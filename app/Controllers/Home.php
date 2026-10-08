<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index()
    {
        if (session()->get('user_id')) {
            return redirect()->to(site_url(\App\Services\PermissionService::allows('dashboard.view') ? 'admin' : 'admin/tickets'));
        }
        return redirect()->to(site_url('login'));
    }
}
