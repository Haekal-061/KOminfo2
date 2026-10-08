<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddWebhookEventQueueMetadata extends Migration
{
    public function up()
    {
        $this->forge->addColumn('webhook_events', [
            'attempt_count' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'available_at' => ['type' => 'DATETIME', 'null' => true],
            'locked_at' => ['type' => 'DATETIME', 'null' => true],
            'last_error' => ['type' => 'TEXT', 'null' => true],
        ]);
        $table = $this->db->prefixTable('webhook_events');
        $this->db->query("CREATE INDEX idx_webhook_queue_pending ON {$table} (processing_status, available_at)");
    }

    public function down()
    {
        $table = $this->db->prefixTable('webhook_events');
        $this->db->query("DROP INDEX idx_webhook_queue_pending ON {$table}");
        $this->forge->dropColumn('webhook_events', ['attempt_count', 'available_at', 'locked_at', 'last_error']);
    }
}
