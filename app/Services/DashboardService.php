<?php

namespace App\Services;

use App\Repositories\DatabaseRepository;

class DashboardService
{
    public function data(string $from, string $to): array
    {
        $db = new DatabaseRepository();
        $base = static function () use ($db, $from, $to) {
            return $db->table('tickets')->where('deleted_at', null)
                ->where('created_at >=', $from . ' 00:00:00')
                ->where('created_at <=', $to . ' 23:59:59');
        };
        $statuses = $db->table('tickets t')->select('s.id, s.name, s.code, s.color, COUNT(t.id) AS total')
            ->join('ticket_statuses s', 's.id = t.status_id')->where('t.deleted_at', null)
            ->where('t.created_at >=', $from . ' 00:00:00')->where('t.created_at <=', $to . ' 23:59:59')
            ->groupBy('s.id')->orderBy('s.sort_order')->get()->getResultArray();
        $categories = $db->table('tickets t')->select('c.name, c.color, COUNT(t.id) AS total')
            ->join('categories c', 'c.id = t.category_id')->where('t.deleted_at', null)
            ->where('t.created_at >=', $from . ' 00:00:00')->where('t.created_at <=', $to . ' 23:59:59')
            ->groupBy('c.id')->orderBy('total', 'DESC')->get()->getResultArray();
        $priorities = $db->table('tickets t')->select('p.name, p.color, COUNT(t.id) AS total')
            ->join('priorities p', 'p.id = t.priority_id')->where('t.deleted_at', null)
            ->where('t.created_at >=', $from . ' 00:00:00')->where('t.created_at <=', $to . ' 23:59:59')
            ->groupBy('p.id')->orderBy('p.sort_order')->get()->getResultArray();
        $trend = $db->query(
            'SELECT DATE(created_at) AS day, COUNT(*) AS total FROM tickets WHERE deleted_at IS NULL AND created_at >= ? AND created_at <= ? GROUP BY DATE(created_at) ORDER BY day',
            [$from . ' 00:00:00', $to . ' 23:59:59'],
        )->getResultArray();
        return [
            'total' => $base()->countAllResults(),
            'statuses' => $statuses, 'categories' => $categories, 'priorities' => $priorities, 'trend' => $trend,
        ];
    }
}
