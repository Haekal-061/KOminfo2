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

    public function list(array $filters, int $limit, int $offset, string $search = '', string $sortField = 'created_at', string $sortDirection = 'DESC'): array
    {
        $allowedSorts = [
            'ticket_number' => 't.ticket_number',
            'created_at' => 't.created_at',
            'reporter' => 'e.name',
            'category' => 'c.name',
            'service' => 'sv.name',
            'priority' => 'p.name',
            'status' => 's.name',
            'assignee' => 'u.name',
            'team' => 'tm.name',
            'subject' => 't.subject',
        ];
        $sortField = $allowedSorts[$sortField] ?? 't.created_at';
        $sortDirection = strtoupper($sortDirection) === 'ASC' ? 'ASC' : 'DESC';

        $total = $this->queryBuilder()->countAllResults();
        $builder = $this->queryBuilder($filters);
        if ($search !== '') {
            $builder->groupStart()
                ->like('t.ticket_number', $search)->orLike('e.name', $search)
                ->orLike('e.employee_number', $search)->orLike('c.name', $search)
                ->orLike('sv.name', $search)->orLike('p.name', $search)
                ->orLike('s.name', $search)->orLike('u.name', $search)
                ->orLike('tm.name', $search)->orLike('t.subject', $search)
                ->orLike('t.description', $search)
                ->groupEnd();
        }

        $filtered = $builder->countAllResults(false);
        $rows = $builder->select('t.ticket_number, t.created_at, e.employee_number, e.name AS reporter, e.whatsapp_number, c.name AS category, sv.name AS service, p.name AS priority, s.name AS status, u.name AS assignee, tm.name AS team, t.subject, t.description')
            ->orderBy($sortField, $sortDirection)
            ->limit(max(1, min(100, $limit)), max(0, $offset))
            ->get()->getResultArray();

        return ['rows' => $rows, 'filtered' => $filtered, 'total' => $total];
    }

    public function exportCsv(array $filters): string
    {
        $builder = $this->queryBuilder($filters)
            ->select('t.ticket_number, t.created_at, e.employee_number, e.name AS reporter, e.whatsapp_number, c.name AS category, sv.name AS service, p.name AS priority, s.name AS status, u.name AS assignee, tm.name AS team, t.subject, t.description');
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

    private function queryBuilder(array $filters = [])
    {
        $builder = $this->db->table('tickets t')
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
        return $builder;
    }
}
