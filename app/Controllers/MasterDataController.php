<?php

namespace App\Controllers;

use App\Services\MasterDataService;
use App\Services\PermissionService;

class MasterDataController extends BaseController
{
    public function index(string $resource)
    {
        $service = new MasterDataService();
        $definition = $service->definition($resource);
        if (! PermissionService::allows($definition['permission'])) {
            return $this->response->setStatusCode(403)->setBody('Akses tidak diizinkan.');
        }
        $editId = (int) ($this->request->getGet('edit') ?? 0);
        $formOptions = $service->formOptions($resource, $editId);
        return view('admin/master/index', [
            'title' => $definition['label'], 'resource' => $resource, 'definition' => $definition,
            'editRow' => $editId ? $service->find($resource, $editId) : null,
        ] + $formOptions);
    }

    public function datatable(string $resource)
    {
        $service = new MasterDataService();
        $definition = $service->definition($resource);
        if (! PermissionService::allows($definition['permission'])) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Akses tidak diizinkan.']);
        }
        $draw = (int) $this->request->getGet('draw');
        $start = max(0, (int) $this->request->getGet('start'));
        $length = max(1, min(100, (int) ($this->request->getGet('length') ?: 10)));
        $search = (string) ($this->request->getGet('search')['value'] ?? '');
        $order = $this->request->getGet('order')[0] ?? [];
        $columnName = $this->request->getGet('columns')[(int) ($order['column'] ?? 0)]['data'] ?? 'id';
        $result = $service->list($resource, $length, $start, $search, (string) $columnName, (string) ($order['dir'] ?? 'DESC'));
        return $this->response->setJSON([
            'draw' => $draw, 'recordsTotal' => $result['total'], 'recordsFiltered' => $result['filtered'],
            'data' => array_map(static function (array $row) use ($resource): array {
                $row['actions'] = '<a class="btn btn-sm btn-outline-primary" href="' . site_url('admin/master/' . rawurlencode($resource) . '?edit=' . $row['id']) . '">Ubah</a>';
                return $row;
            }, $result['rows']),
        ]);
    }

    public function save(string $resource)
    {
        $service = new MasterDataService();
        $definition = $service->definition($resource);
        if (! PermissionService::allows($definition['permission'])) {
            return $this->response->setStatusCode(403)->setBody('Akses tidak diizinkan.');
        }
        $id = (int) ($this->request->getPost('id') ?: 0);
        $input = $this->request->getPost();
        $rules = [];
        if (in_array($resource, ['categories', 'teams', 'priorities'], true)) {
            $rules['name'] = 'required|max_length[120]';
        }
        if ($resource === 'employees') {
            $rules = ['employee_number' => 'required|max_length[80]', 'name' => 'required|max_length[150]', 'whatsapp_number' => 'required|max_length[30]'];
        }
        if ($resource === 'categories') {
            $rules['code'] = 'required|alpha_numeric_punct|max_length[60]';
        }
        if ($resource === 'services') {
            $rules = ['category_id' => 'required|is_natural_no_zero', 'name' => 'required|max_length[120]'];
        }
        if ($resource === 'statuses') {
            $rules = ['code' => 'required|alpha_numeric_punct|max_length[60]', 'name' => 'required|max_length[100]'];
        }
        if ($resource === 'priorities') {
            $rules['code'] = 'required|alpha_numeric_punct|max_length[40]';
        }
        if ($resource === 'users') {
            $rules = ['name' => 'required|max_length[150]', 'email' => 'required|valid_email|max_length[190]', 'role_id' => 'required|is_natural_no_zero'];
            if (! $id) {
                $rules['password'] = 'required|min_length[12]';
            }
        }
        if (in_array($resource, ['categories', 'statuses', 'priorities'], true)) {
            $rules['color'] = 'required|regex_match[/^#[0-9A-Fa-f]{6}$/]';
        }
        if ($resource === 'employees' && ! empty($input['email'])) {
            $rules['email'] = 'valid_email|max_length[190]';
        }
        if ($rules !== [] && ! service('validation')->setRules($rules)->run($input)) {
            return redirect()->back()->withInput()->with('error', implode(' ', service('validation')->getErrors()));
        }
        if ($resource === 'users' && ! empty($input['password']) && strlen((string) $input['password']) < 12) {
            return redirect()->back()->withInput()->with('error', 'Password harus minimal 12 karakter.');
        }
        try {
            $service->save($resource, $input, $id ?: null);
        } catch (\InvalidArgumentException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }
        return redirect()->to(site_url('admin/master/' . rawurlencode($resource)))->with('success', $definition['label'] . ' berhasil disimpan.');
    }
}
