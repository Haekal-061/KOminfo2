<?php

namespace App\Services;

use App\Repositories\DatabaseRepository;

class AuthService
{
    public function authenticate(string $email, string $password, string $ipAddress, string $userAgent): ?array
    {
        $db = new DatabaseRepository();
        $user = $db->table('users u')->select('u.*, r.name AS role_name')
            ->join('roles r', 'r.id = u.role_id')->where('u.email', $email)->where('u.is_active', 1)
            ->get()->getRowArray();
        if (! $user || ! password_verify($password, $user['password_hash'])) {
            log_message('warning', 'Failed login attempt for email {email} from {ip}', ['email' => $email, 'ip' => $ipAddress]);
            return null;
        }

        $db->table('audit_logs')->insert([
            'user_id' => $user['id'], 'action' => 'login', 'entity_type' => 'users', 'entity_id' => $user['id'],
            'ip_address' => $ipAddress, 'user_agent' => substr($userAgent, 0, 255),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        unset($user['password_hash']);
        return $user;
    }

    public function isActive(int $userId): bool
    {
        return (new DatabaseRepository())->table('users')
            ->where(['id' => $userId, 'is_active' => 1])->countAllResults() > 0;
    }
}
