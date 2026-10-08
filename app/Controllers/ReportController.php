<?php

namespace App\Controllers;

use App\Services\PermissionService;
use App\Services\ReportService;

class ReportController extends BaseController
{
    public function index()
    {
        if (! PermissionService::allows('report.view')) {
            return $this->response->setStatusCode(403)->setBody('Akses tidak diizinkan.');
        }
        return view('admin/reports/index', [
            'title' => 'Laporan Ticket',
        ] + (new ReportService())->filterOptions());
    }

    public function export()
    {
        if (! PermissionService::allows('report.export')) {
            return $this->response->setStatusCode(403)->setBody('Akses tidak diizinkan.');
        }
        try {
            $csv = (new ReportService())->exportCsv($this->request->getGet());
        } catch (\InvalidArgumentException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }
        return $this->response->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="ticket-report-' . date('Ymd-His') . '.csv"')
            ->setBody($csv);
    }
}
