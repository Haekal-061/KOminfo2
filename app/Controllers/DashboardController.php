<?php

namespace App\Controllers;

use App\Services\DashboardService;
use App\Services\PermissionService;

class DashboardController extends BaseController
{
    public function index()
    {
        if (! PermissionService::allows('dashboard.view')) {
            return redirect()->to(site_url('admin/tickets'));
        }
        $to = $this->request->getGet('to') ?: date('Y-m-d');
        $from = $this->request->getGet('from') ?: date('Y-m-d', strtotime('-13 days'));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || $from > $to) {
            return redirect()->to(site_url('admin'))->with('error', 'Rentang tanggal tidak valid.');
        }
        return view('admin/dashboard', [
            'title' => 'Dashboard', 'from' => $from, 'to' => $to,
            'metrics' => (new DashboardService())->data($from, $to),
        ]);
    }
}
