<?php
/**
 * WebPanel - Public Landing Page
 */
$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    // Check if installer is accessible
    $installLock = __DIR__ . '/../install/install.lock';
    if (file_exists($installLock)) {
        http_response_code(503);
        die('<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:60px;"><h2>Configuration manquante</h2><p>Le fichier <code>config/config.php</code> n\'a pas pu etre cree automatiquement.<br>Copiez <code>config/config.sample.php</code> vers <code>config/config.php</code> et renseignez vos parametres.</p></body></html>');
    }
    header('Location: ../install/');
    exit;
}
require_once __DIR__ . '/../core/App.php';

$siteName = wp_setting('site_name', 'WebPanel');
$primaryColor = wp_setting('primary_color', '#4F46E5');
$companyName = wp_setting('company_name', 'WebPanel');
$db = Database::getInstance();

$vpsProducts = $db->fetchAll("SELECT * FROM wp_products WHERE type = 'vps' AND is_active = 1 ORDER BY sort_order, price_monthly LIMIT 4");
$hostingProducts = $db->fetchAll("SELECT * FROM wp_products WHERE type = 'hosting' AND is_active = 1 ORDER BY sort_order, price_monthly LIMIT 4");
$navidromeProducts = $db->fetchAll("SELECT * FROM wp_products WHERE type = 'navidrome' AND is_active = 1 ORDER BY sort_order, price_monthly LIMIT 4");
$monitors = $db->fetchAll("SELECT * FROM wp_monitors WHERE is_public = 1 ORDER BY name");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= wp_escape($siteName) ?> - Services Cloud</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --primary: <?= wp_escape($primaryColor) ?>; }
        .hero { background: linear-gradient(135deg, var(--primary), #1e1b4b); color: #fff; padding: 100px 0 80px; }
        .hero h1 { font-size: 3rem; font-weight: 800; }
        .pricing-card { border: 2px solid #e5e7eb; border-radius: 16px; transition: transform .2s, box-shadow .2s; }
        .pricing-card:hover { transform: translateY(-4px); box-shadow: 0 12px 40px rgba(0,0,0,.1); }
        .pricing-card.featured { border-color: var(--primary); }
        .section-title { font-size: 2rem; font-weight: 700; }
        .status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
        footer { background: #1e1b4b; color: rgba(255,255,255,.7); padding: 40px 0; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark fixed-top" style="background: rgba(30,27,75,.95); backdrop-filter: blur(10px);">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#"><i class="bi bi-hdd-rack me-2"></i><?= wp_escape($siteName) ?></a>
        <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#navMenu"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav ms-auto">
                <?php if (!empty($vpsProducts)): ?><li class="nav-item"><a class="nav-link" href="#vps">VPS</a></li><?php endif; ?>
                <?php if (!empty($hostingProducts)): ?><li class="nav-item"><a class="nav-link" href="#hosting">Hebergement</a></li><?php endif; ?>
                <?php if (!empty($navidromeProducts)): ?><li class="nav-item"><a class="nav-link" href="#navidrome">Musique</a></li><?php endif; ?>
                <?php if (!empty($monitors)): ?><li class="nav-item"><a class="nav-link" href="#status">Status</a></li><?php endif; ?>
            </ul>
            <div class="ms-3">
                <a href="<?= wp_url('client/?page=login') ?>" class="btn btn-outline-light btn-sm me-2">Connexion</a>
                <a href="<?= wp_url('client/?page=register') ?>" class="btn btn-light btn-sm">Inscription</a>
            </div>
        </div>
    </div>
</nav>

<section class="hero text-center">
    <div class="container">
        <h1><?= wp_escape($siteName) ?></h1>
        <p class="lead mb-4" style="max-width: 600px; margin: 0 auto;">Infrastructure cloud performante, hebergement web et streaming musical. Tout en un seul endroit.</p>
        <a href="<?= wp_url('client/?page=register') ?>" class="btn btn-light btn-lg me-2">Commencer</a>
        <a href="#vps" class="btn btn-outline-light btn-lg">Nos offres</a>
    </div>
</section>

<?php if (!empty($vpsProducts)): ?>
<section id="vps" class="py-5">
    <div class="container">
        <div class="text-center mb-5"><h2 class="section-title"><i class="bi bi-hdd-rack me-2" style="color:var(--primary)"></i>Serveurs VPS</h2><p class="text-muted">Infrastructure Proxmox haute performance</p></div>
        <div class="row g-4 justify-content-center">
            <?php foreach ($vpsProducts as $i => $p): ?>
            <div class="col-md-6 col-lg-3">
                <div class="pricing-card p-4 text-center <?= $i === 1 ? 'featured' : '' ?>">
                    <h5 class="fw-bold"><?= wp_escape($p['name']) ?></h5>
                    <div class="my-3"><span class="fs-2 fw-bold" style="color:var(--primary)"><?= wp_format_price($p['price_monthly']) ?></span><span class="text-muted">/mois</span></div>
                    <ul class="list-unstyled text-start">
                        <li class="mb-2"><i class="bi bi-cpu text-primary me-2"></i><?= $p['proxmox_cores'] ?> vCPU</li>
                        <li class="mb-2"><i class="bi bi-memory text-primary me-2"></i><?= $p['proxmox_ram_mb'] >= 1024 ? ($p['proxmox_ram_mb']/1024).'GB' : $p['proxmox_ram_mb'].'MB' ?> RAM</li>
                        <li class="mb-2"><i class="bi bi-device-hdd text-primary me-2"></i><?= $p['proxmox_disk_gb'] ?> GB SSD</li>
                    </ul>
                    <a href="<?= wp_url("client/?page=order&product_id={$p['id']}") ?>" class="btn btn-primary w-100" style="background:var(--primary);border-color:var(--primary)">Commander</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($hostingProducts)): ?>
<section id="hosting" class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5"><h2 class="section-title"><i class="bi bi-globe me-2 text-success"></i>Hebergement Web</h2><p class="text-muted">Hebergement mutualise avec SSL gratuit</p></div>
        <div class="row g-4 justify-content-center">
            <?php foreach ($hostingProducts as $p): ?>
            <div class="col-md-6 col-lg-3">
                <div class="pricing-card p-4 text-center bg-white">
                    <h5 class="fw-bold"><?= wp_escape($p['name']) ?></h5>
                    <div class="my-3"><span class="fs-2 fw-bold text-success"><?= wp_format_price($p['price_monthly']) ?></span><span class="text-muted">/mois</span></div>
                    <ul class="list-unstyled text-start">
                        <li class="mb-2"><i class="bi bi-device-hdd text-success me-2"></i><?= $p['hosting_disk_mb'] >= 1024 ? ($p['hosting_disk_mb']/1024).'GB' : $p['hosting_disk_mb'].'MB' ?></li>
                        <li class="mb-2"><i class="bi bi-envelope text-success me-2"></i><?= $p['hosting_email_accounts'] ?> emails</li>
                        <li class="mb-2"><i class="bi bi-database text-success me-2"></i><?= $p['hosting_databases'] ?> BDD</li>
                    </ul>
                    <a href="<?= wp_url("client/?page=order&product_id={$p['id']}") ?>" class="btn btn-success w-100">Commander</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($navidromeProducts)): ?>
<section id="navidrome" class="py-5">
    <div class="container">
        <div class="text-center mb-5"><h2 class="section-title"><i class="bi bi-music-note-beamed me-2 text-info"></i>Streaming Musical</h2><p class="text-muted">Votre musique, partout, avec Navidrome</p></div>
        <div class="row g-4 justify-content-center">
            <?php foreach ($navidromeProducts as $p): ?>
            <div class="col-md-6 col-lg-3">
                <div class="pricing-card p-4 text-center" style="background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff;">
                    <h5 class="fw-bold"><?= wp_escape($p['name']) ?></h5>
                    <div class="my-3"><span class="fs-2 fw-bold" style="color: #1DB954"><?= wp_format_price($p['price_monthly']) ?></span><span style="color:rgba(255,255,255,.6)">/mois</span></div>
                    <ul class="list-unstyled text-start">
                        <li class="mb-2"><i class="bi bi-hdd text-info me-2"></i><?= $p['navidrome_storage_mb'] >= 1024 ? ($p['navidrome_storage_mb']/1024).'GB' : $p['navidrome_storage_mb'].'MB' ?></li>
                        <li class="mb-2"><i class="bi bi-music-note-list text-info me-2"></i><?= $p['navidrome_max_playlists'] ?: 'Illimitees' ?> playlists</li>
                        <li class="mb-2"><i class="bi bi-phone text-info me-2"></i>Apps mobiles</li>
                    </ul>
                    <a href="<?= wp_url("client/?page=order&product_id={$p['id']}") ?>" class="btn w-100" style="background:#1DB954;border:none;color:#fff">S'abonner</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($monitors)): ?>
<section id="status" class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-4"><h2 class="section-title"><i class="bi bi-activity me-2"></i>Statut</h2></div>
        <div class="row g-3 justify-content-center">
            <?php foreach ($monitors as $m): ?>
            <div class="col-md-4">
                <div class="d-flex align-items-center justify-content-between bg-white p-3 rounded shadow-sm">
                    <span><?= wp_escape($m['name']) ?></span>
                    <span><span class="status-dot bg-<?= $m['status'] === 'up' ? 'success' : ($m['status'] === 'down' ? 'danger' : 'warning') ?>"></span> <?= $m['uptime_24h'] !== null ? $m['uptime_24h'] . '%' : '' ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<footer>
    <div class="container text-center">
        <p class="mb-1">&copy; <?= date('Y') ?> <?= wp_escape($companyName) ?>. Tous droits reserves.</p>
        <?php if (wp_setting('terms_url') || wp_setting('privacy_url')): ?>
        <div class="mt-2">
            <?php if (wp_setting('terms_url')): ?><a href="<?= wp_escape(wp_setting('terms_url')) ?>" class="text-white-50 me-3">CGV</a><?php endif; ?>
            <?php if (wp_setting('privacy_url')): ?><a href="<?= wp_escape(wp_setting('privacy_url')) ?>" class="text-white-50">Confidentialite</a><?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
