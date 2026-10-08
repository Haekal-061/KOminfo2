<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<div class="mb-4"><h1 class="h4 mb-1">Laporan Ticket</h1><p class="muted mb-0">Ekspor data ticket sebagai CSV yang dapat dibuka dengan Excel.</p></div>
<div class="card"><div class="card-body"><form method="get" action="<?= site_url('admin/reports/tickets.csv') ?>" class="row g-3 align-items-end">
<div class="col-md-3"><label class="form-label">Tanggal dari</label><input class="form-control" type="date" name="from"></div><div class="col-md-3"><label class="form-label">Tanggal sampai</label><input class="form-control" type="date" name="to"></div>
<div class="col-md-3"><label class="form-label">Kategori</label><select class="form-select" name="category_id"><option value="">Semua</option><?php foreach ($categories as $row): ?><option value="<?= $row['id'] ?>"><?= esc($row['name']) ?></option><?php endforeach ?></select></div>
<div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status_id"><option value="">Semua</option><?php foreach ($statuses as $row): ?><option value="<?= $row['id'] ?>"><?= esc($row['name']) ?></option><?php endforeach ?></select></div>
<div class="col-md-3"><label class="form-label">Prioritas</label><select class="form-select" name="priority_id"><option value="">Semua</option><?php foreach ($priorities as $row): ?><option value="<?= $row['id'] ?>"><?= esc($row['name']) ?></option><?php endforeach ?></select></div>
<div class="col-md-3"><label class="form-label">Operator</label><select class="form-select" name="assignee_user_id"><option value="">Semua</option><?php foreach ($users as $row): ?><option value="<?= $row['id'] ?>"><?= esc($row['name']) ?></option><?php endforeach ?></select></div>
<div class="col-md-3"><button class="btn btn-primary"><i class="bi bi-download me-1"></i>Unduh CSV</button></div>
</form></div></div>
<?= $this->endSection() ?>
