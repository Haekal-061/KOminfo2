<?php

namespace App\Commands;

use App\Services\WhatsAppWebhookService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class OpenWAWebhooksWork extends BaseCommand
{
    protected $group = 'OpenWA';
    protected $name = 'openwa:webhooks:work';
    protected $description = 'Proses event webhook OpenWA yang tersimpan di antrean.';

    public function run(array $params)
    {
        $limit = max(1, min(100, (int) ($params[0] ?? 20)));
        $result = (new WhatsAppWebhookService())->processPending($limit);
        CLI::write(
            $result['processed'] . ' event diproses; '
            . $result['completed'] . ' selesai, ' . $result['failed'] . ' gagal.',
        );
    }
}
