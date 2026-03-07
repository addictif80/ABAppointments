<?php
$currentPage = $pageContent;
$userName = $_SESSION['user_name'] ?? 'Admin';
$isAdmin = Auth::isAdmin();
$primaryColor = ab_setting('primary_color', '#e91e63');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ab_escape(ab_setting('business_name', 'ABAppointments')) ?> - Administration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    <style>
        :root { --ab-primary: <?= $primaryColor ?>; --ab-primary-dark: color-mix(in srgb, <?= $primaryColor ?> 80%, black); }
        body { background: #f4f6f9; }
        .sidebar { width: 260px; min-height: 100vh; background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); position: fixed; left: 0; top: 0; z-index: 100; transition: transform 0.3s; }
        .sidebar .brand { padding: 20px; color: #fff; font-size: 1.2rem; font-weight: 700; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar .nav-link { color: rgba(255,255,255,0.7); padding: 12px 20px; display: flex; align-items: center; gap: 10px; transition: all 0.2s; border-left: 3px solid transparent; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background: rgba(255,255,255,0.08); border-left-color: var(--ab-primary); }
        .sidebar .nav-link i { width: 20px; text-align: center; }
        .main-content { margin-left: 260px; padding: 20px; }
        .top-bar { background: #fff; padding: 15px 25px; margin: -20px -20px 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.08); display: flex; justify-content: space-between; align-items: center; }
        .btn-primary { background: var(--ab-primary); border-color: var(--ab-primary); }
        .btn-primary:hover { background: var(--ab-primary-dark); border-color: var(--ab-primary-dark); }
        .card { border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-radius: 10px; }
        .stat-card { border-left: 4px solid var(--ab-primary); }
        .badge-pending { background: #ffc107; color: #000; }
        .badge-confirmed { background: #28a745; }
        .badge-cancelled { background: #dc3545; }
        .badge-completed { background: #6c757d; }
        .badge-no_show { background: #fd7e14; }
        .badge-paid { background: #28a745; }
        .badge-refunded { background: #17a2b8; }
        .nav-section { padding: 15px 20px 5px; color: rgba(255,255,255,0.4); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <div class="brand">
            <i class="bi bi-calendar-heart"></i> <?= ab_escape(ab_setting('business_name', 'ABAppointments')) ?>
        </div>
        <nav>
            <div class="nav-section">Principal</div>
            <a href="<?= ab_url('admin/index.php?page=dashboard') ?>" class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i> Tableau de bord
            </a>
            <a href="<?= ab_url('admin/index.php?page=appointments') ?>" class="nav-link <?= $currentPage === 'appointments' ? 'active' : '' ?>">
                <i class="bi bi-calendar-check"></i> Rendez-vous
            </a>
            <a href="<?= ab_url('admin/index.php?page=customers') ?>" class="nav-link <?= $currentPage === 'customers' ? 'active' : '' ?>">
                <i class="bi bi-people"></i> Clients
            </a>
            <a href="<?= ab_url('admin/index.php?page=deposits') ?>" class="nav-link <?= $currentPage === 'deposits' ? 'active' : '' ?>">
                <i class="bi bi-cash-coin"></i> Acomptes
            </a>

            <div class="nav-section">Configuration</div>
            <a href="<?= ab_url('admin/index.php?page=services') ?>" class="nav-link <?= $currentPage === 'services' ? 'active' : '' ?>">
                <i class="bi bi-palette"></i> Prestations
            </a>
            <a href="<?= ab_url('admin/index.php?page=categories') ?>" class="nav-link <?= $currentPage === 'categories' ? 'active' : '' ?>">
                <i class="bi bi-tags"></i> Catégories
            </a>
            <?php if ($isAdmin): ?>
            <a href="<?= ab_url('admin/index.php?page=providers') ?>" class="nav-link <?= $currentPage === 'providers' ? 'active' : '' ?>">
                <i class="bi bi-person-badge"></i> Prestataires
            </a>
            <?php endif; ?>
            <a href="<?= ab_url('admin/index.php?page=working-hours') ?>" class="nav-link <?= $currentPage === 'working-hours' ? 'active' : '' ?>">
                <i class="bi bi-clock"></i> Horaires
            </a>
            <a href="<?= ab_url('admin/index.php?page=holidays') ?>" class="nav-link <?= $currentPage === 'holidays' ? 'active' : '' ?>">
                <i class="bi bi-calendar-x"></i> Congés
            </a>

            <?php if ($isAdmin): ?>
            <div class="nav-section">Système</div>
            <a href="<?= ab_url('admin/index.php?page=settings') ?>" class="nav-link <?= $currentPage === 'settings' ? 'active' : '' ?>">
                <i class="bi bi-gear"></i> Paramètres
            </a>
            <a href="<?= ab_url('admin/index.php?page=email-templates') ?>" class="nav-link <?= $currentPage === 'email-templates' ? 'active' : '' ?>">
                <i class="bi bi-envelope"></i> Emails
            </a>
            <?php endif; ?>
        </nav>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <button class="btn btn-sm btn-outline-secondary d-md-none" onclick="document.getElementById('sidebar').classList.toggle('show')">
                <i class="bi bi-list"></i>
            </button>
            <div>
                <a href="<?= ab_url('public/') ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-box-arrow-up-right"></i> Voir la page de réservation
                </a>
            </div>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle"></i> <?= ab_escape($userName) ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?= ab_url('admin/index.php?page=profile') ?>"><i class="bi bi-person"></i> Mon profil</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?= ab_url('admin/index.php?page=login&action=logout') ?>"><i class="bi bi-box-arrow-right"></i> Déconnexion</a></li>
                </ul>
            </div>
        </div>

        <?php foreach (ab_get_flash() as $flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show">
            <?= $flash['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endforeach; ?>

        <?php require __DIR__ . '/pages/' . $currentPage . '.php'; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <?php if (file_exists(__DIR__ . '/pages/' . $currentPage . '.js.php')): ?>
        <?php require __DIR__ . '/pages/' . $currentPage . '.js.php'; ?>
    <?php endif; ?>
</body>
</html>
