<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<div class="mb-4"><h1 class="h4 mb-1">Laporan Ticket</h1><p class="muted mb-0">Ekspor data ticket sebagai CSV yang dapat dibuka dengan Excel.</p></div>
<div class="card mb-4"><div class="card-body"><form id="reportFilters" method="get" action="<?= site_url('admin/reports') ?>" class="row g-2 align-items-end">
<div class="col-sm-6 col-lg-2"><label class="form-label" for="reportStatus">Status</label><select id="reportStatus" class="form-select" name="status_id"><option value="">Semua</option><?php foreach ($statuses as $row): ?><option value="<?= $row['id'] ?>" <?= (string) ($selectedFilters['status_id'] ?? '') === (string) $row['id'] ? 'selected' : '' ?>><?= esc($row['name']) ?></option><?php endforeach ?></select></div>
<div class="col-sm-6 col-lg-2"><label class="form-label" for="reportCategory">Kategori</label><select id="reportCategory" class="form-select" name="category_id"><option value="">Semua</option><?php foreach ($categories as $row): ?><option value="<?= $row['id'] ?>" <?= (string) ($selectedFilters['category_id'] ?? '') === (string) $row['id'] ? 'selected' : '' ?>><?= esc($row['name']) ?></option><?php endforeach ?></select></div>
<div class="col-sm-6 col-lg-2"><label class="form-label" for="reportPriority">Prioritas</label><select id="reportPriority" class="form-select" name="priority_id"><option value="">Semua</option><?php foreach ($priorities as $row): ?><option value="<?= $row['id'] ?>" <?= (string) ($selectedFilters['priority_id'] ?? '') === (string) $row['id'] ? 'selected' : '' ?>><?= esc($row['name']) ?></option><?php endforeach ?></select></div>
<div class="col-sm-6 col-lg-2"><label class="form-label" for="reportAssignee">Operator</label><select id="reportAssignee" class="form-select" name="assignee_user_id"><option value="">Semua</option><?php foreach ($users as $row): ?><option value="<?= $row['id'] ?>" <?= (string) ($selectedFilters['assignee_user_id'] ?? '') === (string) $row['id'] ? 'selected' : '' ?>><?= esc($row['name']) ?></option><?php endforeach ?></select></div>
<div class="col-sm-6 col-lg-2"><label class="form-label" for="reportFrom">Dari</label><input id="reportFrom" class="form-control" type="date" name="from" value="<?= esc($selectedFilters['from'] ?? '') ?>"></div>
<div class="col-sm-6 col-lg-2"><label class="form-label" for="reportTo">Sampai</label><input id="reportTo" class="form-control" type="date" name="to" value="<?= esc($selectedFilters['to'] ?? '') ?>"></div>
<input id="reportExportSearch" type="hidden" name="keyword" value="<?= esc($selectedFilters['keyword'] ?? '') ?>">
</form></div></div>
<div class="card"><div class="card-body">
<div class="row g-3 align-items-center mb-3">
<div class="col-md-4"><label class="d-flex align-items-center gap-2 mb-0"><span>Tampilkan</span><select id="reportPageLength" class="form-select form-select-sm" style="width:auto"><option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option></select><span>entri</span></label></div>
<div class="col-md-4 d-flex justify-content-center"><input id="reportSearch" class="form-control form-control-sm" type="search" placeholder="Cari laporan..." aria-label="Cari laporan" value="<?= esc($selectedFilters['keyword'] ?? '') ?>"></div>
<div class="col-md-4 d-flex justify-content-md-end"><button class="btn btn-primary btn-sm" type="submit" form="reportFilters" formaction="<?= site_url('admin/reports/tickets.csv') ?>"><i class="bi bi-download me-1"></i>Unduh CSV</button></div>
</div>
<div class="table-responsive"><table id="reportTable" class="table table-hover align-middle w-100"><thead><tr><th>No. Ticket</th><th>Dibuat</th><th>Pelapor</th><th>Kategori</th><th>Jenis Layanan</th><th>Prioritas</th><th>Status</th><th>Operator</th><th>Tim</th><th>Subjek</th></tr></thead></table></div></div></div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?><script>
const escapeReportCell = value => { if(value===null||value===undefined)return '';const node=document.createElement('span');node.textContent=value;return node.innerHTML; };
const reportTable = new DataTable('#reportTable',{processing:true,serverSide:true,lengthChange:false,layout:{topStart:null,topEnd:null,bottomStart:'info',bottomEnd:'paging'},ajax:{url:'<?= site_url('admin/reports/datatables') ?>',data:function(d){for(const [key,value] of new FormData(document.getElementById('reportFilters'))){if(value)d[key]=value}}},columns:[
{data:'ticket_number',render:escapeReportCell},{data:'created_at',render:escapeReportCell},{data:'reporter',render:escapeReportCell},{data:'category',render:escapeReportCell},{data:'service',render:escapeReportCell},
{data:'priority',render:escapeReportCell},{data:'status',render:escapeReportCell},{data:'assignee',render:value=>escapeReportCell(value)||'<span class="text-secondary">Belum di-assign</span>'},
{data:'team',render:value=>escapeReportCell(value)||'<span class="text-secondary">-</span>'},{data:'subject',render:escapeReportCell}],order:[[1,'desc']],language:{url:'https://cdn.datatables.net/plug-ins/2.1.8/i18n/id.json'}});
document.querySelectorAll('#reportFilters select,#reportFilters input').forEach(element=>element.addEventListener('change',()=>reportTable.ajax.reload()));
document.getElementById('reportSearch').addEventListener('input',event=>{
    const search=event.target.value;
    document.getElementById('reportExportSearch').value=search;
    reportTable.search(search).draw();
});
document.getElementById('reportPageLength').addEventListener('change',event=>reportTable.page.len(Number(event.target.value)).draw());
</script><?= $this->endSection() ?>
