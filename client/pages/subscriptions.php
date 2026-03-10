<?php
$pageTitle = 'Mes Services';
$db = Database::getInstance();
$userId = $_SESSION['user_id'];

$status = $_GET['status'] ?? 'all';
$where = "s.user_id = ?";
$params = [$userId];
if ($status !== 'all') {
    $where .= " AND s.status = ?";
    $params[] = $status;
}

$total = $db->fetchColumn("SELECT COUNT(*) FROM wp_subscriptions s WHERE $where", $params);
$pagination = wp_paginate($total);

$subscriptions = $db->fetchAll(
    "SELECT s.*, p.name as product_name, p.type as product_type
     FROM wp_subscriptions s JOIN wp_products p ON s.product_id = p.id
     WHERE $where ORDER BY s.created_at DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}",
    $params
);

$statusLabels = ['active' => 'Actif', 'pending' => 'En attente', 'suspended' => 'Suspendu', 'cancelled' => 'Annule', 'expired' => 'Expire'];
$statusBadges = ['active' => 'success', 'pending' => 'warning', 'suspended' => 'danger', 'cancelled' => 'secondary', 'expired' => 'dark'];
$typeIcons = ['vps' => 'bi-hdd-rack', 'hosting' => 'bi-globe', 'navidrome' => 'bi-music-note-beamed'];
?>

<ul class="nav nav-pills mb-4">
    <li class="nav-item"><a class="nav-link <?= $status === 'all' ? 'active' : '' ?>" href="<?= wp_url('client/?page=subscriptions') ?>">Tous</a></li>
    <li class="nav-item"><a class="nav-link <?= $status === 'active' ? 'active' : '' ?>" href="<?= wp_url('client/?page=subscriptions&status=active') ?>">Actifs</a></li>
    <li class="nav-item"><a class="nav-link <?= $status === 'suspended' ? 'active' : '' ?>" href="<?= wp_url('client/?page=subscriptions&status=suspended') ?>">Suspendus</a></li>
</ul>

<?php if (empty($subscriptions)): ?>
    <div class="text-center py-5">
        <i class="bi bi-box-seam fs-1 text-muted"></i>
        <p class="text-muted mt-2">Aucun service trouve</p>
        <a href="<?= wp_url('client/?page=services') ?>" class="btn btn-primary">Decouvrir nos offres</a>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($subscriptions as $s): ?>
        <?php
        $detailPage = match($s['product_type']) {
            'vps' => 'vps-detail',
            'hosting' => 'hosting-detail',
            'navidrome' => 'navidrome-detail',
            default => 'subscriptions'
        };
        ?>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="badge bg-<?= $statusBadges[$s['status']] ?? 'secondary' ?>"><?= $statusLabels[$s['status']] ?? $s['status'] ?></span>
                        <i class="bi <?= $typeIcons[$s['product_type']] ?? 'bi-box' ?> fs-4 text-muted"></i>
                    </div>
                    <h5 class="card-title"><?= wp_escape($s['product_name']) ?></h5>
                    <p class="text-muted mb-1"><?= wp_format_price($s['price']) ?> / <?= $s['billing_cycle'] === 'yearly' ? 'an' : 'mois' ?></p>
                    <p class="small text-muted">Prochaine echeance : <?= wp_format_date($s['next_due_date']) ?></p>
                </div>
                <div class="card-footer bg-transparent">
                    <a href="<?= wp_url("client/?page=$detailPage&id={$s['id']}") ?>" class="btn btn-outline-primary btn-sm w-100">
                        <i class="bi bi-gear me-1"></i> Gerer
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?= wp_pagination_html($pagination, wp_url("client/?page=subscriptions&status=$status")) ?>
<?php endif; ?>
