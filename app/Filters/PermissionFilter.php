<?php

namespace App\Filters;

use App\Services\PermissionService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class PermissionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $permission = $arguments[0] ?? '';
        $userId = session()->get('user_id');
        if (! $userId || $permission === '') {
            return redirect()->to(site_url('login'))->with('error', 'Akses tidak diizinkan.');
        }

        if (! PermissionService::allows($permission)) {
            return service('response')->setStatusCode(403)->setBody('Akses tidak diizinkan.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
