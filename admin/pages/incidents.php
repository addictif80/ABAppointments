<?php
$pageTitle = 'Incidents';
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $db->insert('wp_incidents', [
            'monitor_id' => (int)($_POST['monitor_id'] ?? 0) ?: null,
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'severity' => $_POST['severity'] ?? 'minor',
            'status' => 'investigating'
        ]);
        wp_flash('success', 'Incident cree.');
    } elseif ($action === 'update_status') {
        $newStatus = $_POST['status'] ?? '';
        $data = ['status' => $newStatus];
        if ($newStatus === 'resolved') $data['resolved_at'] = date('Y-m-d H:i:s');
        $db->update('wp_incidents', $data, 'id = ?', [(int)$_POST['incident_id']]);
        wp_flash('success', 'Statut mis a jour.');
    }
    wp_redirect(wp_url('admin/?page=incidents'));
}

$incidents = $db->fetchAll(
    "SELECT i.*, m.name as monitor_name FROM wp_incidents i LEFT JOIN wp_monitors m ON i.monitor_id = m.id ORDER BY i.status != 'resolved', i.created_at DESC"
);
$monitors = $db->fetchAll("SELECT id, name FROM wp_monitors ORDER BY name");
$severityColors = ['minor' => 'warning', 'major' => 'orange', 'critical' => 'danger'];
$statusLabels = ['investigating' => 'Investigation', 'identified' => 'Identifie', 'monitoring' => 'Surveillance', 'resolved' => 'Resolu'];
?>

<div class="d-flex justify-content-end mb-4">
    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#incidentModal"><i class="bi bi-exclamation-triangle me-1"></i> Declarer un incident</button>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Titre</th><th>Moniteur</th><th>Severite</th><th>Statut</th><th>Debut</th><th>Resolution</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($incidents as $i): ?>
            <tr class="<?= $i['status'] === 'resolved' ? 'text-muted' : '' ?>">
                <td class="fw-semibold"><?= wp_escape($i['title']) ?></td>
                <td><?= wp_escape($i['monitor_name'] ?? '-') ?></td>
                <td><span class="badge bg-<?= $severityColors[$i['severity']] ?? 'secondary' ?>"><?= ucfirst($i['severity']) ?></span></td>
                <td><span class="badge bg-<?= $i['status'] === 'resolved' ? 'success' : 'warning' ?>"><?= $statusLabels[$i['status']] ?? $i['status'] ?></span></td>
                <td><?= wp_format_datetime($i['started_at']) ?></td>
                <td><?= $i['resolved_at'] ? wp_format_datetime($i['resolved_at']) : '-' ?></td>
                <td>
                    <?php if ($i['status'] !== 'resolved'): ?>
                    <form method="POST" class="d-inline"><?= wp_csrf_field() ?><input type="hidden" name="action" value="update_status"><input type="hidden" name="incident_id" value="<?= $i['id'] ?>">
                        <select name="status" class="form-select form-select-sm d-inline" style="width:auto" onchange="this.form.submit()">
                            <?php foreach ($statusLabels as $k => $v): ?>
                                <option value="<?= $k ?>" <?= $k === $i['status'] ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="incidentModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?= wp_csrf_field() ?><input type="hidden" name="action" value="create">
            <div class="modal-header"><h5 class="modal-title">Declarer un incident</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Titre *</label><input type="text" name="title" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Severite</label><select name="severity" class="form-select"><option value="minor">Mineure</option><option value="major">Majeure</option><option value="critical">Critique</option></select></div>
                    <div class="col-md-6"><label class="form-label">Moniteur</label><select name="monitor_id" class="form-select"><option value="">-- Aucun --</option>
                        <?php foreach ($monitors as $m): ?><option value="<?= $m['id'] ?>"><?= wp_escape($m['name']) ?></option><?php endforeach; ?>
                    </select></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-danger">Declarer</button></div>
        </form>
    </div>
</div>
