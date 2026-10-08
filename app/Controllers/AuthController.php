<?php

namespace App\Controllers;

use App\Services\AuthService;

class AuthController extends BaseController
{
    public function login()
    {
        if (session()->get('user_id')) {
            return redirect()->to(site_url(\App\Services\PermissionService::allows('dashboard.view') ? 'admin' : 'admin/tickets'));
        }
        return view('auth/login');
    }

    public function authenticate()
    {
        $email = strtolower(trim((string) $this->request->getPost('email')));
        $password = (string) $this->request->getPost('password');
        if (! service('throttler')->check('login-' . hash('sha256', $this->request->getIPAddress() . '|' . $email), 5, 60)) {
            return redirect()->back()->with('error', 'Terlalu banyak percobaan. Coba kembali beberapa saat lagi.');
        }
        $user = (new AuthService())->authenticate(
            $email,
            $password,
            $this->request->getIPAddress(),
            (string) $this->request->getUserAgent(),
        );
        if (! $user) {
            return redirect()->back()->withInput()->with('error', 'Email atau kata sandi tidak valid.');
        }
        session()->regenerate(true);
        session()->set([
            'user_id' => (int) $user['id'],
            'user_name' => $user['name'],
            'user_email' => $user['email'],
            'role_id' => (int) $user['role_id'],
            'role_name' => $user['role_name'],
        ]);
        return redirect()->to(site_url(\App\Services\PermissionService::allows('dashboard.view') ? 'admin' : 'admin/tickets'));
    }

    public function logout()
    {
        session()->destroy();
        return redirect()->to(site_url('login'));
    }
}
