<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<div class="mb-4"><h1 class="h4 mb-1">Buat Ticket</h1><p class="muted mb-0">Catat aduan pegawai secara manual.</p></div>
<div class="card"><div class="card-body p-4"><form method="post" action="<?= site_url('admin/tickets') ?>"><?= csrf_field() ?><div class="row g-3">
<div class="col-md-6"><label class="form-label">Pegawai Pelapor</label><select class="form-select" name="reporter_id" required><option value="">Pilih pegawai</option><?php foreach ($formData['employees'] as $row): ?><option value="<?= $row['id'] ?>" <?= old('reporter_id') == $row['id'] ? 'selected' : '' ?>><?= esc($row['name']) ?> (<?= esc($row['employee_number']) ?>)</option><?php endforeach ?></select></div>
<div class="col-md-6"><label class="form-label">Kategori</label><select class="form-select" name="category_id" required><option value="">Pilih kategori</option><?php foreach ($formData['categories'] as $row): ?><option value="<?= $row['id'] ?>" <?= old('category_id') == $row['id'] ? 'selected' : '' ?>><?= esc($row['name']) ?></option><?php endforeach ?></select></div>
<div class="col-md-6"><label class="form-label">Jenis Layanan</label><select class="form-select" name="service_type_id"><option value="">Pilih otomatis / tidak ditentukan</option><?php foreach ($formData['services'] as $row): ?><option value="<?= $row['id'] ?>" data-category-id="<?= $row['category_id'] ?>"><?= esc($row['name']) ?></option><?php endforeach ?></select></div>
<div class="col-md-6"><label class="form-label">Channel</label><select class="form-select" name="channel"><option value="admin">Admin</option><option value="web">Web</option><option value="api">API</option></select></div>
<div class="col-12"><label class="form-label">Subjek</label><input class="form-control" name="subject" maxlength="255" value="<?= esc(old('subject')) ?>" required></div>
<div class="col-12"><label class="form-label">Uraian masalah</label><textarea class="form-control" name="description" rows="5" required><?= esc(old('description')) ?></textarea></div>
</div><div class="mt-4 d-flex gap-2"><button class="btn btn-primary">Buat Ticket</button><a class="btn btn-light" href="<?= site_url('admin/tickets') ?>">Batal</a></div></form></div></div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?><script>
const categorySelect=document.querySelector('[name="category_id"]'),serviceSelect=document.querySelector('[name="service_type_id"]');
categorySelect.addEventListener('change',()=>{for(const option of serviceSelect.options){option.hidden=option.value!==''&&option.dataset.categoryId!==categorySelect.value;if(option.hidden&&option.selected)serviceSelect.value=''}});
categorySelect.dispatchEvent(new Event('change'));
</script><?= $this->endSection() ?>
