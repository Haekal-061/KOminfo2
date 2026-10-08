<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Ticketing') ?> | KOMINFO PINRANG</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://raw.githubusercontent.com/fhdjg/xakti-admin-template/d70e4bb6324c9f9a8c00d7aa135e7eadbe23232c/assets/css/app.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root{--navy:#14213d;--surface:#f3f6fb;--accent:#2563eb}
        body{background:var(--surface);color:#182338;font-size:.94rem}
        .sidebar{width:250px;position:fixed;inset:0 auto 0 0;background:var(--navy);color:#dbe4f3;padding:1.25rem .9rem;z-index:10}
        .brand{font-weight:700;color:#fff;text-decoration:none;display:flex;gap:.7rem;align-items:center;padding:.45rem .55rem 1.5rem}
        .brand-mark{background:#2563eb;border-radius:10px;padding:.45rem .6rem}
        .nav-label{font-size:.68rem;text-transform:uppercase;letter-spacing:.12em;color:#91a1bb;padding:.8rem .55rem .35rem}
        .sidebar .nav-link{color:#c4d0e2;border-radius:8px;padding:.62rem .7rem;margin:.1rem 0}
        .sidebar .nav-link:hover,.sidebar .nav-link.active{background:#26385b;color:white}
        .main{margin-left:250px;min-height:100vh}
        .topbar{height:70px;background:#fff;border-bottom:1px solid #e6eaf1;padding:0 2rem}
        .content{padding:1.75rem 2rem}
        .card{border:1px solid #e7ebf2;box-shadow:0 4px 15px rgba(21,41,76,.035);border-radius:12px}
        .metric{font-size:1.75rem;font-weight:700}
        .table>:not(caption)>*>*{padding:.8rem .75rem}
        .muted{color:#738199}
        @media(max-width:767px){.sidebar{width:72px;padding:.8rem .5rem}.sidebar .nav-label,.sidebar .nav-link span,.brand .brand-name{display:none}.sidebar .nav-link{text-align:center}.main{margin-left:72px}.content{padding:1rem}.topbar{padding:0 1rem}}
    </style>
</head>
<body>
<aside class="sidebar">
    <a class="brand" href="<?= site_url('admin') ?>"><span class="brand-mark"><i class="bi bi-headset"></i></span><span class="brand-name">KOMINFO<br><small>Ticketing</small></span></a>
    <div class="nav-label">Workspace</div>
    <nav class="nav flex-column">
        <?php if (session()->get('role_name') !== 'operator'): ?><a class="nav-link" href="<?= site_url('admin') ?>"><i class="bi bi-grid me-2"></i><span>Dashboard</span></a><?php endif ?>
        <a class="nav-link" href="<?= site_url('admin/tickets') ?>"><i class="bi bi-ticket-detailed me-2"></i><span>Ticket</span></a>
        <a class="nav-link" href="<?= site_url('admin/reports') ?>"><i class="bi bi-bar-chart me-2"></i><span>Laporan</span></a>
    </nav>
    <div class="nav-label">Master Data</div>
    <nav class="nav flex-column">
        <?php foreach (['employees' => 'Pegawai', 'categories' => 'Kategori', 'services' => 'Jenis Layanan', 'statuses' => 'Status', 'priorities' => 'Prioritas', 'teams' => 'Tim', 'users' => 'Pengguna'] as $key => $label): ?>
            <a class="nav-link" href="<?= site_url('admin/master/' . $key) ?>"><i class="bi bi-chevron-right me-2"></i><span><?= esc($label) ?></span></a>
        <?php endforeach ?>
    </nav>
</aside>
<main class="main">
    <header class="topbar d-flex align-items-center justify-content-between">
        <div class="fw-semibold"><?= esc($title ?? '') ?></div>
        <div class="d-flex align-items-center gap-3">
            <div class="text-end"><div class="fw-semibold"><?= esc(session()->get('user_name')) ?></div><small class="muted"><?= esc(session()->get('role_name')) ?></small></div>
            <form method="post" action="<?= site_url('logout') ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary" type="submit" title="Keluar"><i class="bi bi-box-arrow-right"></i></button></form>
        </div>
    </header>
    <section class="content">
        <?php if ($message = session()->getFlashdata('success')): ?><div class="alert alert-success alert-dismissible fade show"><?= esc($message) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif ?>
        <?php if ($message = session()->getFlashdata('error')): ?><div class="alert alert-danger alert-dismissible fade show"><?= esc($message) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif ?>
        <?= $this->renderSection('content') ?>
    </section>
</main>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
