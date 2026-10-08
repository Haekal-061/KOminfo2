<?php

namespace App\Services;

use App\Repositories\DatabaseRepository;

class ReportService
{
    private DatabaseRepository $db;

    public function __construct(?DatabaseRepository $db = null)
    {
        $this->db = $db ?? new DatabaseRepository();
    }

    public function filterOptions(): array
    {
        return [
            'categories' => $this->db->table('categories')->where('is_active', 1)->orderBy('name')->get()->getResultArray(),
            'statuses' => $this->db->table('ticket_statuses')->where('is_active', 1)->orderBy('sort_order')->get()->getResultArray(),
            'priorities' => $this->db->table('priorities')->where('is_active', 1)->orderBy('sort_order')->get()->getResultArray(),
            'users' => $this->db->table('users')->where('is_active', 1)->orderBy('name')->get()->getResultArray(),
        ];
    }

    public function exportCsv(array $filters): string
    {
        $builder = $this->db->table('tickets t')
            ->select('t.ticket_number, t.created_at, e.employee_number, e.name AS reporter, e.whatsapp_number, c.name AS category, sv.name AS service, p.name AS priority, s.name AS status, u.name AS assignee, tm.name AS team, t.subject, t.description')
            ->join('employees e', 'e.id = t.reporter_id')->join('categories c', 'c.id = t.category_id')
            ->join('service_types sv', 'sv.id = t.service_type_id', 'left')->join('priorities p', 'p.id = t.priority_id')
            ->join('ticket_statuses s', 's.id = t.status_id')->join('users u', 'u.id = t.assignee_user_id', 'left')
            ->join('teams tm', 'tm.id = t.team_id', 'left')->where('t.deleted_at', null);
        foreach (['status_id', 'category_id', 'priority_id', 'assignee_user_id'] as $field) {
            if (empty($filters[$field])) {
                continue;
            }
            if (! is_scalar($filters[$field]) || filter_var($filters[$field], FILTER_VALIDATE_INT) === false) {
                throw new \InvalidArgumentException('Filter laporan tidak valid.');
            }
            $builder->where('t.' . $field, (int) $filters[$field]);
        }
        foreach (['from' => '>=', 'to' => '<='] as $field => $operator) {
            if (! empty($filters[$field])) {
                if (! is_string($filters[$field])) {
                    throw new \InvalidArgumentException('Format tanggal laporan tidak valid.');
                }
                $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $filters[$field]);
                if ($date === false || $date->format('Y-m-d') !== $filters[$field]) {
                    throw new \InvalidArgumentException('Format tanggal laporan tidak valid.');
                }
                $builder->where('t.created_at ' . $operator, $date->format('Y-m-d') . ($field === 'from' ? ' 00:00:00' : ' 23:59:59'));
            }
        }
        $rows = $builder->orderBy('t.created_at', 'DESC')->get()->getResultArray();
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new \RuntimeException('Tidak dapat menyiapkan file CSV.');
        }
        fputcsv($stream, array_keys($rows[0] ?? [
            'ticket_number' => '', 'created_at' => '', 'employee_number' => '', 'reporter' => '', 'whatsapp_number' => '',
            'category' => '', 'service' => '', 'priority' => '', 'status' => '', 'assignee' => '', 'team' => '',
            'subject' => '', 'description' => '',
        ]));
        foreach ($rows as $row) {
            fputcsv($stream, array_map(static function ($value) {
                $value = (string) $value;
                return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
            }, array_values($row)));
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        if ($csv === false) {
            throw new \RuntimeException('Tidak dapat membaca hasil ekspor CSV.');
        }
        return "\xEF\xBB\xBF" . $csv;
    }
}
