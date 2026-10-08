<?php

namespace App\Filters;

use App\Services\AuthService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $userId = session()->get('user_id');
        if (! $userId) {
            return redirect()->to(site_url('login'))->with('error', 'Silakan masuk terlebih dahulu.');
        }
        if (! (new AuthService())->isActive((int) $userId)) {
            session()->destroy();
            return redirect()->to(site_url('login'))->with('error', 'Akun tidak aktif.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
