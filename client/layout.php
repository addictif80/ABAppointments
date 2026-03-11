<?php
$currentUser = Auth::user();
$siteName = wp_setting('site_name', 'WebPanel');
$primaryColor = wp_setting('primary_color', '#4F46E5');
$pageTitle = $pageTitle ?? 'Espace Client';

// Count unread tickets
$db = Database::getInstance();
$openTickets = $db->count('wp_tickets', "user_id = ? AND status NOT IN ('resolved','closed')", [$currentUser['id']]);
$pendingInvoices = $db->count('wp_invoices', "user_id = ? AND status IN ('pending','overdue')", [$currentUser['id']]);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= wp_escape($pageTitle) ?> - <?= wp_escape($siteName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --primary: <?= wp_escape($primaryColor) ?>; --primary-dark: color-mix(in srgb, <?= wp_escape($primaryColor) ?> 85%, black); }
        body { background: #f0f2f5; min-height: 100vh; }
        .sidebar { width: 260px; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); min-height: 100vh; position: fixed; left: 0; top: 0; z-index: 1000; transition: transform .3s; }
        .sidebar .nav-link { color: rgba(255,255,255,.8); padding: .75rem 1.25rem; border-radius: .5rem; margin: 2px 12px; font-size: .9rem; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255,255,255,.15); color: #fff; }
        .sidebar .nav-link i { width: 24px; text-align: center; margin-right: 8px; }
        .main-content { margin-left: 260px; padding: 24px; }
        .topbar { background: #fff; border-bottom: 1px solid #e5e7eb; padding: 12px 24px; margin: -24px -24px 24px; display: flex; justify-content: space-between; align-items: center; }
        .stat-card { background: #fff; border-radius: 12px; padding: 24px; border: 1px solid #e5e7eb; }
        .stat-card .icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; }
        .badge-status { font-size: .75rem; padding: .35em .65em; }
        .sidebar-brand { padding: 20px; color: #fff; font-size: 1.3rem; font-weight: 700; border-bottom: 1px solid rgba(255,255,255,.15); }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>
<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <i class="bi bi-hdd-rack"></i> <?= wp_escape($siteName) ?>
    </div>
    <nav class="mt-3">
        <a href="<?= wp_url('client/?page=dashboard') ?>" class="nav-link <?= $page === 'dashboard' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i> Tableau de bord
        </a>
        <a href="<?= wp_url('client/?page=services') ?>" class="nav-link <?= $page === 'services' ? 'active' : '' ?>">
            <i class="bi bi-shop"></i> Catalogue
        </a>
        <a href="<?= wp_url('client/?page=subscriptions') ?>" class="nav-link <?= in_array($page, ['subscriptions','vps-detail','hosting-detail','navidrome-detail']) ? 'active' : '' ?>">
            <i class="bi bi-collection"></i> Mes Services
        </a>
        <a href="<?= wp_url('client/?page=invoices') ?>" class="nav-link <?= in_array($page, ['invoices','invoice-detail','invoice-pay']) ? 'active' : '' ?>">
            <i class="bi bi-receipt"></i> Factures
            <?php if ($pendingInvoices > 0): ?><span class="badge bg-warning ms-auto"><?= $pendingInvoices ?></span><?php endif; ?>
        </a>
        <a href="<?= wp_url('client/?page=tickets') ?>" class="nav-link <?= in_array($page, ['tickets','ticket-detail','ticket-new']) ? 'active' : '' ?>">
            <i class="bi bi-chat-left-text"></i> Support
            <?php if ($openTickets > 0): ?><span class="badge bg-info ms-auto"><?= $openTickets ?></span><?php endif; ?>
        </a>
        <a href="<?= wp_url('client/?page=gift-cards') ?>" class="nav-link <?= $page === 'gift-cards' ? 'active' : '' ?>">
            <i class="bi bi-gift"></i> Cartes Cadeau
        </a>
        <hr style="border-color: rgba(255,255,255,.2); margin: 8px 20px;">
        <a href="<?= wp_url('client/?page=profile') ?>" class="nav-link <?= $page === 'profile' ? 'active' : '' ?>">
            <i class="bi bi-person"></i> Mon Profil
        </a>
        <a href="<?= wp_url('client/?page=logout') ?>" class="nav-link">
            <i class="bi bi-box-arrow-left"></i> Deconnexion
        </a>
    </nav>
</div>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn btn-sm btn-outline-secondary d-md-none me-2" onclick="document.getElementById('sidebar').classList.toggle('show')">
                <i class="bi bi-list"></i>
            </button>
            <span class="fw-semibold"><?= wp_escape($pageTitle) ?></span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted small"><?= wp_escape($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></span>
        </div>
    </div>

    <?php foreach (['success', 'error', 'warning', 'info'] as $type): ?>
        <?php foreach (wp_flash($type) as $msg): ?>
            <div class="alert alert-<?= $type === 'error' ? 'danger' : $type ?> alert-dismissible fade show">
                <?= wp_escape($msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <?php require $pageFile; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
