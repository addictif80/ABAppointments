<?php
$pageTitle = 'Catalogue';
$db = Database::getInstance();

$type = $_GET['type'] ?? 'all';
$where = "is_active = 1";
$params = [];
if ($type !== 'all') {
    $where .= " AND type = ?";
    $params[] = $type;
}
$products = $db->fetchAll("SELECT * FROM wp_products WHERE $where ORDER BY type, sort_order, price_monthly", $params);

$grouped = [];
foreach ($products as $p) {
    $grouped[$p['type']][] = $p;
}

$typeLabels = ['vps' => 'Serveurs VPS', 'hosting' => 'Hebergement Web', 'navidrome' => 'Streaming Musical'];
$typeIcons = ['vps' => 'bi-hdd-rack', 'hosting' => 'bi-globe', 'navidrome' => 'bi-music-note-beamed'];
$typeColors = ['vps' => 'primary', 'hosting' => 'success', 'navidrome' => 'info'];
?>

<ul class="nav nav-pills mb-4">
    <li class="nav-item"><a class="nav-link <?= $type === 'all' ? 'active' : '' ?>" href="<?= wp_url('client/?page=services') ?>">Tout</a></li>
    <li class="nav-item"><a class="nav-link <?= $type === 'vps' ? 'active' : '' ?>" href="<?= wp_url('client/?page=services&type=vps') ?>">VPS</a></li>
    <li class="nav-item"><a class="nav-link <?= $type === 'hosting' ? 'active' : '' ?>" href="<?= wp_url('client/?page=services&type=hosting') ?>">Hebergement</a></li>
    <li class="nav-item"><a class="nav-link <?= $type === 'navidrome' ? 'active' : '' ?>" href="<?= wp_url('client/?page=services&type=navidrome') ?>">Navidrome</a></li>
</ul>

<?php foreach ($grouped as $gType => $prods): ?>
<h5 class="mb-3"><i class="bi <?= $typeIcons[$gType] ?? 'bi-box' ?>"></i> <?= $typeLabels[$gType] ?? ucfirst($gType) ?></h5>
<div class="row g-4 mb-5">
    <?php foreach ($prods as $p): ?>
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h5 class="card-title mb-0"><?= wp_escape($p['name']) ?></h5>
                    <span class="badge bg-<?= $typeColors[$gType] ?? 'secondary' ?>"><?= strtoupper($gType) ?></span>
                </div>

                <div class="mb-3">
                    <span class="fs-3 fw-bold" style="color: var(--primary)"><?= wp_format_price($p['price_monthly']) ?></span>
                    <span class="text-muted">/mois</span>
                    <?php if ($p['price_yearly']): ?>
                        <br><small class="text-muted">ou <?= wp_format_price($p['price_yearly']) ?>/an</small>
                    <?php endif; ?>
                </div>

                <?php if ($p['description']): ?>
                    <p class="text-muted small"><?= wp_escape($p['description']) ?></p>
                <?php endif; ?>

                <ul class="list-unstyled mb-3">
                    <?php if ($gType === 'vps'): ?>
                        <li><i class="bi bi-cpu text-primary me-2"></i> <?= $p['proxmox_cores'] ?> vCPU</li>
                        <li><i class="bi bi-memory text-primary me-2"></i> <?= $p['proxmox_ram_mb'] >= 1024 ? ($p['proxmox_ram_mb']/1024) . ' GB' : $p['proxmox_ram_mb'] . ' MB' ?> RAM</li>
                        <li><i class="bi bi-device-hdd text-primary me-2"></i> <?= $p['proxmox_disk_gb'] ?> GB SSD</li>
                        <?php if ($p['proxmox_bandwidth_gb']): ?>
                            <li><i class="bi bi-speedometer text-primary me-2"></i> <?= $p['proxmox_bandwidth_gb'] ?> GB Bande passante</li>
                        <?php endif; ?>
                    <?php elseif ($gType === 'hosting'): ?>
                        <li><i class="bi bi-device-hdd text-success me-2"></i> <?= $p['hosting_disk_mb'] >= 1024 ? ($p['hosting_disk_mb']/1024) . ' GB' : $p['hosting_disk_mb'] . ' MB' ?> Stockage</li>
                        <li><i class="bi bi-envelope text-success me-2"></i> <?= $p['hosting_email_accounts'] ?> Comptes email</li>
                        <li><i class="bi bi-database text-success me-2"></i> <?= $p['hosting_databases'] ?> Bases de donnees</li>
                        <li><i class="bi bi-globe text-success me-2"></i> <?= $p['hosting_domains'] ?> Domaine(s)</li>
                    <?php elseif ($gType === 'navidrome'): ?>
                        <li><i class="bi bi-hdd text-info me-2"></i> <?= $p['navidrome_storage_mb'] >= 1024 ? ($p['navidrome_storage_mb']/1024) . ' GB' : $p['navidrome_storage_mb'] . ' MB' ?> Stockage</li>
                        <?php if ($p['navidrome_max_playlists']): ?>
                            <li><i class="bi bi-music-note-list text-info me-2"></i> <?= $p['navidrome_max_playlists'] ?> Playlists</li>
                        <?php else: ?>
                            <li><i class="bi bi-music-note-list text-info me-2"></i> Playlists illimitees</li>
                        <?php endif; ?>
                        <li><i class="bi bi-phone text-info me-2"></i> Applications mobiles</li>
                    <?php endif; ?>

                    <?php
                    $features = json_decode($p['features'] ?? '[]', true);
                    if ($features):
                        foreach ($features as $f): ?>
                            <li><i class="bi bi-check-circle text-success me-2"></i> <?= wp_escape($f) ?></li>
                        <?php endforeach;
                    endif; ?>
                </ul>

                <?php if ($p['setup_fee'] > 0): ?>
                    <p class="small text-muted">Frais d'installation : <?= wp_format_price($p['setup_fee']) ?></p>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-transparent border-0 pb-3">
                <a href="<?= wp_url("client/?page=order&product_id={$p['id']}") ?>" class="btn btn-<?= $typeColors[$gType] ?? 'primary' ?> w-100">Commander</a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

<?php if (empty($products)): ?>
<div class="text-center py-5 text-muted">
    <i class="bi bi-box-seam fs-1"></i>
    <p class="mt-2">Aucun produit disponible pour le moment.</p>
</div>
<?php endif; ?>
