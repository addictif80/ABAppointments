<?php
$currentUser = Auth::user();
$siteName = wp_setting('site_name', 'WebPanel');
$primaryColor = wp_setting('primary_color', '#4F46E5');
$pageTitle = $pageTitle ?? 'Administration';
$db = Database::getInstance();

$openTickets = $db->count('wp_tickets', "status NOT IN ('resolved','closed')");
$overdueInvoices = $db->count('wp_invoices', "status = 'overdue'");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= wp_escape($pageTitle) ?> - Admin <?= wp_escape($siteName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --primary: <?= wp_escape($primaryColor) ?>; }
        body { background: #f0f2f5; min-height: 100vh; }
        .sidebar { width: 260px; background: #1e1b4b; min-height: 100vh; position: fixed; left: 0; top: 0; z-index: 1000; overflow-y: auto; }
        .sidebar .nav-link { color: rgba(255,255,255,.7); padding: .6rem 1.1rem; border-radius: .4rem; margin: 1px 10px; font-size: .85rem; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255,255,255,.12); color: #fff; }
        .sidebar .nav-link i { width: 22px; text-align: center; margin-right: 8px; }
        .sidebar .section-label { color: rgba(255,255,255,.4); font-size: .7rem; text-transform: uppercase; letter-spacing: 1px; padding: .5rem 1.2rem; margin-top: .5rem; }
        .main-content { margin-left: 260px; padding: 20px; }
        .topbar { background: #fff; border-bottom: 1px solid #e5e7eb; padding: 10px 20px; margin: -20px -20px 20px; display: flex; justify-content: space-between; align-items: center; }
        .stat-card { background: #fff; border-radius: 10px; padding: 20px; border: 1px solid #e5e7eb; }
        .sidebar-brand { padding: 16px 18px; color: #fff; font-size: 1.15rem; font-weight: 700; border-bottom: 1px solid rgba(255,255,255,.1); }
        @media (max-width: 768px) { .sidebar { display: none; } .main-content { margin-left: 0; } }
    </style>
</head>
<body>
<div class="sidebar">
    <div class="sidebar-brand"><i class="bi bi-shield-lock me-2"></i> <?= wp_escape($siteName) ?> Admin</div>
    <nav class="mt-2">
        <a href="<?= wp_url('admin/?page=dashboard') ?>" class="nav-link <?= $page === 'dashboard' ? 'active' : '' ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>

        <div class="section-label">Clients</div>
        <a href="<?= wp_url('admin/?page=clients') ?>" class="nav-link <?= in_array($page, ['clients','client-detail']) ? 'active' : '' ?>"><i class="bi bi-people"></i> Clients</a>
        <a href="<?= wp_url('admin/?page=subscriptions') ?>" class="nav-link <?= in_array($page, ['subscriptions','subscription-detail']) ? 'active' : '' ?>"><i class="bi bi-collection"></i> Abonnements</a>

        <div class="section-label">Finances</div>
        <a href="<?= wp_url('admin/?page=invoices') ?>" class="nav-link <?= in_array($page, ['invoices','invoice-detail']) ? 'active' : '' ?>"><i class="bi bi-receipt"></i> Factures <?php if ($overdueInvoices): ?><span class="badge bg-danger ms-auto"><?= $overdueInvoices ?></span><?php endif; ?></a>
        <a href="<?= wp_url('admin/?page=payments') ?>" class="nav-link <?= $page === 'payments' ? 'active' : '' ?>"><i class="bi bi-credit-card"></i> Paiements</a>
        <a href="<?= wp_url('admin/?page=promo-codes') ?>" class="nav-link <?= $page === 'promo-codes' ? 'active' : '' ?>"><i class="bi bi-tag"></i> Codes Promo</a>
        <a href="<?= wp_url('admin/?page=gift-cards') ?>" class="nav-link <?= $page === 'gift-cards' ? 'active' : '' ?>"><i class="bi bi-gift"></i> Cartes Cadeau</a>

        <div class="section-label">Support</div>
        <a href="<?= wp_url('admin/?page=tickets') ?>" class="nav-link <?= in_array($page, ['tickets','ticket-detail']) ? 'active' : '' ?>"><i class="bi bi-chat-left-text"></i> Tickets <?php if ($openTickets): ?><span class="badge bg-warning ms-auto"><?= $openTickets ?></span><?php endif; ?></a>

        <div class="section-label">Infrastructure</div>
        <a href="<?= wp_url('admin/?page=monitoring') ?>" class="nav-link <?= $page === 'monitoring' ? 'active' : '' ?>"><i class="bi bi-activity"></i> Monitoring</a>
        <a href="<?= wp_url('admin/?page=incidents') ?>" class="nav-link <?= $page === 'incidents' ? 'active' : '' ?>"><i class="bi bi-exclamation-triangle"></i> Incidents</a>
        <a href="<?= wp_url('admin/?page=ip-pool') ?>" class="nav-link <?= $page === 'ip-pool' ? 'active' : '' ?>"><i class="bi bi-hdd-network"></i> Pool IP</a>

        <div class="section-label">Configuration</div>
        <a href="<?= wp_url('admin/?page=products') ?>" class="nav-link <?= $page === 'products' ? 'active' : '' ?>"><i class="bi bi-box-seam"></i> Produits</a>
        <a href="<?= wp_url('admin/?page=os-templates') ?>" class="nav-link <?= $page === 'os-templates' ? 'active' : '' ?>"><i class="bi bi-disc"></i> Templates OS</a>
        <a href="<?= wp_url('admin/?page=email-templates') ?>" class="nav-link <?= $page === 'email-templates' ? 'active' : '' ?>"><i class="bi bi-envelope"></i> Emails</a>
        <a href="<?= wp_url('admin/?page=settings') ?>" class="nav-link <?= $page === 'settings' ? 'active' : '' ?>"><i class="bi bi-gear"></i> Parametres</a>
        <a href="<?= wp_url('admin/?page=activity-log') ?>" class="nav-link <?= $page === 'activity-log' ? 'active' : '' ?>"><i class="bi bi-clock-history"></i> Journal</a>

        <hr style="border-color: rgba(255,255,255,.1); margin: 6px 16px;">
        <a href="<?= wp_url('client/?page=dashboard') ?>" class="nav-link"><i class="bi bi-box-arrow-up-right"></i> Espace client</a>
        <a href="<?= wp_url('admin/?page=logout') ?>" class="nav-link"><i class="bi bi-box-arrow-left"></i> Deconnexion</a>
    </nav>
</div>

<div class="main-content">
    <div class="topbar">
        <span class="fw-semibold"><?= wp_escape($pageTitle) ?></span>
        <span class="text-muted small"><?= wp_escape($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></span>
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
