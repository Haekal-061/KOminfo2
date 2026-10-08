<?php

namespace App\Commands;

use App\Services\NotificationService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class NotificationsWork extends BaseCommand
{
    protected $group = 'Notifications';
    protected $name = 'notifications:work';
    protected $description = 'Kirim pesan WhatsApp yang menunggu di antrean.';

    public function run(array $params)
    {
        $limit = max(1, min(100, (int) ($params[0] ?? 20)));
        $result = (new NotificationService())->process($limit);
        CLI::write($result['processed'] . ' antrean diproses; ' . $result['sent'] . ' terkirim, ' . $result['failed'] . ' gagal.');
    }
}
