<?php

namespace App\Services;

use App\Repositories\DatabaseRepository;
use CodeIgniter\Database\BaseConnection;

class MasterDataService
{
    public const RESOURCES = [
        'employees' => ['table' => 'employees', 'permission' => 'master.employee.manage', 'label' => 'Pegawai', 'fields' => ['employee_number', 'name', 'whatsapp_number', 'email', 'department', 'position', 'is_active']],
        'categories' => ['table' => 'categories', 'permission' => 'master.category.manage', 'label' => 'Kategori', 'fields' => ['name', 'code', 'description', 'color', 'is_active']],
        'services' => ['table' => 'service_types', 'permission' => 'master.service.manage', 'label' => 'Jenis Layanan', 'fields' => ['category_id', 'name', 'description', 'is_active']],
        'statuses' => ['table' => 'ticket_statuses', 'permission' => 'master.status.manage', 'label' => 'Status', 'fields' => ['code', 'name', 'description', 'color', 'is_initial', 'is_final', 'sort_order', 'is_active']],
        'priorities' => ['table' => 'priorities', 'permission' => 'master.priority.manage', 'label' => 'Prioritas', 'fields' => ['name', 'code', 'color', 'sort_order', 'is_default', 'is_active']],
        'teams' => ['table' => 'teams', 'permission' => 'master.team.manage', 'label' => 'Tim', 'fields' => ['name', 'description', 'is_active']],
        'users' => ['table' => 'users', 'permission' => 'user.manage', 'label' => 'Pengguna', 'fields' => ['name', 'email', 'role_id', 'is_active']],
    ];

    private DatabaseRepository $db;

    public function __construct(BaseConnection|DatabaseRepository|null $db = null)
    {
        $this->db = $db instanceof DatabaseRepository ? $db : new DatabaseRepository($db);
    }

    public function definition(string $resource): array
    {
        if (! isset(self::RESOURCES[$resource])) {
            throw new \InvalidArgumentException('Master data tidak dikenal.');
        }
        return self::RESOURCES[$resource];
    }

    public function formOptions(string $resource, int $editId = 0): array
    {
        return [
            'categories' => $this->db->table('categories')->where('is_active', 1)->orderBy('name')->get()->getResultArray(),
            'roles' => $this->db->table('roles')->orderBy('label')->get()->getResultArray(),
            'users' => $this->db->table('users')->where('is_active', 1)->orderBy('name')->get()->getResultArray(),
            'selectedTeamMembers' => $resource === 'teams' && $editId
                ? array_column($this->db->table('team_members')->where('team_id', $editId)->get()->getResultArray(), 'user_id')
                : [],
        ];
    }

    public function list(string $resource, int $limit, int $offset, string $search = '', string $sortField = 'id', string $sortDirection = 'DESC'): array
    {
        $definition = $this->definition($resource);
        if (! in_array($sortField, array_merge(['id'], $definition['fields']), true)) {
            $sortField = 'id';
        }
        $sortDirection = strtoupper($sortDirection) === 'ASC' ? 'ASC' : 'DESC';
        $builder = $this->db->table($definition['table'])->select('id, ' . implode(', ', $definition['fields']));
        if ($search !== '') {
            $builder->groupStart();
            foreach ($definition['fields'] as $index => $field) {
                if (in_array($field, ['is_active', 'is_initial', 'is_final', 'sort_order', 'category_id', 'role_id'], true)) {
                    continue;
                }
                $index === 0 ? $builder->like($field, $search) : $builder->orLike($field, $search);
            }
            $builder->groupEnd();
        }
        $filtered = $builder->countAllResults(false);
        $rows = $builder->orderBy($sortField, $sortDirection)->limit($limit, $offset)->get()->getResultArray();
        return ['rows' => $rows, 'filtered' => $filtered, 'total' => $this->db->table($definition['table'])->countAllResults()];
    }

