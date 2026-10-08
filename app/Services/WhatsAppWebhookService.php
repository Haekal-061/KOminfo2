<?php

namespace App\Services;

use App\Repositories\DatabaseRepository;
use CodeIgniter\Database\BaseConnection;

class WhatsAppWebhookService
{
    private DatabaseRepository $db;
    private TicketService $tickets;

    public function __construct(
        BaseConnection|DatabaseRepository|null $db = null,
        ?TicketService $tickets = null,
    ) {
        $this->db = $db instanceof DatabaseRepository ? $db : new DatabaseRepository($db);
        $this->tickets ??= new TicketService($this->db);
    }

    public function enqueue(array $payload, ?string $idempotencyKey = null): array
    {
        $this->validateEnvelope($payload);
        $eventType = $this->eventType($payload);
        $eventPayload = is_array($payload['payload'] ?? null) ? $payload['payload'] : $payload;
        $message = is_array($eventPayload['message'] ?? null)
            ? $eventPayload['message']
            : (is_array($eventPayload['data'] ?? null) ? $eventPayload['data'] : $eventPayload);
        $messageId = $this->messageId($message);
        $sessionId = is_string($payload['sessionId'] ?? null) ? $payload['sessionId'] : '';
        $idempotencyKey = $idempotencyKey ?: (is_string($payload['idempotencyKey'] ?? null) ? $payload['idempotencyKey'] : null);
        $rawKey = $idempotencyKey ?: ($sessionId !== ''
            ? $sessionId . "\0" . $eventType . "\0" . ($messageId ?: hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)))
            : ($messageId ?: hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR))));
        $externalId = $sessionId === '' && $idempotencyKey === null && $messageId !== '' && strlen($messageId) <= 190
            ? $messageId
            : hash('sha256', $rawKey);
        $now = date('Y-m-d H:i:s');

        $this->db->table('webhook_events')->ignore(true)->insert([
            'channel' => 'whatsapp',
            'external_message_id' => $externalId,
            'event_type' => $eventType,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'processing_status' => 'received',
            'received_at' => $now,
            'available_at' => $now,
        ]);

        return [
            'accepted' => true,
            'duplicate' => $this->db->affectedRows() !== 1,
        ];
    }

    public function processPending(int $limit): array
    {
        $now = date('Y-m-d H:i:s');
        $staleLock = date('Y-m-d H:i:s', time() - 600);
        $this->db->table('webhook_events')->where('processing_status', 'processing')
            ->where('locked_at <', $staleLock)
            ->update(['processing_status' => 'received', 'locked_at' => null, 'available_at' => $now]);

        $pending = $this->db->table('webhook_events')->where('processing_status', 'received')
            ->groupStart()->where('available_at', null)->orWhere('available_at <=', $now)->groupEnd()
            ->orderBy('id')->limit(max(1, min(100, $limit)))->get()->getResultArray();
        $stats = ['processed' => 0, 'completed' => 0, 'failed' => 0];

        foreach ($pending as $event) {
            $this->db->table('webhook_events')->where(['id' => $event['id'], 'processing_status' => 'received'])
                ->update(['processing_status' => 'processing', 'locked_at' => $now]);
            if ($this->db->affectedRows() !== 1) {
                continue;
            }

            $stats['processed']++;
            $transactionStarted = false;
            try {
                $payload = json_decode($event['payload'], true, 512, JSON_THROW_ON_ERROR);
                if (! is_array($payload)) {
                    throw new \UnexpectedValueException('Payload webhook tersimpan bukan objek JSON.');
                }
                $transactionStarted = $this->db->transBegin();
                if (! $transactionStarted) {
                    throw new \RuntimeException('Tidak dapat memulai transaksi pemrosesan webhook.');
                }
                $this->processEvent($event, $payload);
                if (! $this->db->transStatus()) {
                    throw new \RuntimeException('Pemrosesan webhook gagal disimpan.');
                }
                if (! $this->db->transCommit()) {
                    throw new \RuntimeException('Tidak dapat menyimpan hasil pemrosesan webhook.');
                }
                $transactionStarted = false;
                $stats['completed']++;
            } catch (\Throwable $exception) {
                if ($transactionStarted) {
                    $this->db->transRollback();
                }
                $attempt = (int) $event['attempt_count'] + 1;
                $terminal = $attempt >= 5;
                $this->db->table('webhook_events')->where('id', $event['id'])->update([
                    'processing_status' => $terminal ? 'failed' : 'received',
                    'attempt_count' => $attempt,
                    'available_at' => date('Y-m-d H:i:s', time() + min(3600, 60 * (2 ** ($attempt - 1)))),
                    'locked_at' => null,
                    'last_error' => mb_substr($exception->getMessage(), 0, 1000),
                ]);
                log_message('error', 'OpenWA webhook event #{id} failed on attempt {attempt}: {error}', [
                    'id' => $event['id'], 'attempt' => $attempt, 'error' => $exception->getMessage(),
                ]);
                $stats['failed']++;
            }
        }

        return $stats;
    }

    private function processEvent(array $event, array $payload): void
    {
        $eventType = $this->eventType($payload);
        if ($eventType !== 'message.received') {
            $this->markProcessed((int) $event['id'], 'ignored');
            return;
        }

        $eventPayload = is_array($payload['payload'] ?? null) ? $payload['payload'] : $payload;
        $message = is_array($eventPayload['message'] ?? null)
            ? $eventPayload['message']
            : (is_array($eventPayload['data'] ?? null) ? $eventPayload['data'] : $eventPayload);
        $messageId = $this->messageId($message);
        $senderRaw = (string) ($message['senderPhone'] ?? $message['from'] ?? $message['chatId'] ?? $message['sender'] ?? '');
        $body = trim((string) ($message['body'] ?? $message['text'] ?? $message['caption'] ?? (is_string($message['message'] ?? null) ? $message['message'] : '')));

        if (($message['fromMe'] ?? false) || ($message['isFromMe'] ?? false)) {
            $this->markProcessed((int) $event['id'], 'ignored');
            return;
        }
        if (str_ends_with(strtolower($senderRaw), '@lid')) {
            $this->markProcessed((int) $event['id'], 'rejected');
            return;
        }
        if (str_contains($senderRaw, '@g.us') || ($message['isGroupMsg'] ?? $message['isGroup'] ?? false)) {
            $this->markProcessed((int) $event['id'], 'ignored');
            return;
        }
        if ($messageId === '' || $senderRaw === '' || $body === '') {
            $this->markProcessed((int) $event['id'], 'rejected');
            return;
        }
        if ($this->db->table('ticket_messages')->where(['channel' => 'whatsapp', 'external_message_id' => $messageId])->countAllResults()) {
            $this->markProcessed((int) $event['id'], 'processed');
            return;
        }

        try {
            $phone = PhoneNumberService::normalize(preg_replace('/@.*/', '', $senderRaw) ?? $senderRaw);
        } catch (\InvalidArgumentException) {
            $this->markProcessed((int) $event['id'], 'rejected');
            return;
        }
        $employee = $this->db->table('employees')->where(['whatsapp_normalized' => $phone, 'is_active' => 1])->get()->getRowArray();
        if (! $employee) {
            $this->tickets->queueMessage($phone, "Nomor Anda belum terdaftar sebagai pegawai KOMINFO PINRANG.\n\nSilakan hubungi administrator untuk melakukan registrasi.");
            $this->markProcessed((int) $event['id'], 'rejected');
            return;
        }

        $this->processRegisteredMessage($employee, $phone, $messageId, $body);
        $this->markProcessed((int) $event['id'], 'processed');
    }

    private function eventType(array $payload): string
    {
        $nested = is_array($payload['payload'] ?? null) ? $payload['payload'] : [];
        $event = $payload['event'] ?? $nested['event'] ?? 'message.received';
        return is_string($event) && $event !== '' && strlen($event) <= 100 ? $event : 'unknown';
    }

    private function validateEnvelope(array $payload): void
    {
        $nested = is_array($payload['payload'] ?? null) ? $payload['payload'] : [];
        $event = $payload['event'] ?? $nested['event'] ?? null;
        $hasV5Field = array_key_exists('webhookId', $payload)
            || array_key_exists('sessionId', $payload)
            || array_key_exists('timestamp', $payload);

        if ($hasV5Field) {
            if (! is_string($payload['webhookId'] ?? null) || $payload['webhookId'] === ''
                || ! is_string($payload['sessionId'] ?? null) || $payload['sessionId'] === ''
                || ! is_string($event) || $event === '' || strlen($event) > 100
                || ! isset($payload['timestamp']) || ! is_numeric($payload['timestamp'])
                || ! is_finite((float) $payload['timestamp'])
                || ! array_key_exists('payload', $payload)) {
                throw new \InvalidArgumentException('Envelope webhook v5 tidak valid.');
            }
            return;
        }

        $legacyPayload = is_array($payload['payload'] ?? null)
            ? ($nested['data'] ?? null)
            : ($payload['data'] ?? null);
        if (! is_string($event) || $event === '' || strlen($event) > 100 || ! is_array($legacyPayload)) {
            throw new \InvalidArgumentException('Envelope webhook tidak valid.');
        }
    }

    private function messageId(array $message): string
    {
        $id = $message['id'] ?? $message['messageId'] ?? $message['message_id'] ?? '';
        if (is_array($id)) {
            $id = $id['_serialized'] ?? $id['serialized'] ?? '';
        }
        return is_string($id) || is_numeric($id) ? (string) $id : '';
    }

    private function processRegisteredMessage(array $employee, string $phone, string $messageId, string $body): string
    {
        $conversation = $this->db->table('wa_conversations')->where('employee_id', $employee['id'])->get()->getRowArray();
        if ($conversation && $conversation['state'] === 'waiting_ticket_selection') {
            if (mb_strtoupper($body) === 'BATAL') {
                $this->db->table('wa_conversations')->where('id', $conversation['id'])->delete();
                $this->tickets->queueMessage($phone, 'Pemilihan ticket dibatalkan.');
                return 'ticket_selection_cancelled';
            }
            $activeTickets = $this->activeTicketsFor((int) $employee['id']);
            $selected = ctype_digit($body) ? ($activeTickets[(int) $body - 1] ?? null) : null;
            if (! $selected) {
                $this->tickets->queueMessage($phone, "Balas dengan nomor ticket dari daftar atau BATAL.\n" . $this->ticketChoices($activeTickets));
                return 'ticket_selection_required';
            }
            $this->db->table('wa_conversations')->where('id', $conversation['id'])->update([
                'state' => 'waiting_ticket_message', 'ticket_id' => $selected['id'], 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->tickets->queueMessage($phone, "Ticket {$selected['ticket_number']} dipilih. Kirim pesan yang ingin ditambahkan, atau BATAL.");
            return 'ticket_selected';
        }
        if ($conversation && $conversation['state'] === 'waiting_ticket_message') {
            if (mb_strtoupper($body) === 'BATAL') {
                $this->db->table('wa_conversations')->where('id', $conversation['id'])->delete();
                $this->tickets->queueMessage($phone, 'Pengiriman pesan ke ticket dibatalkan.');
                return 'ticket_message_cancelled';
            }
            $ticket = $this->db->table('tickets t')->select('t.id, t.ticket_number')
                ->join('ticket_statuses s', 's.id = t.status_id')
                ->where(['t.id' => $conversation['ticket_id'], 't.reporter_id' => $employee['id'], 's.is_final' => 0])
                ->where('t.deleted_at', null)->get()->getRowArray();
            $this->db->table('wa_conversations')->where('id', $conversation['id'])->delete();
            if (! $ticket) {
                $this->tickets->queueMessage($phone, 'Ticket yang dipilih tidak lagi tersedia. Kirim ADUAN untuk memulai kembali.');
                return 'selected_ticket_unavailable';
            }
            $this->tickets->message((int) $ticket['id'], 'whatsapp', $messageId, 'in', $phone, null, $body, 'received_at');
            return 'message_attached:' . $ticket['ticket_number'];
        }
        if ($conversation && $conversation['state'] === 'waiting_category') {
            $categories = $this->activeCategories();
            $selected = ctype_digit($body) ? ($categories[(int) $body - 1] ?? null) : null;
            if (! $selected) {
                $this->tickets->queueMessage($phone, $this->categoryPrompt($categories));
                return 'category_selection_required';
            }
            $this->db->table('wa_conversations')->where('id', $conversation['id'])->update(['state' => 'waiting_description', 'category_id' => $selected['id'], 'updated_at' => date('Y-m-d H:i:s')]);
            $this->tickets->queueMessage($phone, 'Silakan jelaskan masalah Anda dalam satu pesan.');
            return 'description_required';
        }
        if ($conversation && $conversation['state'] === 'waiting_description') {
            if (mb_strlen($body) < 4) {
                $this->tickets->queueMessage($phone, 'Mohon jelaskan masalah Anda dengan lebih lengkap.');
                return 'description_required';
            }
            $ticket = $this->tickets->create([
                'reporter_id' => $employee['id'], 'channel' => 'whatsapp',
                'category_id' => (int) $conversation['category_id'],
                'subject' => mb_substr(preg_replace('/\s+/', ' ', $body) ?? $body, 0, 100),
                'description' => $body, 'initial_message' => $body,
                'sender' => $phone, 'recipient' => $phone, 'external_message_id' => $messageId,
            ]);
            $this->db->table('wa_conversations')->where('id', $conversation['id'])->delete();
            return 'created:' . $ticket['ticket_number'];
        }

        if (preg_match('/^aduan$|^buat(?:\s+ticket)?$|^lapor$/iu', $body)) {
            $categories = $this->activeCategories();
            if ($categories === []) {
                $this->tickets->queueMessage($phone, 'Kategori aduan belum tersedia. Silakan hubungi administrator.');
                return 'no_category';
            }
            $this->db->table('wa_conversations')->replace([
                'employee_id' => $employee['id'], 'state' => 'waiting_category',
                'category_id' => null, 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->tickets->queueMessage($phone, $this->categoryPrompt($categories));
            return 'guided_flow_started';
        }

        if (preg_match('/#(TCK-\d{8}-\d{4})/i', $body, $match)) {
            $ticket = $this->db->table('tickets t')->select('t.id, t.ticket_number')
                ->join('ticket_statuses s', 's.id = t.status_id')
                ->where(['t.ticket_number' => strtoupper($match[1]), 't.reporter_id' => $employee['id'], 's.is_final' => 0])
                ->where('t.deleted_at', null)->get()->getRowArray();
            $reply = trim(preg_replace('/#TCK-\d{8}-\d{4}/i', '', $body) ?? '');
            if (! $ticket) {
                $this->tickets->queueMessage($phone, 'Nomor ticket tidak ditemukan pada akun Anda. Periksa kembali referensi ticket.');
                return 'ticket_not_found';
            }
            if ($reply !== '') {
                $this->tickets->message((int) $ticket['id'], 'whatsapp', $messageId, 'in', $phone, null, $reply, 'received_at');
                return 'message_attached:' . $ticket['ticket_number'];
            }
            $this->tickets->queueMessage($phone, "Balas dengan #{$ticket['ticket_number']} diikuti pesan Anda untuk menambahkan informasi.");
            return 'ticket_reference_confirmed';
        }

        if (preg_match('/^baru\s*:\s*(.+)$/isu', $body, $newMatch)) {
            $category = $this->db->table('categories')->where('is_active', 1)->orderBy('id')->get()->getRowArray();
            if (! $category) {
                $this->tickets->queueMessage($phone, 'Kategori aduan belum tersedia. Silakan hubungi administrator.');
                return 'no_category';
            }
            $description = trim($newMatch[1]);
            $ticket = $this->tickets->create([
                'reporter_id' => $employee['id'], 'channel' => 'whatsapp', 'category_id' => $category['id'],
                'subject' => mb_substr(preg_replace('/\s+/', ' ', $description) ?? $description, 0, 100),
                'description' => $description, 'initial_message' => $description, 'sender' => $phone, 'recipient' => $phone, 'external_message_id' => $messageId,
            ]);
            return 'created:' . $ticket['ticket_number'];
        }

        $activeTickets = $this->db->table('tickets t')
            ->select('t.ticket_number, c.name AS category_name')
            ->join('ticket_statuses s', 's.id = t.status_id')
            ->join('categories c', 'c.id = t.category_id')
            ->where('t.reporter_id', $employee['id'])->where('t.deleted_at', null)->where('s.is_final', 0)
            ->orderBy('t.created_at', 'DESC')->get()->getResultArray();
        if ($activeTickets !== []) {
            if (count($activeTickets) > 1) {
                $this->db->table('wa_conversations')->replace([
                    'employee_id' => $employee['id'], 'state' => 'waiting_ticket_selection',
                    'category_id' => null, 'ticket_id' => null, 'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $this->tickets->queueMessage($phone, "Pilih ticket aktif dengan membalas nomornya:\n" . $this->ticketChoices($activeTickets) . "\nBalas BATAL untuk membatalkan.");
                return 'ticket_selection_started';
            }
            $choices = implode("\n", array_map(static fn (array $row): string => '- ' . $row['ticket_number'] . ' - ' . $row['category_name'], $activeTickets));
            $this->tickets->queueMessage($phone, "Pesan tidak otomatis ditambahkan ke ticket aktif.\n{$choices}\n\nBalas #TCK-YYYYMMDD-NNNN diikuti pesan, atau kirim ADUAN untuk membuat ticket baru.");
            return 'explicit_ticket_reference_required';
        }

        $category = $this->db->table('categories')->where('is_active', 1)->orderBy('id')->get()->getRowArray();
        if (! $category) {
            $this->tickets->queueMessage($phone, 'Kategori aduan belum tersedia. Silakan hubungi administrator.');
            return 'no_category';
        }
        $ticket = $this->tickets->create([
            'reporter_id' => $employee['id'], 'channel' => 'whatsapp', 'category_id' => $category['id'],
            'subject' => mb_substr(preg_replace('/\s+/', ' ', $body) ?? $body, 0, 100),
            'description' => $body, 'initial_message' => $body, 'sender' => $phone, 'recipient' => $phone, 'external_message_id' => $messageId,
        ]);
        return 'created:' . $ticket['ticket_number'];
    }

    private function activeCategories(): array
    {
        return $this->db->table('categories')->where('is_active', 1)->orderBy('id')->get()->getResultArray();
    }

    private function activeTicketsFor(int $employeeId): array
    {
        return $this->db->table('tickets t')
            ->select('t.id, t.ticket_number, c.name AS category_name')
            ->join('ticket_statuses s', 's.id = t.status_id')
            ->join('categories c', 'c.id = t.category_id')
            ->where('t.reporter_id', $employeeId)->where('t.deleted_at', null)->where('s.is_final', 0)
            ->orderBy('t.created_at', 'DESC')->get()->getResultArray();
    }

    private function ticketChoices(array $tickets): string
    {
        $choices = [];
        foreach ($tickets as $index => $ticket) {
            $choices[] = ($index + 1) . '. ' . $ticket['ticket_number'] . ' - ' . $ticket['category_name'];
        }
        return implode("\n", $choices);
    }

    private function categoryPrompt(array $categories): string
    {
        $choices = [];
        foreach ($categories as $index => $category) {
            $choices[] = ($index + 1) . '. ' . $category['name'];
        }
        return "Pilih kategori aduan dengan membalas nomornya:\n" . implode("\n", $choices);
    }

    private function markProcessed(int $eventId, string $status): void
    {
        $this->db->table('webhook_events')->where('id', $eventId)->update([
            'processing_status' => $status, 'processed_at' => date('Y-m-d H:i:s'), 'locked_at' => null,
        ]);
    }
}
