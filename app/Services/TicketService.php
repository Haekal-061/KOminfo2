<?php

namespace App\Services;

use App\Repositories\DatabaseRepository;
use CodeIgniter\Database\BaseConnection;

class TicketService
{
    private DatabaseRepository $db;

    public function __construct(BaseConnection|DatabaseRepository|null $db = null)
    {
        $this->db = $db instanceof DatabaseRepository ? $db : new DatabaseRepository($db);
    }

    public function create(array $data): array
    {
        $channel = $data['channel'] ?? 'admin';
        if (! in_array($channel, ['whatsapp', 'web', 'admin', 'api'], true)) {
            throw new \InvalidArgumentException('Channel ticket tidak valid.');
        }
        $category = $this->db->table('categories')->where(['id' => $data['category_id'], 'is_active' => 1])->get()->getRowArray();
        $initialStatus = $this->db->table('ticket_statuses')->where(['is_initial' => 1, 'is_active' => 1])->orderBy('sort_order')->get()->getRowArray();
        $reporter = $this->db->table('employees')->where(['id' => $data['reporter_id'], 'is_active' => 1])->get()->getRowArray();
        $priority = $this->db->table('priorities')->where(['is_default' => 1, 'is_active' => 1])->get()->getRowArray();
        if (! $reporter) {
            throw new \InvalidArgumentException('Pegawai pelapor tidak valid atau tidak aktif.');
        }
        if (! $category || ! $initialStatus || ! $priority) {
            throw new \RuntimeException('Konfigurasi kategori, status awal, atau prioritas default belum tersedia.');
        }

        $serviceTypeId = $data['service_type_id'] ?? null;
        if ($serviceTypeId) {
            $validService = $this->db->table('service_types')->where([
                'id' => $serviceTypeId, 'category_id' => $category['id'], 'is_active' => 1,
            ])->countAllResults();
            if (! $validService) {
                throw new \InvalidArgumentException('Jenis layanan tidak sesuai dengan kategori.');
            }
        }
        if (! $serviceTypeId) {
            $service = $this->db->table('service_types')->where(['category_id' => $category['id'], 'is_active' => 1])->orderBy('id')->get()->getRowArray();
            $serviceTypeId = $service['id'] ?? null;
        }

        $now = date('Y-m-d H:i:s');
        $ticketDate = date('Y-m-d');
        $this->db->transBegin();
        $this->db->query('INSERT IGNORE INTO ticket_counters (ticket_date, next_number) VALUES (?, 0)', [$ticketDate]);
        $counter = $this->db->query('SELECT next_number FROM ticket_counters WHERE ticket_date = ? FOR UPDATE', [$ticketDate])->getRowArray();
        $number = (int) $counter['next_number'] + 1;
        $this->db->table('ticket_counters')->where('ticket_date', $ticketDate)->update(['next_number' => $number]);
        $ticketNumber = 'TCK-' . date('Ymd') . '-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
        $this->db->table('tickets')->insert([
            'ticket_number' => $ticketNumber,
            'reporter_id' => $data['reporter_id'],
            'channel' => $channel,
            'category_id' => $category['id'],
            'service_type_id' => $serviceTypeId,
            'priority_id' => $priority['id'],
            'status_id' => $initialStatus['id'],
            'subject' => mb_substr(trim($data['subject']), 0, 255),
            'description' => trim($data['description']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $ticketId = (int) $this->db->insertID();
        $this->activity($ticketId, 'created', 'Ticket dibuat melalui ' . $channel . '.', null, ['ticket_number' => $ticketNumber]);
        if (! empty($data['initial_message'])) {
            $this->message($ticketId, $channel, $data['external_message_id'] ?? null, 'in', $data['sender'] ?? null, null, $data['initial_message'], 'received_at');
        }
        if ($this->db->transStatus() === false) {
            $this->db->transRollback();
            throw new \RuntimeException('Ticket gagal disimpan.');
        }
        $this->db->transCommit();
        $ticket = $this->get($ticketId);
        if ($channel === 'whatsapp') {
            $this->queueMessage($data['recipient'], $this->createdMessage($ticket), $ticketId);
        }
        return $ticket;
    }

    public function formData(): array
    {
        return [
            'employees' => $this->db->table('employees')->where('is_active', 1)->orderBy('name')->get()->getResultArray(),
            'categories' => $this->db->table('categories')->where('is_active', 1)->orderBy('name')->get()->getResultArray(),
            'services' => $this->db->table('service_types')->where('is_active', 1)->orderBy('name')->get()->getResultArray(),
            'statuses' => $this->db->table('ticket_statuses')->where('is_active', 1)->orderBy('sort_order')->get()->getResultArray(),
            'priorities' => $this->db->table('priorities')->where('is_active', 1)->orderBy('sort_order')->get()->getResultArray(),
            'users' => $this->db->table('users')->where('is_active', 1)->orderBy('name')->get()->getResultArray(),
            'teams' => $this->db->table('teams')->where('is_active', 1)->orderBy('name')->get()->getResultArray(),
        ];
    }

    public function get(int $id): array
    {
        $ticket = $this->db->table('tickets t')
            ->select('t.*, e.name AS reporter_name, e.whatsapp_number, c.name AS category_name, s.name AS service_name, st.name AS status_name, st.code AS status_code, st.color AS status_color, p.name AS priority_name, p.color AS priority_color, u.name AS assignee_name, tm.name AS team_name')
            ->join('employees e', 'e.id = t.reporter_id')
            ->join('categories c', 'c.id = t.category_id')
            ->join('service_types s', 's.id = t.service_type_id', 'left')
            ->join('ticket_statuses st', 'st.id = t.status_id')
            ->join('priorities p', 'p.id = t.priority_id')
            ->join('users u', 'u.id = t.assignee_user_id', 'left')
            ->join('teams tm', 'tm.id = t.team_id', 'left')
            ->where('t.id', $id)->where('t.deleted_at', null)->get()->getRowArray();
        if (! $ticket) {
            throw new \RuntimeException('Ticket tidak ditemukan.');
        }
        return $ticket;
    }

    public function canView(int $id): bool
    {
        if ($this->isPrivilegedRole()) {
            return $this->db->table('tickets')->where(['id' => $id, 'deleted_at' => null])->countAllResults() > 0;
        }
        $userId = (int) session()->get('user_id');
        if (! $userId) {
            return false;
        }
        $memberTeams = $this->db->table('team_members')->select('team_id')->where('user_id', $userId);
        return $this->db->table('tickets')->where('id', $id)->where('deleted_at', null)
            ->groupStart()->where('assignee_user_id', $userId)->orWhereIn('team_id', $memberTeams)->groupEnd()
            ->countAllResults() > 0;
    }

    public function changeStatus(int $id, int $statusId): array
    {
        $ticket = $this->get($id);
        $target = $this->db->table('ticket_statuses')->where(['id' => $statusId, 'is_active' => 1])->get()->getRowArray();
        if (! $target) {
            throw new \InvalidArgumentException('Status tujuan tidak valid.');
        }
        $transition = $this->db->table('ticket_status_transitions')
            ->where(['from_status_id' => $ticket['status_id'], 'to_status_id' => $statusId])
            ->groupStart()->where('role_id', null)->orWhere('role_id', $this->currentRoleId())->groupEnd()
            ->countAllResults();
        if (! $transition) {
            throw new \DomainException('Perubahan status tidak diizinkan oleh workflow.');
        }
        $now = date('Y-m-d H:i:s');
        $update = ['status_id' => $statusId, 'updated_at' => $now];
        if ((int) $target['is_final'] === 1) {
            $update['resolved_at'] = $now;
            $update['closed_at'] = $now;
        }
        $this->db->table('tickets')->where('id', $id)->update($update);
        $this->activity($id, 'status_changed', 'Status berubah dari ' . $ticket['status_name'] . ' menjadi ' . $target['name'] . '.', ['status_id' => $ticket['status_id']], ['status_id' => $statusId]);
        $ticket = $this->get($id);
        $this->queueMessage($ticket['whatsapp_number'], "Update Ticket {$ticket['ticket_number']}\nStatus: {$ticket['status_name']}", $id);
        return $ticket;
    }

    public function assign(int $id, ?int $userId, ?int $teamId): array
    {
        $ticket = $this->get($id);
        if ($userId && ! $this->db->table('users')->where(['id' => $userId, 'is_active' => 1])->countAllResults()) {
            throw new \InvalidArgumentException('Pengguna penanggung jawab tidak valid.');
        }
        if ($teamId && ! $this->db->table('teams')->where(['id' => $teamId, 'is_active' => 1])->countAllResults()) {
            throw new \InvalidArgumentException('Tim penanggung jawab tidak valid.');
        }
        $this->db->table('tickets')->where('id', $id)->update([
            'assignee_user_id' => $userId,
            'team_id' => $teamId,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->activity($id, 'assigned', 'Penanggung jawab ticket diperbarui.', ['user_id' => $ticket['assignee_user_id'], 'team_id' => $ticket['team_id']], ['user_id' => $userId, 'team_id' => $teamId]);
        return $this->get($id);
    }

    public function update(int $id, array $input): array
    {
        $ticket = $this->get($id);
        $data = [];
        foreach (['subject', 'description', 'category_id', 'service_type_id', 'priority_id', 'resolution'] as $field) {
            if (array_key_exists($field, $input)) {
                $value = trim((string) $input[$field]);
                $data[$field] = $value === '' && $field === 'service_type_id' ? null : $value;
            }
        }
        if (isset($data['category_id']) && ! $this->db->table('categories')->where(['id' => $data['category_id'], 'is_active' => 1])->countAllResults()) {
            throw new \InvalidArgumentException('Kategori tidak valid.');
        }
        if (isset($data['priority_id']) && ! $this->db->table('priorities')->where(['id' => $data['priority_id'], 'is_active' => 1])->countAllResults()) {
            throw new \InvalidArgumentException('Prioritas tidak valid.');
        }
        if (isset($data['service_type_id']) && ! $this->db->table('service_types')->where(['id' => $data['service_type_id'], 'is_active' => 1])->countAllResults()) {
            throw new \InvalidArgumentException('Jenis layanan tidak valid.');
        }
        if ($data !== []) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            $this->db->table('tickets')->where('id', $id)->update($data);
            $this->activity($id, 'updated', 'Data ticket diperbarui.', $ticket, $data);
            if (array_key_exists('priority_id', $data)) {
                $this->activity($id, 'priority_changed', 'Prioritas ticket diperbarui.', ['priority_id' => $ticket['priority_id']], ['priority_id' => $data['priority_id']]);
            }
        }
        return $this->get($id);
    }

    public function addComment(int $id, string $body): void
    {
        $ticket = $this->get($id);
        if (trim($body) === '') {
            throw new \InvalidArgumentException('Komentar tidak boleh kosong.');
        }
        $this->queueMessage($ticket['whatsapp_number'], "Pesan terkait ticket {$ticket['ticket_number']}:\n" . trim($body), $id);
    }

    public function list(int $limit, int $offset, string $search, array $filters = [], string $sortField = 'created_at', string $sortDirection = 'DESC'): array
    {
        $allowedSorts = [
            'ticket_number' => 't.ticket_number', 'created_at' => 't.created_at', 'reporter_name' => 'e.name',
            'category_name' => 'c.name', 'service_name' => 'sv.name', 'priority_name' => 'p.name',
            'status_name' => 'st.name', 'assignee_name' => 'u.name',
        ];
        $sortField = $allowedSorts[$sortField] ?? 't.created_at';
        $sortDirection = strtoupper($sortDirection) === 'ASC' ? 'ASC' : 'DESC';
        $totalBuilder = $this->db->table('tickets t')->where('t.deleted_at', null);
        $base = $this->db->table('tickets t')
            ->join('employees e', 'e.id = t.reporter_id')
            ->join('categories c', 'c.id = t.category_id')
            ->join('service_types sv', 'sv.id = t.service_type_id', 'left')
            ->join('ticket_statuses st', 'st.id = t.status_id')
            ->join('priorities p', 'p.id = t.priority_id')
            ->join('users u', 'u.id = t.assignee_user_id', 'left')
            ->where('t.deleted_at', null);
        $this->applyVisibility($base);
        $this->applyVisibility($totalBuilder);
        $this->applyFilters($base, $filters);
        if ($search !== '') {
            $base->groupStart()->like('t.ticket_number', $search)->orLike('t.subject', $search)
                ->orLike('t.description', $search)->orLike('e.name', $search)
                ->orLike('e.whatsapp_number', $search)->orLike('c.name', $search)
                ->orLike('st.name', $search)->orLike('p.name', $search)->orLike('u.name', $search)->groupEnd();
        }
        $filtered = $base->countAllResults(false);
        $rows = $base->select('t.id, t.ticket_number, t.subject, t.created_at, e.name AS reporter_name, c.name AS category_name, sv.name AS service_name, st.name AS status_name, st.color AS status_color, st.code AS status_code, p.name AS priority_name, p.color AS priority_color, u.name AS assignee_name')
            ->orderBy($sortField, $sortDirection)->limit($limit, $offset)->get()->getResultArray();
        return ['rows' => $rows, 'filtered' => $filtered, 'total' => $totalBuilder->countAllResults()];
    }

    public function activities(int $id): array
    {
        return $this->db->table('ticket_activities a')->select('a.*, u.name AS user_name')->join('users u', 'u.id = a.user_id', 'left')->where('a.ticket_id', $id)->orderBy('a.created_at', 'DESC')->get()->getResultArray();
    }

    public function availableTransitions(int $id): array
    {
        $ticket = $this->get($id);
        return $this->db->table('ticket_status_transitions tr')
            ->select('s.id, s.name, s.color, s.code')
            ->join('ticket_statuses s', 's.id = tr.to_status_id')
            ->where('tr.from_status_id', $ticket['status_id'])->where('s.is_active', 1)
            ->groupStart()->where('tr.role_id', null)->orWhere('tr.role_id', $this->currentRoleId())->groupEnd()
            ->orderBy('s.sort_order')->get()->getResultArray();
    }

    public function messages(int $id): array
    {
        return $this->db->table('ticket_messages')->where('ticket_id', $id)->orderBy('created_at', 'ASC')->get()->getResultArray();
    }

    public function queueMessage(string $recipient, string $body, ?int $ticketId = null): void
    {
        $this->db->table('notification_queue')->insert([
            'channel' => 'whatsapp', 'recipient' => $recipient, 'body' => $body, 'ticket_id' => $ticketId,
            'status' => 'pending', 'attempt_count' => 0, 'available_at' => date('Y-m-d H:i:s'), 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $queueId = (int) $this->db->insertID();
        if ($ticketId) {
            $messageId = $this->message($ticketId, 'whatsapp', null, 'out', null, $recipient, $body, 'sent_at');
            $this->db->table('notification_queue')->where('id', $queueId)->update(['message_id' => $messageId]);
        }
    }

    public function message(int $ticketId, string $channel, ?string $externalId, string $direction, ?string $sender, ?string $recipient, string $body, string $timeField): int
    {
        $row = [
            'ticket_id' => $ticketId, 'channel' => $channel, 'external_message_id' => $externalId, 'direction' => $direction,
            'sender' => $sender, 'recipient' => $recipient, 'message_type' => 'text',
            'delivery_status' => $direction === 'in' ? 'received' : 'queued',
            'body' => $body, 'created_at' => date('Y-m-d H:i:s'),
        ];
        if ($direction === 'in') {
            $row[$timeField] = date('Y-m-d H:i:s');
        }
        $this->db->table('ticket_messages')->insert($row);
        if ($direction === 'out' && $channel === 'admin') {
            $this->db->table('tickets')->where('id', $ticketId)->where('first_response_at', null)->update(['first_response_at' => date('Y-m-d H:i:s')]);
        }
        $messageId = (int) $this->db->insertID();
        $this->activity(
            $ticketId,
            $direction === 'in' ? 'message_received' : 'message_queued',
            $direction === 'in' ? 'Pesan masuk diterima.' : 'Pesan keluar masuk ke antrean.',
            null,
            ['message_id' => $messageId],
        );
        return $messageId;
    }

    public function activity(int $ticketId, string $type, string $description, ?array $old, ?array $new): void
    {
        $this->db->table('ticket_activities')->insert([
            'ticket_id' => $ticketId, 'user_id' => session()->get('user_id'),
            'activity_type' => $type, 'description' => $description,
            'metadata' => json_encode(['old' => $old, 'new' => $new], JSON_THROW_ON_ERROR),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function applyFilters($builder, array $filters): void
    {
        foreach (['status_id', 'category_id', 'priority_id', 'assignee_user_id'] as $field) {
            if (! empty($filters[$field])) {
                $builder->where('t.' . $field, (int) $filters[$field]);
            }
        }
        if (! empty($filters['from'])) {
            $builder->where('t.created_at >=', $filters['from'] . ' 00:00:00');
        }
        if (! empty($filters['to'])) {
            $builder->where('t.created_at <=', $filters['to'] . ' 23:59:59');
        }
    }

    private function applyVisibility($builder): void
    {
        if ($this->isPrivilegedRole()) {
            return;
        }
        $userId = (int) session()->get('user_id');
        $memberTeams = $this->db->table('team_members')->select('team_id')->where('user_id', $userId);
        $builder->groupStart()->where('t.assignee_user_id', $userId)->orWhereIn('t.team_id', $memberTeams)->groupEnd();
    }

    private function isPrivilegedRole(): bool
    {
        $role = $this->db->table('users u')->select('r.name')
            ->join('roles r', 'r.id = u.role_id')
            ->where('u.id', session()->get('user_id'))->where('u.is_active', 1)
            ->get()->getRowArray();
        return in_array($role['name'] ?? '', ['super_admin', 'admin', 'supervisor'], true);
    }

    private function currentRoleId(): ?int
    {
        $user = $this->db->table('users')->select('role_id')
            ->where(['id' => session()->get('user_id'), 'is_active' => 1])->get()->getRowArray();
        return $user ? (int) $user['role_id'] : null;
    }

    private function createdMessage(array $ticket): string
    {
        return "Aduan Anda telah diterima.\n\nNomor Ticket: {$ticket['ticket_number']}\nKategori: {$ticket['category_name']}\nStatus: {$ticket['status_name']}\n\nTim KOMINFO akan menindaklanjuti aduan Anda.";
    }
}
