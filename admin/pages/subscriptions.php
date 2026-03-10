<?php
$pageTitle = 'Abonnements';
$db = Database::getInstance();

$status = $_GET['status'] ?? 'all';
$where = '1';
$params = [];
if ($status !== 'all') { $where = 's.status = ?'; $params = [$status]; }

$total = $db->fetchColumn("SELECT COUNT(*) FROM wp_subscriptions s WHERE $where", $params);
$pagination = wp_paginate($total);
$subs = $db->fetchAll(
    "SELECT s.*, p.name as product_name, p.type, u.first_name, u.last_name, u.email
     FROM wp_subscriptions s JOIN wp_products p ON s.product_id = p.id JOIN wp_users u ON s.user_id = u.id
     WHERE $where ORDER BY s.created_at DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}",
    $params
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $subId = (int)($_POST['sub_id'] ?? 0);
    if ($action === 'suspend' && $subId) {
        ServiceManager::suspendService($subId, $_POST['reason'] ?? 'Admin');
        wp_flash('success', 'Service suspendu.');
    } elseif ($action === 'activate' && $subId) {
        ServiceManager::unsuspendService($subId);
        $db->update('wp_subscriptions', ['status' => 'active', 'suspended_at' => null, 'suspension_reason' => null], 'id = ?', [$subId]);
        wp_flash('success', 'Service reactive.');
    } elseif ($action === 'terminate' && $subId) {
        ServiceManager::terminateService($subId);
        wp_flash('success', 'Service supprime.');
    }
    wp_redirect(wp_url("admin/?page=subscriptions&status=$status"));
}

$statusLabels = ['active' => 'Actif', 'pending' => 'En attente', 'suspended' => 'Suspendu', 'cancelled' => 'Annule', 'expired' => 'Expire'];
$statusColors = ['active' => 'success', 'pending' => 'warning', 'suspended' => 'danger', 'cancelled' => 'dark', 'expired' => 'secondary'];
?>

<ul class="nav nav-pills mb-4">
    <?php foreach (['all' => 'Tous', 'active' => 'Actifs', 'pending' => 'En attente', 'suspended' => 'Suspendus', 'cancelled' => 'Annules'] as $k => $v): ?>
    <li class="nav-item"><a class="nav-link <?= $status === $k ? 'active' : '' ?>" href="<?= wp_url("admin/?page=subscriptions&status=$k") ?>"><?= $v ?></a></li>
    <?php endforeach; ?>
</ul>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>ID</th><th>Client</th><th>Produit</th><th>Type</th><th>Prix</th><th>Statut</th><th>Echeance</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($subs as $s): ?>
            <tr>
                <td>#<?= $s['id'] ?></td>
                <td><a href="<?= wp_url("admin/?page=client-detail&id={$s['user_id']}") ?>"><?= wp_escape($s['first_name'] . ' ' . $s['last_name']) ?></a></td>
                <td><?= wp_escape($s['product_name']) ?></td>
                <td><span class="badge bg-<?= $s['type'] === 'vps' ? 'primary' : ($s['type'] === 'hosting' ? 'success' : 'info') ?>"><?= strtoupper($s['type']) ?></span></td>
                <td><?= wp_format_price($s['price']) ?>/<?= $s['billing_cycle'] === 'yearly' ? 'an' : 'mois' ?></td>
                <td><span class="badge bg-<?= $statusColors[$s['status']] ?? 'secondary' ?>"><?= $statusLabels[$s['status']] ?? $s['status'] ?></span></td>
                <td><?= wp_format_date($s['next_due_date']) ?></td>
                <td>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
                        <ul class="dropdown-menu">
                            <li><form method="POST"><?= wp_csrf_field() ?><input type="hidden" name="sub_id" value="<?= $s['id'] ?>">
                                <?php if ($s['status'] === 'active'): ?>
                                    <button name="action" value="suspend" class="dropdown-item text-warning"><i class="bi bi-pause me-2"></i>Suspendre</button>
                                <?php elseif ($s['status'] === 'suspended'): ?>
                                    <button name="action" value="activate" class="dropdown-item text-success"><i class="bi bi-play me-2"></i>Reactiver</button>
                                <?php endif; ?>
                                <?php if ($s['status'] !== 'cancelled'): ?>
                                    <button name="action" value="terminate" class="dropdown-item text-danger" onclick="return confirm('Supprimer ce service ? Cette action est irreversible.')"><i class="bi bi-trash me-2"></i>Supprimer</button>
                                <?php endif; ?>
                            </form></li>
                        </ul>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?= wp_pagination_html($pagination, wp_url("admin/?page=subscriptions&status=$status")) ?>
