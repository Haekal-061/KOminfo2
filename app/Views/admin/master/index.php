<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$fieldLabels = ['employee_number' => 'No. Pegawai', 'name' => 'Nama', 'whatsapp_number' => 'WhatsApp', 'email' => 'Email', 'department' => 'Unit/Department', 'position' => 'Jabatan', 'is_active' => 'Aktif', 'code' => 'Kode', 'description' => 'Deskripsi', 'color' => 'Warna', 'category_id' => 'Kategori', 'is_initial' => 'Status awal', 'is_final' => 'Status akhir', 'is_default' => 'Prioritas default', 'sort_order' => 'Urutan', 'role_id' => 'Role', 'password' => 'Password baru'];
$fields = $definition['fields'];
if ($resource === 'users') { $fields[] = 'password'; }
$values = $editRow ?? [];
?>
<div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h4 mb-1"><?= esc($title) ?></h1><p class="muted mb-0">Kelola data master ticketing.</p></div><button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#masterForm"><i class="bi bi-plus-lg me-1"></i><?= $editRow ? 'Ubah' : 'Tambah' ?> data</button></div>
<div class="collapse <?= $editRow ? 'show' : '' ?> mb-4" id="masterForm"><div class="card card-body"><form method="post" action="<?= site_url('admin/master/' . $resource) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= esc($values['id'] ?? '') ?>"><div class="row g-3">
<?php foreach ($fields as $field): $value = old($field, $values[$field] ?? ''); ?>
<div class="col-md-6"><label class="form-label" for="<?= esc($field) ?>"><?= esc($fieldLabels[$field] ?? ucwords(str_replace('_', ' ', $field))) ?></label>
<?php if (in_array($field, ['is_active', 'is_initial', 'is_final', 'is_default'], true)): ?><div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" name="<?= esc($field) ?>" id="<?= esc($field) ?>" value="1" <?= ($value === '' ? ($field === 'is_active') : (bool) $value) ? 'checked' : '' ?>></div>
<?php elseif ($field === 'category_id'): ?><select class="form-select" name="<?= esc($field) ?>" id="<?= esc($field) ?>" required><option value="">Pilih kategori</option><?php foreach ($categories as $option): ?><option value="<?= $option['id'] ?>" <?= (string) $value === (string) $option['id'] ? 'selected' : '' ?>><?= esc($option['name']) ?></option><?php endforeach ?></select>
<?php elseif ($field === 'role_id'): ?><select class="form-select" name="<?= esc($field) ?>" id="<?= esc($field) ?>" required><option value="">Pilih role</option><?php foreach ($roles as $option): ?><option value="<?= $option['id'] ?>" <?= (string) $value === (string) $option['id'] ? 'selected' : '' ?>><?= esc($option['label']) ?></option><?php endforeach ?></select>
<?php elseif ($field === 'description'): ?><textarea class="form-control" name="<?= esc($field) ?>" id="<?= esc($field) ?>" rows="2"><?= esc($value) ?></textarea>
<?php elseif ($field === 'password'): ?><input class="form-control" type="password" name="password" id="password" minlength="12" <?= $editRow ? '' : 'required' ?> autocomplete="new-password"><small class="form-text">Minimal 12 karakter; kosongkan saat tidak mengganti.</small>
<?php else: ?><input class="form-control" type="<?= $field === 'email' ? 'email' : (in_array($field, ['sort_order'], true) ? 'number' : ($field === 'color' ? 'color' : 'text')) ?>" name="<?= esc($field) ?>" id="<?= esc($field) ?>" value="<?= esc($value) ?>" <?= in_array($field, ['name', 'code', 'employee_number', 'whatsapp_number', 'email'], true) ? 'required' : '' ?>><?php endif ?>
</div><?php endforeach ?>
<?php if ($resource === 'teams'): ?><div class="col-md-6"><label class="form-label" for="member_ids">Anggota tim</label><input type="hidden" name="members_submitted" value="1"><select class="form-select" id="member_ids" name="member_ids[]" multiple size="5"><?php foreach ($users as $user): ?><option value="<?= $user['id'] ?>" <?= in_array((string) $user['id'], array_map('strval', old('member_ids', $selectedTeamMembers)), true) ? 'selected' : '' ?>><?= esc($user['name']) ?> (<?= esc($user['email']) ?>)</option><?php endforeach ?></select><small class="form-text">Operator yang menjadi anggota tim dapat melihat ticket yang di-assign ke tim ini.</small></div><?php endif ?>
</div><div class="mt-3 d-flex gap-2"><button class="btn btn-primary" type="submit">Simpan</button><?php if ($editRow): ?><a class="btn btn-light" href="<?= site_url('admin/master/' . $resource) ?>">Batal</a><?php endif ?></div></form></div></div>
<div class="card"><div class="card-body"><div class="table-responsive"><table id="masterTable" class="table table-hover align-middle w-100"><thead><tr><?php foreach ($definition['fields'] as $field): ?><th><?= esc($fieldLabels[$field] ?? ucwords(str_replace('_', ' ', $field))) ?></th><?php endforeach ?><th>Aksi</th></tr></thead></table></div></div></div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?><script>
const masterColumns = <?= json_encode(array_map(static fn($field) => ['data' => $field, 'render' => 'text'], $definition['fields']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
masterColumns.push({data:'actions',orderable:false,searchable:false,render: value => value});
masterColumns.forEach((column,index)=>{if(index<masterColumns.length-1)column.render=(data,type)=>{if(type!=='display'||data===null)return data;const el=document.createElement('span');el.textContent=data;return el.innerHTML}});
new DataTable('#masterTable',{processing:true,serverSide:true,ajax:'<?= site_url('admin/master/' . $resource . '/datatable') ?>',columns:masterColumns,order:[],language:{url:'https://cdn.datatables.net/plug-ins/2.1.8/i18n/id.json'}});
</script><?= $this->endSection() ?>
