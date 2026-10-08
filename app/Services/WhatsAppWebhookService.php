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

    public function handle(array $payload): array
    {
        $envelope = is_array($payload['payload'] ?? null) ? $payload['payload'] : $payload;
        $data = is_array($envelope['data'] ?? null) ? $envelope['data'] : $envelope;
        $messageId = (string) ($data['id'] ?? $data['messageId'] ?? $data['message_id'] ?? '');
        $senderRaw = (string) ($data['senderPhone'] ?? $data['from'] ?? $data['chatId'] ?? $data['sender'] ?? '');
        $body = trim((string) ($data['body'] ?? $data['text'] ?? $data['message'] ?? ''));
        $eventType = (string) ($envelope['event'] ?? $payload['event'] ?? 'message.received');
        if ($messageId === '') {
            throw new \InvalidArgumentException('Payload webhook harus memiliki id pesan.');
        }
        $now = date('Y-m-d H:i:s');
        $this->db->query(
            'INSERT IGNORE INTO webhook_events (channel, external_message_id, event_type, payload, processing_status, received_at) VALUES (?, ?, ?, ?, ?, ?)',
            ['whatsapp', $messageId, $eventType, json_encode($payload, JSON_THROW_ON_ERROR), 'received', $now],
        );
        if ($this->db->affectedRows() !== 1) {
            return ['accepted' => true, 'duplicate' => true];
        }
        $eventId = (int) $this->db->insertID();
        if ($eventType !== 'message.received') {
            $this->markProcessed($eventId, 'ignored');
            return ['accepted' => true, 'ignored' => 'unsupported_event'];
        }
        if ($senderRaw === '' || $body === '') {
            $this->markProcessed($eventId, 'rejected');
            throw new \InvalidArgumentException('Payload message.received harus memiliki pengirim dan teks pesan.');
        }
        if (str_ends_with(strtolower($senderRaw), '@lid')) {
            $this->markProcessed($eventId, 'rejected');
            return ['accepted' => true, 'ignored' => 'unresolved_lid_sender'];
        }
        if (str_contains($senderRaw, '@g.us') || ($data['isGroup'] ?? false)) {
            $this->markProcessed($eventId, 'ignored');
            return ['accepted' => true, 'ignored' => 'group_message'];
        }

        try {
            $phone = PhoneNumberService::normalize(preg_replace('/@.*/', '', $senderRaw) ?? $senderRaw);
        } catch (\InvalidArgumentException) {
            $this->db->table('webhook_events')->where('id', $eventId)->update(['processing_status' => 'rejected', 'processed_at' => $now]);
            return ['accepted' => true, 'ignored' => 'invalid_sender'];
        }
        $employee = $this->db->table('employees')->where(['whatsapp_normalized' => $phone, 'is_active' => 1])->get()->getRowArray();
        if (! $employee) {
            $this->tickets->queueMessage($phone, "Nomor Anda belum terdaftar sebagai pegawai KOMINFO PINRANG.\n\nSilakan hubungi administrator untuk melakukan registrasi.");
            $this->markProcessed($eventId, 'rejected');
            return ['accepted' => true, 'ignored' => 'unregistered_reporter'];
        }

        $outcome = $this->processRegisteredMessage($employee, $phone, $messageId, $body);
        $this->markProcessed($eventId, 'processed');
        return ['accepted' => true, 'result' => $outcome];
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
            'processing_status' => $status, 'processed_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
