<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h4 mb-1">Daftar Ticket</h1><p class="muted mb-0">Cari dan filter tiket penanganan.</p></div><a class="btn btn-primary" href="<?= site_url('admin/tickets/create') ?>"><i class="bi bi-plus-lg me-1"></i>Buat Ticket</a></div>
<div class="card mb-4"><div class="card-body"><form id="ticketFilters" class="row g-2 align-items-end">
<div class="col-sm-6 col-lg-2"><label class="form-label">Status</label><select class="form-select" name="status_id"><option value="">Semua</option><?php foreach ($filters['statuses'] as $row): ?><option value="<?= $row['id'] ?>"><?= esc($row['name']) ?></option><?php endforeach ?></select></div>
<div class="col-sm-6 col-lg-2"><label class="form-label">Kategori</label><select class="form-select" name="category_id"><option value="">Semua</option><?php foreach ($filters['categories'] as $row): ?><option value="<?= $row['id'] ?>"><?= esc($row['name']) ?></option><?php endforeach ?></select></div>
<div class="col-sm-6 col-lg-2"><label class="form-label">Prioritas</label><select class="form-select" name="priority_id"><option value="">Semua</option><?php foreach ($filters['priorities'] as $row): ?><option value="<?= $row['id'] ?>"><?= esc($row['name']) ?></option><?php endforeach ?></select></div>
<div class="col-sm-6 col-lg-2"><label class="form-label">Operator</label><select class="form-select" name="assignee_user_id"><option value="">Semua</option><?php foreach ($filters['users'] as $row): ?><option value="<?= $row['id'] ?>"><?= esc($row['name']) ?></option><?php endforeach ?></select></div>
<div class="col-sm-6 col-lg-2"><label class="form-label">Dari</label><input class="form-control" type="date" name="from"></div><div class="col-sm-6 col-lg-2"><label class="form-label">Sampai</label><input class="form-control" type="date" name="to"></div>
</form></div></div>
<div class="card"><div class="card-body"><div class="table-responsive"><table id="ticketsTable" class="table table-hover align-middle w-100"><thead><tr><th>Ticket</th><th>Dibuat</th><th>Pelapor</th><th>Kategori</th><th>Layanan</th><th>Prioritas</th><th>Status</th><th>Assignee</th><th>Aksi</th></tr></thead></table></div></div></div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?><script>
const escapeCell = value => { if(value===null||value===undefined)return '';const node=document.createElement('span');node.textContent=value;return node.innerHTML; };
const table = new DataTable('#ticketsTable',{processing:true,serverSide:true,ajax:{url:'<?= site_url('admin/tickets/datatables') ?>',data:function(d){for(const [key,value] of new FormData(document.getElementById('ticketFilters'))){if(value)d[key]=value}}},columns:[
{data:'ticket_number',render:escapeCell},{data:'created_at',render:escapeCell},{data:'reporter_name',render:escapeCell},{data:'category_name',render:escapeCell},{data:'service_name',render:escapeCell},
{data:'priority_name',render:(v,t,r)=>`<span class="badge" style="background:${escapeCell(r.priority_color)}">${escapeCell(v)}</span>`},
{data:'status_name',render:(v,t,r)=>`<span class="badge" style="background:${escapeCell(r.status_color)}">${escapeCell(v)}</span>`},
{data:'assignee_name',render:v=>escapeCell(v)||'<span class="text-secondary">Belum di-assign</span>'},
{data:'action',orderable:false,searchable:false,render:v=>v}],order:[[1,'desc']],language:{url:'https://cdn.datatables.net/plug-ins/2.1.8/i18n/id.json'}});
document.querySelectorAll('#ticketFilters select,#ticketFilters input').forEach(el=>el.addEventListener('change',()=>table.ajax.reload()));
</script><?= $this->endSection() ?>
