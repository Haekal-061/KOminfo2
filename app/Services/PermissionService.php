<?php

namespace App\Services;

use App\Repositories\DatabaseRepository;

class PermissionService
{
    public static function allows(string $permission): bool
    {
        $userId = session()->get('user_id');
        if (! $userId) {
            return false;
        }
        return (new DatabaseRepository())->table('users u')
            ->join('role_permissions rp', 'rp.role_id = u.role_id')
            ->join('permissions p', 'p.id = rp.permission_id')
            ->where('u.id', $userId)->where('u.is_active', 1)->where('p.code', $permission)
            ->countAllResults() > 0;
    }
}
