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
        $filters = $this->request->getGet();
        return view('admin/reports/index', [
            'title' => 'Laporan Ticket',
            'selectedFilters' => $filters,
        ] + (new ReportService())->filterOptions());
    }

    public function datatable()
    {
        if (! PermissionService::allows('report.view')) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Akses tidak diizinkan.']);
        }

        $order = $this->request->getGet('order')[0] ?? [];
        $column = $this->request->getGet('columns')[(int) ($order['column'] ?? 1)]['data'] ?? 'created_at';

        try {
            $result = (new ReportService())->list(
                $this->request->getGet(),
                max(1, min(100, (int) ($this->request->getGet('length') ?: 10))),
                max(0, (int) $this->request->getGet('start')),
                (string) ($this->request->getGet('search')['value'] ?? ''),
                (string) $column,
                (string) ($order['dir'] ?? 'DESC'),
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->response->setStatusCode(400)->setJSON(['error' => $exception->getMessage()]);
        }

        return $this->response->setJSON([
            'draw' => (int) $this->request->getGet('draw'),
            'recordsTotal' => $result['total'],
            'recordsFiltered' => $result['filtered'],
            'data' => $result['rows'],
        ]);
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