    public function save(string $resource, array $input, ?int $id = null): int
    {
        $definition = $this->definition($resource);
        $data = [];
        foreach ($definition['fields'] as $field) {
            if (in_array($field, ['is_active', 'is_initial', 'is_final', 'is_default'], true)) {
                $data[$field] = isset($input[$field]) ? 1 : 0;
            } elseif (array_key_exists($field, $input)) {
                $data[$field] = trim((string) $input[$field]) === '' ? null : trim((string) $input[$field]);
            }
        }
        if ($resource === 'employees' && isset($data['whatsapp_number'])) {
            $data['whatsapp_normalized'] = PhoneNumberService::normalize($data['whatsapp_number']);
        }
        if ($resource === 'services' && ! $this->db->table('categories')->where(['id' => $data['category_id'] ?? 0, 'is_active' => 1])->countAllResults()) {
            throw new \InvalidArgumentException('Kategori layanan tidak valid atau tidak aktif.');
        }
        if ($resource === 'users' && ! $this->db->table('roles')->where('id', $data['role_id'] ?? 0)->countAllResults()) {
            throw new \InvalidArgumentException('Role pengguna tidak valid.');
        }
        if ($resource === 'users' && ! empty($input['password'])) {
            if (strlen((string) $input['password']) < 12) {
                throw new \InvalidArgumentException('Password harus minimal 12 karakter.');
            }
            $data['password_hash'] = password_hash((string) $input['password'], PASSWORD_DEFAULT);
        }
        $syncMembers = $resource === 'teams' && isset($input['members_submitted']);
        $setPriorityDefault = $resource === 'priorities' && ($data['is_default'] ?? 0) === 1;
        $setInitialStatus = $resource === 'statuses' && ($data['is_initial'] ?? 0) === 1;
        foreach ([
            ['resource' => 'priorities', 'flag' => 'is_default', 'active' => 'is_active', 'label' => 'prioritas default'],
            ['resource' => 'statuses', 'flag' => 'is_initial', 'active' => 'is_active', 'label' => 'status awal'],
        ] as $configuration) {
            if ($resource !== $configuration['resource'] || ! $id) {
                continue;
            }
            $current = $this->db->table($definition['table'])->where('id', $id)->get()->getRowArray();
            if (! $current || (int) $current[$configuration['flag']] !== 1) {
                continue;
            }
            $removingCurrent = ($data[$configuration['flag']] ?? 0) !== 1 || ($data[$configuration['active']] ?? 1) !== 1;
            if ($removingCurrent) {
                $replacement = $this->db->table($definition['table'])
                    ->where($configuration['flag'], 1)->where($configuration['active'], 1)->where('id !=', $id)->countAllResults();
                if (! $replacement) {
                    throw new \InvalidArgumentException('Tetapkan ' . $configuration['label'] . ' pengganti yang aktif sebelum menonaktifkan ini.');
                }
            }
        }
        if (($setPriorityDefault && ($data['is_active'] ?? 1) !== 1) || ($setInitialStatus && ($data['is_active'] ?? 1) !== 1)) {
            throw new \InvalidArgumentException('Konfigurasi default harus aktif.');
        }
        $defaultPriority = $setPriorityDefault;
        $initialStatus = $setInitialStatus;
        if ($syncMembers) {
            $memberIds = array_values(array_unique(array_filter(array_map('intval', (array) ($input['member_ids'] ?? [])), static fn (int $id): bool => $id > 0)));
            if ($memberIds !== [] && $this->db->table('users')->whereIn('id', $memberIds)->where('is_active', 1)->countAllResults() !== count($memberIds)) {
                throw new \InvalidArgumentException('Anggota tim harus berupa pengguna aktif.');
            }
        }
        $transactionStarted = $syncMembers || $defaultPriority || $initialStatus;
        if ($transactionStarted) {
            $this->db->transBegin();
        }
        if ($defaultPriority) {
            $priorityQuery = $this->db->table('priorities')->where('is_default', 1);
            if ($id) {
                $priorityQuery->where('id !=', $id);
            }
            $priorityQuery->update(['is_default' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
        }
        if ($initialStatus) {
            $statusQuery = $this->db->table('ticket_statuses')->where('is_initial', 1);
            if ($id) {
                $statusQuery->where('id !=', $id);
            }
            $statusQuery->update(['is_initial' => 0]);
        }
        $data['updated_at'] = date('Y-m-d H:i:s');
        if ($id) {
            if ($data !== ['updated_at' => $data['updated_at']]) {
                $this->db->table($definition['table'])->where('id', $id)->update($data);
                $this->audit('updated', $definition['table'], $id, $data);
            }
            $savedId = $id;
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $this->db->table($definition['table'])->insert($data);
            $savedId = (int) $this->db->insertID();
            $this->audit('created', $definition['table'], $savedId, $data);
        }
        if ($syncMembers) {
            $this->db->table('team_members')->where('team_id', $savedId)->delete();
            foreach ($memberIds as $memberId) {
                $this->db->table('team_members')->insert(['team_id' => $savedId, 'user_id' => $memberId]);
            }
            if ($this->db->transStatus() === false) {
                $this->db->transRollback();
                throw new \RuntimeException('Tim atau anggota tim gagal disimpan.');
            }
        }
        if ($transactionStarted) {
            if ($this->db->transStatus() === false) {
                $this->db->transRollback();
                throw new \RuntimeException('Data master gagal disimpan.');
            }
            $this->db->transCommit();
        }
        return $savedId;
    }

    public function find(string $resource, int $id): ?array
    {
        return $this->db->table($this->definition($resource)['table'])->where('id', $id)->get()->getRowArray() ?: null;
    }

    private function audit(string $action, string $entity, int $id, array $values): void
    {
        $this->db->table('audit_logs')->insert([
            'user_id' => session()->get('user_id'),
            'action' => $action,
            'entity_type' => $entity,
            'entity_id' => $id,
            'new_values' => json_encode($values, JSON_THROW_ON_ERROR),
            'ip_address' => service('request')->getIPAddress(),
            'user_agent' => substr((string) service('request')->getUserAgent(), 0, 255),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
