<?php

namespace App\Services;

use App\Repositories\DatabaseRepository;
use CodeIgniter\Database\BaseConnection;

class NotificationService
{
    private DatabaseRepository $db;
    private OpenWAClient $openWA;

    public function __construct(
        BaseConnection|DatabaseRepository|null $db = null,
        ?OpenWAClient $openWA = null,
    ) {
        $this->db = $db instanceof DatabaseRepository ? $db : new DatabaseRepository($db);
        $this->openWA ??= new OpenWAClient();
    }

    public function process(int $limit): array
    {
        $staleLock = date('Y-m-d H:i:s', time() - 600);
        $this->db->table('notification_queue')->where('status', 'processing')->where('locked_at <', $staleLock)
            ->update(['status' => 'pending', 'available_at' => date('Y-m-d H:i:s')]);
        $items = $this->db->table('notification_queue')->where('status', 'pending')
            ->where('available_at <=', date('Y-m-d H:i:s'))->orderBy('id')->limit($limit)->get()->getResultArray();
        $stats = ['processed' => 0, 'sent' => 0, 'failed' => 0];
        foreach ($items as $item) {
            $claimed = $this->db->table('notification_queue')->where(['id' => $item['id'], 'status' => 'pending'])
                ->update(['status' => 'processing', 'locked_at' => date('Y-m-d H:i:s')]);
            if (! $claimed || $this->db->affectedRows() !== 1) {
                continue;
            }
            $attempt = (int) $item['attempt_count'] + 1;
            $stats['processed']++;
            try {
                $this->openWA->sendText($item['recipient'], $item['body'], 'ticketing-queue-' . $item['id']);
                $this->db->table('notification_queue')->where('id', $item['id'])->update([
                    'status' => 'sent', 'attempt_count' => $attempt,
                    'sent_at' => date('Y-m-d H:i:s'), 'last_error' => null,
                ]);
                if ($item['message_id']) {
                    $this->db->table('ticket_messages')->where('id', $item['message_id'])->update([
                        'delivery_status' => 'sent', 'sent_at' => date('Y-m-d H:i:s'),
                    ]);
                }
                if ($item['ticket_id']) {
                    $this->db->table('tickets')->where('id', $item['ticket_id'])
                        ->where('first_response_at', null)
                        ->update(['first_response_at' => date('Y-m-d H:i:s')]);
                    (new TicketService($this->db))->activity((int) $item['ticket_id'], 'message_sent', 'Pesan WhatsApp berhasil dikirim.', null, ['queue_id' => $item['id']]);
                }
                $stats['sent']++;
            } catch (\Throwable $exception) {
                $terminal = $attempt >= 5;
                $this->db->table('notification_queue')->where('id', $item['id'])->update([
                    'status' => $terminal ? 'failed' : 'pending',
                    'attempt_count' => $attempt,
                    'last_error' => mb_substr($exception->getMessage(), 0, 1000),
                    'available_at' => date('Y-m-d H:i:s', time() + min(3600, 60 * (2 ** ($attempt - 1)))),
                ]);
                if ($terminal && $item['message_id']) {
                    $this->db->table('ticket_messages')->where('id', $item['message_id'])->update(['delivery_status' => 'failed']);
                }
                if ($terminal && $item['ticket_id']) {
                    (new TicketService($this->db))->activity((int) $item['ticket_id'], 'message_failed', 'Pesan WhatsApp gagal setelah percobaan maksimum.', null, ['queue_id' => $item['id']]);
                }
                log_message('error', 'OpenWA notification #{id} failed on attempt {attempt}: {error}', [
                    'id' => $item['id'], 'attempt' => $attempt, 'error' => $exception->getMessage(),
                ]);
                $stats['failed']++;
            }
        }
        return $stats;
    }
}
