<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class TicketingSeeder extends Seeder
{
    public function run()
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');
        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! $password || strlen((string) $password) < 12) {
            throw new \RuntimeException('Set ADMIN_EMAIL and ADMIN_PASSWORD (minimum 12 characters) in .env before running db:seed.');
        }

        $roles = [
            ['name' => 'super_admin', 'label' => 'Super Admin'],
            ['name' => 'admin', 'label' => 'Admin'],
            ['name' => 'supervisor', 'label' => 'Supervisor'],
            ['name' => 'operator', 'label' => 'Operator'],
        ];
        foreach ($roles as $role) {
            if (!$this->db->table('roles')->where('name', $role['name'])->countAllResults()) {
                $this->db->table('roles')->insert($role);
            }
        }

        $permissions = [
            'dashboard.view', 'ticket.view', 'ticket.create', 'ticket.update', 'ticket.assign',
            'ticket.change_status', 'ticket.change_priority', 'ticket.delete', 'report.view',
            'ticket.message.create', 'report.export', 'master.employee.manage', 'master.category.manage',
            'master.service.manage', 'master.status.manage', 'master.priority.manage',
            'master.team.manage', 'user.manage',
        ];
        foreach ($permissions as $permission) {
            if (!$this->db->table('permissions')->where('code', $permission)->countAllResults()) {
                $this->db->table('permissions')->insert(['code' => $permission, 'label' => ucwords(str_replace(['.', '_'], ' ', $permission))]);
            }
        }

        $roleRows = $this->db->table('roles')->get()->getResultArray();
        $permissionRows = $this->db->table('permissions')->get()->getResultArray();
        foreach ($roleRows as $role) {
            foreach ($permissionRows as $permission) {
                $allowed = $role['name'] === 'super_admin'
                    || $role['name'] === 'admin'
                    || ($role['name'] === 'supervisor' && !in_array($permission['code'], ['ticket.delete', 'user.manage'], true))
                    || ($role['name'] === 'operator' && in_array($permission['code'], ['ticket.view', 'ticket.message.create', 'ticket.change_status'], true));
                if ($allowed && !$this->db->table('role_permissions')->where(['role_id' => $role['id'], 'permission_id' => $permission['id']])->countAllResults()) {
                    $this->db->table('role_permissions')->insert(['role_id' => $role['id'], 'permission_id' => $permission['id']]);
                }
            }
        }

        $categories = [
            ['name' => 'Jaringan', 'code' => 'jaringan'],
            ['name' => 'Absensi', 'code' => 'absensi'],
            ['name' => 'Aplikasi', 'code' => 'aplikasi'],
            ['name' => 'Perangkat', 'code' => 'perangkat'],
            ['name' => 'Lainnya', 'code' => 'lainnya'],
        ];
        foreach ($categories as $category) {
            if (!$this->db->table('categories')->where('code', $category['code'])->countAllResults()) {
                $this->db->table('categories')->insert($category);
            }
        }

        $services = [
            'Jaringan' => ['Internet', 'WiFi', 'LAN', 'VPN'],
            'Absensi' => ['Mesin Absensi', 'Aplikasi Absensi', 'Data Kehadiran'],
            'Aplikasi' => ['Aplikasi Internal', 'Website'],
            'Perangkat' => ['Komputer', 'Printer', 'Scanner'],
            'Lainnya' => ['Permintaan Informasi', 'Lainnya'],
        ];
        foreach ($services as $categoryName => $serviceNames) {
            $category = $this->db->table('categories')->where('name', $categoryName)->get()->getRowArray();
            foreach ($serviceNames as $serviceName) {
                if (!$this->db->table('service_types')->where(['category_id' => $category['id'], 'name' => $serviceName])->countAllResults()) {
                    $this->db->table('service_types')->insert(['category_id' => $category['id'], 'name' => $serviceName]);
                }
            }
        }

        $statuses = [
            ['code' => 'NEW', 'name' => 'Baru', 'color' => '#2563eb', 'is_initial' => 1, 'sort_order' => 1],
            ['code' => 'TRIAGED', 'name' => 'Ditriage', 'color' => '#7c3aed', 'sort_order' => 2],
            ['code' => 'ASSIGNED', 'name' => 'Ditugaskan', 'color' => '#0891b2', 'sort_order' => 3],
            ['code' => 'IN_PROGRESS', 'name' => 'Sedang Ditangani', 'color' => '#0284c7', 'sort_order' => 4],
            ['code' => 'WAITING_REPORTER', 'name' => 'Menunggu Pelapor', 'color' => '#d97706', 'sort_order' => 5],
            ['code' => 'RESOLVED', 'name' => 'Selesai Ditangani', 'color' => '#16a34a', 'sort_order' => 6],
            ['code' => 'CLOSED', 'name' => 'Ditutup', 'color' => '#64748b', 'is_final' => 1, 'sort_order' => 7],
            ['code' => 'REOPENED', 'name' => 'Dibuka Kembali', 'color' => '#dc2626', 'sort_order' => 8],
        ];
        foreach ($statuses as $status) {
            if (!$this->db->table('ticket_statuses')->where('code', $status['code'])->countAllResults()) {
                $this->db->table('ticket_statuses')->insert($status);
            }
        }
        $transitionCodes = [
            'NEW' => ['TRIAGED'],
            'TRIAGED' => ['ASSIGNED', 'IN_PROGRESS'],
            'ASSIGNED' => ['IN_PROGRESS'],
            'IN_PROGRESS' => ['WAITING_REPORTER', 'RESOLVED'],
            'WAITING_REPORTER' => ['IN_PROGRESS', 'RESOLVED'],
            'RESOLVED' => ['CLOSED', 'REOPENED'],
            'REOPENED' => ['IN_PROGRESS'],
        ];
        foreach ($transitionCodes as $fromCode => $toCodes) {
            $from = $this->db->table('ticket_statuses')->where('code', $fromCode)->get()->getRowArray();
            foreach ($toCodes as $toCode) {
                $to = $this->db->table('ticket_statuses')->where('code', $toCode)->get()->getRowArray();
                if (!$this->db->table('ticket_status_transitions')->where(['from_status_id' => $from['id'], 'to_status_id' => $to['id']])->countAllResults()) {
                    $this->db->table('ticket_status_transitions')->insert(['from_status_id' => $from['id'], 'to_status_id' => $to['id']]);
                }
            }
        }

        foreach ([
            ['code' => 'LOW', 'name' => 'Low', 'color' => '#64748b', 'sort_order' => 1],
            ['code' => 'MEDIUM', 'name' => 'Medium', 'color' => '#ca8a04', 'sort_order' => 2, 'is_default' => 1],
            ['code' => 'HIGH', 'name' => 'High', 'color' => '#ea580c', 'sort_order' => 3],
            ['code' => 'CRITICAL', 'name' => 'Critical', 'color' => '#dc2626', 'sort_order' => 4],
        ] as $priority) {
            if (!$this->db->table('priorities')->where('code', $priority['code'])->countAllResults()) {
                $this->db->table('priorities')->insert($priority);
            }
        }

        foreach ($this->db->table('priorities')->get()->getResultArray() as $priority) {
            if (!$this->db->table('sla_policies')->where('priority_id', $priority['id'])->countAllResults()) {
                $minutes = ['LOW' => [480, 2880], 'MEDIUM' => [240, 1440], 'HIGH' => [60, 240], 'CRITICAL' => [15, 120]][$priority['code']] ?? [240, 1440];
                $this->db->table('sla_policies')->insert(['name' => 'Default ' . $priority['name'], 'priority_id' => $priority['id'], 'response_minutes' => $minutes[0], 'resolution_minutes' => $minutes[1], 'is_active' => 1]);
            }
        }

        if (!$this->db->table('teams')->where('name', 'Tim Teknologi Informasi')->countAllResults()) {
            $this->db->table('teams')->insert(['name' => 'Tim Teknologi Informasi', 'description' => 'Tim penanganan tiket KOMINFO PINRANG']);
        }

        if (!$this->db->table('users')->where('email', $email)->countAllResults()) {
            $adminRole = $this->db->table('roles')->where('name', 'super_admin')->get()->getRowArray();
            $this->db->table('users')->insert([
                'role_id' => $adminRole['id'],
                'name' => env('ADMIN_NAME') ?: 'Administrator',
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'is_active' => 1,
            ]);
        }
    }
}
