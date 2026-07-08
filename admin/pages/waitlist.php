<?php
$waitlistManager = new Waitlist();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'delete' && isset($_POST['id'])) {
        $waitlistManager->delete((int)$_POST['id']);
        ab_flash('success', 'Entrée supprimée de la liste d\'attente.');
        ab_redirect(ab_url('admin/index.php?page=waitlist'));
    }
}

$statusFilter = $_GET['status'] ?? 'waiting';
$entries = $waitlistManager->getAll($statusFilter);
?>

<h4 class="mb-3"><i class="bi bi-bell"></i> Liste d'attente</h4>

<div class="card">
    <div class="card-header bg-white">
        <div class="d-flex gap-2">
            <a href="<?= ab_url('admin/index.php?page=waitlist&status=waiting') ?>" class="btn btn-sm <?= $statusFilter === 'waiting' ? 'btn-warning' : 'btn-outline-warning' ?>">En attente</a>
            <a href="<?= ab_url('admin/index.php?page=waitlist&status=notified') ?>" class="btn btn-sm <?= $statusFilter === 'notified' ? 'btn-info' : 'btn-outline-info' ?>">Notifiés</a>
            <a href="<?= ab_url('admin/index.php?page=waitlist&status=') ?>" class="btn btn-sm <?= $statusFilter === '' ? 'btn-primary' : 'btn-outline-primary' ?>">Tous</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Client</th><th>Prestation</th><th>Prestataire</th><th>Période souhaitée</th><th>Statut</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($entries as $e): ?>
            <tr>
                <td>
                    <strong><?= ab_escape($e['first_name'] . ' ' . $e['last_name']) ?></strong>
                    <br><small class="text-muted"><?= ab_escape($e['email']) ?><?= $e['phone'] ? ' · ' . ab_escape($e['phone']) : '' ?></small>
                </td>
                <td><?= ab_escape($e['service_name']) ?></td>
                <td><?= $e['provider_first_name'] ? ab_escape($e['provider_first_name'] . ' ' . $e['provider_last_name']) : '<span class="text-muted">Indifférent</span>' ?></td>
                <td><?= ab_format_date($e['desired_date_start']) ?> - <?= ab_format_date($e['desired_date_end']) ?></td>
                <td><span class="badge badge-<?= $e['status'] ?>"><?= $e['status'] ?></span></td>
                <td>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer cette entrée ?')">
                        <?= Auth::csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $e['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($entries)): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">Aucune entrée</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
