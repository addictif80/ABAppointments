<?php
$pageTitle = 'Gestion Clients';
$db = Database::getInstance();

$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? 'all';

$where = "role = 'client'";
$params = [];
if ($search) { $where .= " AND (email LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR company LIKE ?)"; $s = "%$search%"; $params = [$s,$s,$s,$s]; }
if ($status !== 'all') { $where .= " AND status = ?"; $params[] = $status; }

$total = $db->count('wp_users', $where, $params);
$pagination = wp_paginate($total);
$clients = $db->fetchAll(
    "SELECT u.*, (SELECT COUNT(*) FROM wp_subscriptions WHERE user_id = u.id AND status = 'active') as active_subs,
     (SELECT COALESCE(SUM(total),0) FROM wp_invoices WHERE user_id = u.id AND status = 'overdue') as overdue_amount
     FROM wp_users u WHERE $where ORDER BY u.created_at DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}",
    $params
);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $clientId = (int)($_POST['client_id'] ?? 0);
    if ($action === 'suspend' && $clientId) {
        $db->update('wp_users', ['status' => 'suspended'], 'id = ? AND role = ?', [$clientId, 'client']);
        wp_flash('success', 'Client suspendu.');
    } elseif ($action === 'activate' && $clientId) {
        $db->update('wp_users', ['status' => 'active'], 'id = ? AND role = ?', [$clientId, 'client']);
        wp_flash('success', 'Client reactive.');
    } elseif ($action === 'create') {
        $result = Auth::register([
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? wp_generate_password(12),
            'company' => trim($_POST['company'] ?? ''),
            'phone' => trim($_POST['phone'] ?? '')
        ]);
        if (isset($result['error'])) wp_flash('error', $result['error']);
        else wp_flash('success', 'Client cree.');
    }
    wp_redirect(wp_url('admin/?page=clients'));
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <form class="d-flex gap-2" method="GET">
        <input type="hidden" name="page" value="clients">
        <input type="text" name="q" class="form-control" placeholder="Rechercher..." value="<?= wp_escape($search) ?>" style="width: 250px;">
        <select name="status" class="form-select" style="width: 150px;" onchange="this.form.submit()">
            <option value="all">Tous</option>
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Actifs</option>
            <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Suspendus</option>
        </select>
        <button type="submit" class="btn btn-outline-primary"><i class="bi bi-search"></i></button>
    </form>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal"><i class="bi bi-plus-lg me-1"></i> Nouveau client</button>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Client</th><th>Email</th><th>Services</th><th>Impayes</th><th>Statut</th><th>Inscrit le</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($clients as $c): ?>
            <tr>
                <td><a href="<?= wp_url("admin/?page=client-detail&id={$c['id']}") ?>" class="fw-semibold"><?= wp_escape($c['first_name'] . ' ' . $c['last_name']) ?></a>
                    <?php if ($c['company']): ?><br><small class="text-muted"><?= wp_escape($c['company']) ?></small><?php endif; ?>
                </td>
                <td><?= wp_escape($c['email']) ?></td>
                <td><span class="badge bg-primary"><?= $c['active_subs'] ?></span></td>
                <td><?php if ($c['overdue_amount'] > 0): ?><span class="text-danger fw-bold"><?= wp_format_price($c['overdue_amount']) ?></span><?php else: ?>-<?php endif; ?></td>
                <td><span class="badge bg-<?= $c['status'] === 'active' ? 'success' : ($c['status'] === 'suspended' ? 'warning' : 'danger') ?>"><?= $c['status'] ?></span></td>
                <td><?= wp_format_date($c['created_at']) ?></td>
                <td>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?= wp_url("admin/?page=client-detail&id={$c['id']}") ?>"><i class="bi bi-eye me-2"></i>Voir</a></li>
                            <li>
                                <form method="POST" class="d-inline">
                                    <?= wp_csrf_field() ?>
                                    <input type="hidden" name="client_id" value="<?= $c['id'] ?>">
                                    <?php if ($c['status'] === 'active'): ?>
                                        <button type="submit" name="action" value="suspend" class="dropdown-item text-warning"><i class="bi bi-pause me-2"></i>Suspendre</button>
                                    <?php else: ?>
                                        <button type="submit" name="action" value="activate" class="dropdown-item text-success"><i class="bi bi-play me-2"></i>Reactiver</button>
                                    <?php endif; ?>
                                </form>
                            </li>
                        </ul>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?= wp_pagination_html($pagination, wp_url("admin/?page=clients&q=" . urlencode($search) . "&status=$status")) ?>

<!-- Create Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?= wp_csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-header"><h5 class="modal-title">Nouveau client</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-6"><label class="form-label">Prenom *</label><input type="text" name="first_name" class="form-control" required></div>
                    <div class="col-6"><label class="form-label">Nom *</label><input type="text" name="last_name" class="form-control" required></div>
                    <div class="col-12"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
                    <div class="col-12"><label class="form-label">Mot de passe</label><input type="text" name="password" class="form-control" placeholder="Auto-genere si vide"></div>
                    <div class="col-6"><label class="form-label">Societe</label><input type="text" name="company" class="form-control"></div>
                    <div class="col-6"><label class="form-label">Telephone</label><input type="tel" name="phone" class="form-control"></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-primary">Creer</button></div>
        </form>
    </div>
</div>
