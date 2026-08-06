<?php
$reviewsManager = new Reviews();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'approve' && isset($_POST['id'])) {
        $reviewsManager->updateStatus((int)$_POST['id'], 'approved');
        ab_flash('success', 'Avis approuvé et visible publiquement.');
        ab_redirect(ab_url('admin/index.php?page=reviews'));
    }
    if (($_POST['action'] ?? '') === 'reject' && isset($_POST['id'])) {
        $reviewsManager->updateStatus((int)$_POST['id'], 'rejected');
        ab_flash('success', 'Avis rejeté.');
        ab_redirect(ab_url('admin/index.php?page=reviews'));
    }
    if (($_POST['action'] ?? '') === 'delete' && isset($_POST['id'])) {
        $reviewsManager->delete((int)$_POST['id']);
        ab_flash('success', 'Avis supprimé.');
        ab_redirect(ab_url('admin/index.php?page=reviews'));
    }
}

$statusFilter = $_GET['status'] ?? 'pending';
$reviewsList = $reviewsManager->getAll($statusFilter);
$stats = $reviewsManager->getStats();
?>

<h4 class="mb-3"><i class="bi bi-star"></i> Avis clients</h4>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card stat-card p-3" style="border-left-color: #ffc107;">
            <div class="text-muted small">Note moyenne</div>
            <div class="h4 mb-0"><?= $stats['avg_rating'] ?? '-' ?> / 5</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card p-3" style="border-left-color: #28a745;">
            <div class="text-muted small">Avis approuvés</div>
            <div class="h4 mb-0"><?= (int)$stats['total'] ?></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white">
        <div class="d-flex gap-2">
            <a href="<?= ab_url('admin/index.php?page=reviews&status=pending') ?>" class="btn btn-sm <?= $statusFilter === 'pending' ? 'btn-warning' : 'btn-outline-warning' ?>">En attente</a>
            <a href="<?= ab_url('admin/index.php?page=reviews&status=approved') ?>" class="btn btn-sm <?= $statusFilter === 'approved' ? 'btn-success' : 'btn-outline-success' ?>">Approuvés</a>
            <a href="<?= ab_url('admin/index.php?page=reviews&status=rejected') ?>" class="btn btn-sm <?= $statusFilter === 'rejected' ? 'btn-danger' : 'btn-outline-danger' ?>">Rejetés</a>
            <a href="<?= ab_url('admin/index.php?page=reviews&status=') ?>" class="btn btn-sm <?= $statusFilter === '' ? 'btn-primary' : 'btn-outline-primary' ?>">Tous</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Client</th><th>Prestation</th><th>Note</th><th>Commentaire</th><th>Statut</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($reviewsList as $r): ?>
            <tr>
                <td><?= ab_escape($r['customer_first_name'] . ' ' . $r['customer_last_name']) ?></td>
                <td><?= ab_escape($r['service_name']) ?></td>
                <td><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></td>
                <td style="max-width:300px;"><?= nl2br(ab_escape($r['comment'] ?? '')) ?></td>
                <td><span class="badge badge-<?= $r['status'] ?>"><?= $r['status'] ?></span></td>
                <td class="text-nowrap">
                    <?php if ($r['status'] !== 'approved'): ?>
                    <form method="POST" class="d-inline">
                        <?= Auth::csrfField() ?>
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <button class="btn btn-sm btn-outline-success"><i class="bi bi-check-circle"></i></button>
                    </form>
                    <?php endif; ?>
                    <?php if ($r['status'] !== 'rejected'): ?>
                    <form method="POST" class="d-inline">
                        <?= Auth::csrfField() ?>
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <button class="btn btn-sm btn-outline-warning"><i class="bi bi-x-circle"></i></button>
                    </form>
                    <?php endif; ?>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer cet avis ?')">
                        <?= Auth::csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($reviewsList)): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">Aucun avis</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
