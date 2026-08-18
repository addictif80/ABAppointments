<?php
$db = Database::getInstance();
$providerId = Auth::isAdmin() ? null : Auth::userId();

$modeLabels = [
    'normal' => 'Normal (annule la règle du jour de semaine)',
    'single_appointment' => '1 seul RDV ce jour',
    'closed' => 'Fermé',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    if ($postAction === 'save') {
        $data = [
            'provider_id' => !empty($_POST['provider_id']) ? (int)$_POST['provider_id'] : null,
            'exception_date' => $_POST['exception_date'],
            'mode' => in_array($_POST['mode'] ?? '', ['normal', 'single_appointment', 'closed'], true) ? $_POST['mode'] : 'normal',
            'note' => trim($_POST['note'] ?? ''),
        ];
        if (!Auth::isAdmin()) {
            $data['provider_id'] = $providerId;
        }
        if (!empty($_POST['id'])) {
            $db->update('ab_day_exceptions', $data, 'id = ?', [(int)$_POST['id']]);
        } else {
            $db->insert('ab_day_exceptions', $data);
        }
        ab_flash('success', 'Exception enregistrée.');
        ab_redirect(ab_url('admin/index.php?page=day-exceptions'));
    }
    if ($postAction === 'delete' && isset($_POST['id'])) {
        $db->delete('ab_day_exceptions', 'id = ?', [(int)$_POST['id']]);
        ab_flash('success', 'Exception supprimée.');
        ab_redirect(ab_url('admin/index.php?page=day-exceptions'));
    }
}

$sql = "SELECT e.*, u.first_name, u.last_name FROM ab_day_exceptions e LEFT JOIN ab_users u ON e.provider_id = u.id";
if ($providerId) $sql .= " WHERE e.provider_id = $providerId OR e.provider_id IS NULL";
$sql .= " ORDER BY e.exception_date DESC";
$exceptions = $db->fetchAll($sql);
$providers = Auth::isAdmin() ? $db->fetchAll("SELECT * FROM ab_users WHERE is_active = 1 ORDER BY first_name") : [];
$editException = isset($_GET['edit']) ? $db->fetchOne("SELECT * FROM ab_day_exceptions WHERE id = ?", [(int)$_GET['edit']]) : null;
?>

<h4 class="mb-3"><i class="bi bi-calendar2-week"></i> Exceptions ponctuelles</h4>
<p class="text-muted">Surcharge la règle habituelle du jour de semaine pour une date précise : fermeture exceptionnelle, limitation à 1 seul rendez-vous, ou réouverture normale (ex. "sauf le 25/12").</p>
<div class="row">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header bg-white"><h6 class="mb-0"><?= $editException ? 'Modifier' : 'Nouvelle' ?> exception</h6></div>
            <div class="card-body">
                <form method="POST">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="action" value="save">
                    <?php if ($editException): ?><input type="hidden" name="id" value="<?= $editException['id'] ?>"><?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Date *</label>
                        <input type="date" name="exception_date" class="form-control" required value="<?= $editException['exception_date'] ?? '' ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mode *</label>
                        <select name="mode" class="form-select" required>
                            <?php foreach ($modeLabels as $key => $label): ?>
                            <option value="<?= $key ?>" <?= ($editException['mode'] ?? '') === $key ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if (Auth::isAdmin()): ?>
                    <div class="mb-3">
                        <label class="form-label">Prestataire</label>
                        <select name="provider_id" class="form-select">
                            <option value="">Tous les prestataires</option>
                            <?php foreach ($providers as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ($editException['provider_id'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= ab_escape($p['first_name'] . ' ' . $p['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Note</label>
                        <input type="text" name="note" class="form-control" value="<?= ab_escape($editException['note'] ?? '') ?>" placeholder="Ex : Réveillon">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check"></i> Enregistrer</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Date</th><th>Mode</th><th>Prestataire</th><th>Note</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($exceptions as $e): ?>
                    <tr>
                        <td><?= ab_format_date($e['exception_date']) ?></td>
                        <td><?= ab_escape($modeLabels[$e['mode']] ?? $e['mode']) ?></td>
                        <td><?= $e['first_name'] ? ab_escape($e['first_name'] . ' ' . $e['last_name']) : '<em>Tous</em>' ?></td>
                        <td><?= ab_escape($e['note'] ?? '') ?></td>
                        <td>
                            <a href="<?= ab_url('admin/index.php?page=day-exceptions&edit=' . $e['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer ?')">
                                <?= Auth::csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
