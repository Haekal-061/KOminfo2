<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTicketingMvpSchema extends Migration
{
    public function up()
    {
        $tables = [
            'roles' => ['id' => 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'name' => 'VARCHAR(60) NOT NULL UNIQUE', 'label' => 'VARCHAR(100) NOT NULL', 'created_at' => 'DATETIME NULL'],
            'permissions' => ['id' => 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'code' => 'VARCHAR(100) NOT NULL UNIQUE', 'label' => 'VARCHAR(150) NOT NULL'],
            'role_permissions' => ['role_id' => 'BIGINT UNSIGNED NOT NULL', 'permission_id' => 'BIGINT UNSIGNED NOT NULL', 'PRIMARY KEY' => '(role_id, permission_id)'],
            'users' => ['id' => 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'role_id' => 'BIGINT UNSIGNED NOT NULL', 'name' => 'VARCHAR(150) NOT NULL', 'email' => 'VARCHAR(190) NOT NULL UNIQUE', 'password_hash' => 'VARCHAR(255) NOT NULL', 'is_active' => 'TINYINT(1) NOT NULL DEFAULT 1', 'created_at' => 'DATETIME NULL', 'updated_at' => 'DATETIME NULL'],
            'employees' => ['id' => 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'employee_number' => 'VARCHAR(80) NOT NULL UNIQUE', 'name' => 'VARCHAR(150) NOT NULL', 'whatsapp_number' => 'VARCHAR(30) NOT NULL', 'whatsapp_normalized' => 'VARCHAR(20) NOT NULL UNIQUE', 'email' => 'VARCHAR(190) NULL', 'department' => 'VARCHAR(150) NULL', 'position' => 'VARCHAR(150) NULL', 'is_active' => 'TINYINT(1) NOT NULL DEFAULT 1', 'created_at' => 'DATETIME NULL', 'updated_at' => 'DATETIME NULL'],
            'teams' => ['id' => 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'name' => 'VARCHAR(120) NOT NULL UNIQUE', 'description' => 'TEXT NULL', 'is_active' => 'TINYINT(1) NOT NULL DEFAULT 1', 'created_at' => 'DATETIME NULL', 'updated_at' => 'DATETIME NULL'],
            'team_members' => ['team_id' => 'BIGINT UNSIGNED NOT NULL', 'user_id' => 'BIGINT UNSIGNED NOT NULL', 'PRIMARY KEY' => '(team_id, user_id)'],
            'categories' => ['id' => 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'name' => 'VARCHAR(120) NOT NULL UNIQUE', 'code' => 'VARCHAR(60) NOT NULL UNIQUE', 'description' => 'TEXT NULL', 'color' => 'VARCHAR(20) NOT NULL DEFAULT \'#64748b\'', 'is_active' => 'TINYINT(1) NOT NULL DEFAULT 1', 'created_at' => 'DATETIME NULL', 'updated_at' => 'DATETIME NULL'],
            'service_types' => ['id' => 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'category_id' => 'BIGINT UNSIGNED NOT NULL', 'name' => 'VARCHAR(120) NOT NULL', 'description' => 'TEXT NULL', 'is_active' => 'TINYINT(1) NOT NULL DEFAULT 1', 'created_at' => 'DATETIME NULL', 'updated_at' => 'DATETIME NULL', 'KEY idx_service_category (category_id)'],
            'ticket_statuses' => ['id' => 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'code' => 'VARCHAR(60) NOT NULL UNIQUE', 'name' => 'VARCHAR(100) NOT NULL', 'description' => 'TEXT NULL', 'color' => 'VARCHAR(20) NOT NULL DEFAULT \'#64748b\'', 'is_initial' => 'TINYINT(1) NOT NULL DEFAULT 0', 'is_final' => 'TINYINT(1) NOT NULL DEFAULT 0', 'sort_order' => 'INT NOT NULL DEFAULT 0', 'is_active' => 'TINYINT(1) NOT NULL DEFAULT 1', 'created_at' => 'DATETIME NULL', 'updated_at' => 'DATETIME NULL'],
            'ticket_status_transitions' => ['id' => 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'from_status_id' => 'BIGINT UNSIGNED NOT NULL', 'to_status_id' => 'BIGINT UNSIGNED NOT NULL', 'role_id' => 'BIGINT UNSIGNED NULL', 'UNIQUE KEY uq_transition (from_status_id, to_status_id, role_id)'],
            'priorities' => ['id' => 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'name' => 'VARCHAR(80) NOT NULL UNIQUE', 'code' => 'VARCHAR(40) NOT NULL UNIQUE', 'color' => 'VARCHAR(20) NOT NULL DEFAULT \'#64748b\'', 'sort_order' => 'INT NOT NULL DEFAULT 0', 'is_default' => 'TINYINT(1) NOT NULL DEFAULT 0', 'is_active' => 'TINYINT(1) NOT NULL DEFAULT 1', 'created_at' => 'DATETIME NULL', 'updated_at' => 'DATETIME NULL'],
            'sla_policies' => ['id' => 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'name' => 'VARCHAR(120) NOT NULL', 'service_type_id' => 'BIGINT UNSIGNED NULL', 'priority_id' => 'BIGINT UNSIGNED NOT NULL', 'response_minutes' => 'INT NOT NULL', 'resolution_minutes' => 'INT NOT NULL', 'is_active' => 'TINYINT(1) NOT NULL DEFAULT 1'],
            'ticket_counters' => ['ticket_date' => 'DATE PRIMARY KEY', 'next_number' => 'INT NOT NULL DEFAULT 0'],
            'tickets' => ['id' => 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'ticket_number' => 'VARCHAR(50) NOT NULL UNIQUE', 'reporter_id' => 'BIGINT UNSIGNED NOT NULL', 'channel' => 'VARCHAR(30) NOT NULL', 'category_id' => 'BIGINT UNSIGNED NOT NULL', 'service_type_id' => 'BIGINT UNSIGNED NULL', 'priority_id' => 'BIGINT UNSIGNED NOT NULL', 'status_id' => 'BIGINT UNSIGNED NOT NULL', 'assignee_user_id' => 'BIGINT UNSIGNED NULL', 'team_id' => 'BIGINT UNSIGNED NULL', 'subject' => 'VARCHAR(255) NOT NULL', 'description' => 'TEXT NOT NULL', 'resolution' => 'TEXT NULL', 'first_response_at' => 'DATETIME NULL', 'resolved_at' => 'DATETIME NULL', 'closed_at' => 'DATETIME NULL', 'created_at' => 'DATETIME NOT NULL', 'updated_at' => 'DATETIME NOT NULL', 'deleted_at' => 'DATETIME NULL', 'KEY idx_ticket_status (status_id)', 'KEY idx_ticket_category (category_id)', 'KEY idx_ticket_priority (priority_id)', 'KEY idx_ticket_assignee (assignee_user_id)', 'KEY idx_ticket_created (created_at)', 'KEY idx_ticket_reporter (reporter_id)'],
            'ticket_messages' => ['id' => 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'ticket_id' => 'BIGINT UNSIGNED NOT NULL', 'channel' => 'VARCHAR(30) NOT NULL', 'external_message_id' => 'VARCHAR(190) NULL', 'direction' => 'VARCHAR(12) NOT NULL', 'sender' => 'VARCHAR(40) NULL', 'recipient' => 'VARCHAR(40) NULL', 'message_type' => 'VARCHAR(40) NOT NULL DEFAULT \'text\'', 'delivery_status' => 'VARCHAR(20) NOT NULL DEFAULT \'received\'', 'body' => 'TEXT NOT NULL', 'metadata' => 'JSON NULL', 'sent_at' => 'DATETIME NULL', 'received_at' => 'DATETIME NULL', 'created_at' => 'DATETIME NOT NULL', 'UNIQUE KEY uq_external_message (channel, external_message_id)', 'KEY idx_message_ticket (ticket_id, created_at)'],
            'ticket_activities' => ['id' => 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'ticket_id' => 'BIGINT UNSIGNED NOT NULL', 'user_id' => 'BIGINT UNSIGNED NULL', 'activity_type' => 'VARCHAR(60) NOT NULL', 'description' => 'TEXT NOT NULL', 'metadata' => 'JSON NULL', 'created_at' => 'DATETIME NOT NULL', 'KEY idx_activity_ticket (ticket_id, created_at)'],
            'webhook_events' => ['id' => 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'channel' => 'VARCHAR(30) NOT NULL', 'external_message_id' => 'VARCHAR(190) NULL', 'event_type' => 'VARCHAR(100) NULL', 'payload' => 'JSON NOT NULL', 'processing_status' => 'VARCHAR(30) NOT NULL DEFAULT \'received\'', 'received_at' => 'DATETIME NOT NULL', 'processed_at' => 'DATETIME NULL', 'UNIQUE KEY uq_webhook_message (channel, event_type, external_message_id)'],
            'wa_conversations' => ['id' => 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'employee_id' => 'BIGINT UNSIGNED NOT NULL UNIQUE', 'state' => 'VARCHAR(40) NOT NULL', 'category_id' => 'BIGINT UNSIGNED NULL', 'ticket_id' => 'BIGINT UNSIGNED NULL', 'updated_at' => 'DATETIME NOT NULL'],
            'notification_queue' => ['id' => 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'channel' => 'VARCHAR(30) NOT NULL', 'recipient' => 'VARCHAR(190) NOT NULL', 'body' => 'TEXT NOT NULL', 'ticket_id' => 'BIGINT UNSIGNED NULL', 'message_id' => 'BIGINT UNSIGNED NULL', 'status' => 'VARCHAR(20) NOT NULL DEFAULT \'pending\'', 'attempt_count' => 'INT NOT NULL DEFAULT 0', 'last_error' => 'TEXT NULL', 'available_at' => 'DATETIME NOT NULL', 'locked_at' => 'DATETIME NULL', 'sent_at' => 'DATETIME NULL', 'created_at' => 'DATETIME NOT NULL', 'KEY idx_queue_pending (status, available_at)'],
            'audit_logs' => ['id' => 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'user_id' => 'BIGINT UNSIGNED NULL', 'action' => 'VARCHAR(100) NOT NULL', 'entity_type' => 'VARCHAR(80) NOT NULL', 'entity_id' => 'BIGINT UNSIGNED NULL', 'old_values' => 'JSON NULL', 'new_values' => 'JSON NULL', 'ip_address' => 'VARCHAR(45) NULL', 'user_agent' => 'VARCHAR(255) NULL', 'created_at' => 'DATETIME NOT NULL'],
        ];

        foreach ($tables as $table => $fields) {
            $columns = [];
            foreach ($fields as $name => $definition) {
                $columns[$name] = $definition;
            }
            $this->db->query("CREATE TABLE IF NOT EXISTS `{$table}` (" . implode(', ', array_map(
                static function (string|int $name, string $definition): string {
                    if (is_int($name) || preg_match('/^(PRIMARY KEY|UNIQUE KEY|KEY)\b/', $name)) {
                        return is_int($name) ? $definition : "{$name} {$definition}";
                    }
                    return "`{$name}` {$definition}";
                },
                array_keys($columns),
                array_values($columns),
            )) . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        }
    }

    public function down()
    {
        foreach ([
            'audit_logs', 'notification_queue', 'wa_conversations', 'webhook_events',
            'ticket_activities', 'ticket_messages', 'tickets', 'sla_policies',
            'priorities', 'ticket_status_transitions', 'ticket_statuses', 'ticket_counters',
            'service_types', 'categories', 'team_members', 'teams', 'employees',
            'users', 'role_permissions', 'permissions', 'roles',
        ] as $table) {
            $this->db->query("DROP TABLE IF EXISTS `{$table}`");
        }
    }
}
