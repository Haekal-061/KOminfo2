<?php

namespace App\Services;

use App\Repositories\DatabaseRepository;

class DashboardService
{
    private DatabaseRepository $db;

    public function __construct(?DatabaseRepository $db = null)
    {
        $this->db = $db ?? new DatabaseRepository();
    }

    public function data(string $from, string $to): array
    {
        $db = $this->db;
        $base = static function () use ($db, $from, $to) {
            return $db->table('tickets')->where('deleted_at', null)
                ->where('created_at >=', $from . ' 00:00:00')
                ->where('created_at <=', $to . ' 23:59:59');
        };
        $start = $from . ' 00:00:00';
        $end = $to . ' 23:59:59';
        $connection = $db->connection();
        $ticketsTable = $connection->prefixTable('tickets') . ' t';
        $ticketJoin = static fn (string $field): string => 't.' . $field . ' = master.id AND t.deleted_at IS NULL'
            . ' AND t.created_at >= ' . $connection->escape($start)
            . ' AND t.created_at <= ' . $connection->escape($end);
        $statuses = $db->table('ticket_statuses master')
            ->select('master.id, master.name, master.code, master.color, COUNT(t.id) AS total', false)
            ->join($ticketsTable, $ticketJoin('status_id'), 'left', false)
            ->groupBy(['master.id', 'master.name', 'master.code', 'master.color', 'master.sort_order'])
            ->orderBy('master.sort_order')->orderBy('master.id')
            ->get()->getResultArray();
        $categories = $db->table('categories master')
            ->select('master.name, master.color, COUNT(t.id) AS total', false)
            ->join($ticketsTable, $ticketJoin('category_id'), 'left', false)
            ->groupBy(['master.id', 'master.name', 'master.color'])
            ->orderBy('total', 'DESC')->orderBy('master.name')
            ->get()->getResultArray();
        $priorities = $db->table('priorities master')
            ->select('master.name, master.color, COUNT(t.id) AS total', false)
            ->join($ticketsTable, $ticketJoin('priority_id'), 'left', false)
            ->groupBy(['master.id', 'master.name', 'master.color', 'master.sort_order'])
            ->orderBy('master.sort_order')->orderBy('master.id')
            ->get()->getResultArray();
        $trend = $base()->select('DATE(created_at) AS day, COUNT(*) AS total', false)
            ->groupBy('DATE(created_at)', false)->orderBy('day')
            ->get()->getResultArray();
        return [
            'total' => $base()->countAllResults(),
            'statuses' => $statuses, 'categories' => $categories, 'priorities' => $priorities, 'trend' => $trend,
        ];
    }
}
