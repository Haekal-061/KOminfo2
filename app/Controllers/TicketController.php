<?php

namespace App\Controllers;

use App\Services\PermissionService;
use App\Services\TicketService;

class TicketController extends BaseController
{
    public function index()
    {
        if (! PermissionService::allows('ticket.view')) {
            return $this->response->setStatusCode(403)->setBody('Akses tidak diizinkan.');
        }
        return view('admin/tickets/index', ['title' => 'Ticket', 'filters' => $this->filterData()]);
    }

    public function datatable()
    {
        if (! PermissionService::allows('ticket.view')) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Akses tidak diizinkan.']);
        }
        $draw = (int) $this->request->getGet('draw');
        $order = $this->request->getGet('order')[0] ?? [];
        $sortField = $this->request->getGet('columns')[(int) ($order['column'] ?? 1)]['data'] ?? 'created_at';
        $result = (new TicketService())->list(
            max(1, min(100, (int) ($this->request->getGet('length') ?: 10))),
            max(0, (int) $this->request->getGet('start')),
            (string) ($this->request->getGet('search')['value'] ?? ''),
            $this->request->getGet(),
            (string) $sortField,
            (string) ($order['dir'] ?? 'DESC'),
        );
        $result['rows'] = array_map(static function (array $row): array {
            $row['action'] = '<a class="btn btn-sm btn-outline-primary" href="' . site_url('admin/tickets/' . $row['id']) . '">Buka</a>';
            return $row;
        }, $result['rows']);
        return $this->response->setJSON(['draw' => $draw, 'recordsTotal' => $result['total'], 'recordsFiltered' => $result['filtered'], 'data' => $result['rows']]);
    }

    public function create()
    {
        if (! PermissionService::allows('ticket.create')) {
            return $this->response->setStatusCode(403)->setBody('Akses tidak diizinkan.');
        }
        return view('admin/tickets/create', ['title' => 'Buat Ticket', 'formData' => $this->formData()]);
    }

    public function store()
    {
        if (! PermissionService::allows('ticket.create')) {
            return $this->response->setStatusCode(403)->setBody('Akses tidak diizinkan.');
        }
        $rules = ['reporter_id' => 'required|is_natural_no_zero', 'category_id' => 'required|is_natural_no_zero', 'subject' => 'required|max_length[255]', 'description' => 'required|min_length[4]', 'channel' => 'required|in_list[admin,web,api]'];
        if (! service('validation')->setRules($rules)->run($this->request->getPost())) {
            return redirect()->back()->withInput()->with('error', implode(' ', service('validation')->getErrors()));
        }
        try {
            $ticket = (new TicketService())->create($this->request->getPost());
        } catch (\InvalidArgumentException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }
        return redirect()->to(site_url('admin/tickets/' . $ticket['id']))->with('success', 'Ticket berhasil dibuat: ' . $ticket['ticket_number']);
    }

    public function show(int $id)
    {
        if (! PermissionService::allows('ticket.view')) {
            return $this->response->setStatusCode(403)->setBody('Akses tidak diizinkan.');
        }
        $service = new TicketService();
        if (! $service->canView($id)) {
            return $this->response->setStatusCode(404)->setBody('Ticket tidak ditemukan.');
        }
        return view('admin/tickets/show', [
            'title' => 'Detail Ticket', 'ticket' => $service->get($id),
            'activities' => $service->activities($id), 'messages' => $service->messages($id),
            'formData' => array_merge($this->formData(), ['statuses' => $service->availableTransitions($id)]),
            'canUpdate' => PermissionService::allows('ticket.update'),
            'canChangeStatus' => PermissionService::allows('ticket.change_status'),
            'canAssign' => PermissionService::allows('ticket.assign'),
            'canMessage' => PermissionService::allows('ticket.message.create'),
        ]);
    }

    public function update(int $id)
    {
        if (! PermissionService::allows('ticket.update')) {
            return $this->response->setStatusCode(403)->setBody('Akses tidak diizinkan.');
        }
        if (! (new TicketService())->canView($id)) {
            return $this->response->setStatusCode(404)->setBody('Ticket tidak ditemukan.');
        }
        $input = $this->request->getPost();
        if (! service('validation')->setRules(['subject' => 'required|max_length[255]', 'description' => 'required|min_length[4]'])->run($input)) {
            return redirect()->back()->withInput()->with('error', implode(' ', service('validation')->getErrors()));
        }
        try {
            (new TicketService())->update($id, $input);
        } catch (\InvalidArgumentException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }
        return redirect()->back()->with('success', 'Ticket diperbarui.');
    }

    public function status(int $id)
    {
        if (! PermissionService::allows('ticket.change_status')) {
            return $this->response->setStatusCode(403)->setBody('Akses tidak diizinkan.');
        }
        if (! (new TicketService())->canView($id)) {
            return $this->response->setStatusCode(404)->setBody('Ticket tidak ditemukan.');
        }
        $statusId = (int) $this->request->getPost('status_id');
        if ($statusId < 1) {
            return redirect()->back()->with('error', 'Status tujuan tidak valid.');
        }
        try {
            (new TicketService())->changeStatus($id, $statusId);
        } catch (\DomainException|\InvalidArgumentException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }
        return redirect()->back()->with('success', 'Status ticket diperbarui.');
    }

    public function assign(int $id)
    {
        if (! PermissionService::allows('ticket.assign')) {
            return $this->response->setStatusCode(403)->setBody('Akses tidak diizinkan.');
        }
        if (! (new TicketService())->canView($id)) {
            return $this->response->setStatusCode(404)->setBody('Ticket tidak ditemukan.');
        }
        $userId = (int) $this->request->getPost('assignee_user_id') ?: null;
        $teamId = (int) $this->request->getPost('team_id') ?: null;
        try {
            (new TicketService())->assign($id, $userId, $teamId);
        } catch (\InvalidArgumentException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }
        return redirect()->back()->with('success', 'Assignment diperbarui.');
    }

    public function comment(int $id)
    {
        if (! PermissionService::allows('ticket.message.create')) {
            return $this->response->setStatusCode(403)->setBody('Akses tidak diizinkan.');
        }
        if (! (new TicketService())->canView($id)) {
            return $this->response->setStatusCode(404)->setBody('Ticket tidak ditemukan.');
        }
        try {
            (new TicketService())->addComment($id, (string) $this->request->getPost('body'));
        } catch (\InvalidArgumentException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }
        return redirect()->back()->with('success', 'Balasan dimasukkan ke antrean WhatsApp.');
    }

    private function formData(): array
    {
        return (new TicketService())->formData();
    }

    private function filterData(): array
    {
        $data = $this->formData();
        return ['statuses' => $data['statuses'], 'categories' => $data['categories'], 'priorities' => $data['priorities'], 'users' => $data['users']];
    }
}
