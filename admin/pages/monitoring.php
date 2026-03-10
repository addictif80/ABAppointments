<?php
$pageTitle = 'Monitoring';
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $db->insert('wp_monitors', [
            'kuma_monitor_id' => (int)($_POST['kuma_monitor_id'] ?? 0) ?: null,
            'name' => trim($_POST['name'] ?? ''),
            'type' => $_POST['type'] ?? 'http',
            'url' => trim($_POST['url'] ?? ''),
            'related_product_type' => $_POST['related_product_type'] ?? 'infrastructure',
            'is_public' => isset($_POST['is_public']) ? 1 : 0
        ]);
        wp_flash('success', 'Moniteur ajoute.');
    } elseif ($action === 'delete') {
        $db->delete('wp_monitors', 'id = ?', [(int)$_POST['monitor_id']]);
        wp_flash('success', 'Moniteur supprime.');
    } elseif ($action === 'sync') {
        $kuma = new UptimeKumaAPI();
        if ($kuma->syncMonitors()) {
            wp_flash('success', 'Synchronisation terminee.');
        } else {
            wp_flash('error', 'Echec de la synchronisation.');
        }
    }
    wp_redirect(wp_url('admin/?page=monitoring'));
}

$monitors = $db->fetchAll("SELECT * FROM wp_monitors ORDER BY status DESC, name ASC");
$statusIcons = ['up' => 'check-circle text-success', 'down' => 'x-circle text-danger', 'pending' => 'clock text-warning', 'maintenance' => 'wrench text-info'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <form method="POST" class="d-inline"><?= wp_csrf_field() ?><button name="action" value="sync" class="btn btn-outline-primary"><i class="bi bi-arrow-repeat me-1"></i> Sync Uptime Kuma</button></form>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#monitorModal"><i class="bi bi-plus-lg me-1"></i> Ajouter</button>
</div>

<div class="row g-3">
    <?php foreach ($monitors as $m): ?>
    <div class="col-md-6 col-xl-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-1"><i class="bi bi-<?= $statusIcons[$m['status']] ?? 'question-circle text-muted' ?> me-2"></i><?= wp_escape($m['name']) ?></h6>
                        <small class="text-muted"><?= wp_escape($m['url'] ?: 'N/A') ?></small>
                    </div>
                    <span class="badge bg-<?= $m['related_product_type'] === 'infrastructure' ? 'dark' : 'primary' ?>"><?= $m['related_product_type'] ?></span>
                </div>
                <div class="mt-3 d-flex justify-content-between">
                    <div><small class="text-muted">Uptime 24h</small><br><strong><?= $m['uptime_24h'] !== null ? $m['uptime_24h'] . '%' : 'N/A' ?></strong></div>
                    <div><small class="text-muted">Uptime 30j</small><br><strong><?= $m['uptime_30d'] !== null ? $m['uptime_30d'] . '%' : 'N/A' ?></strong></div>
                    <div><small class="text-muted">Dernier check</small><br><strong><?= $m['last_check'] ? wp_format_datetime($m['last_check']) : 'N/A' ?></strong></div>
                </div>
                <div class="mt-2">
                    <form method="POST" class="d-inline"><?= wp_csrf_field() ?><input type="hidden" name="monitor_id" value="<?= $m['id'] ?>">
                        <button name="action" value="delete" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer ?')"><i class="bi bi-trash"></i></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if (empty($monitors)): ?>
<div class="text-center py-5 text-muted"><i class="bi bi-activity fs-1"></i><p class="mt-2">Aucun moniteur configure</p></div>
<?php endif; ?>

<div class="modal fade" id="monitorModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?= wp_csrf_field() ?><input type="hidden" name="action" value="create">
            <div class="modal-header"><h5 class="modal-title">Ajouter un moniteur</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Nom *</label><input type="text" name="name" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">URL</label><input type="text" name="url" class="form-control"></div>
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Type</label><select name="type" class="form-select"><option value="http">HTTP</option><option value="ping">Ping</option><option value="port">Port</option></select></div>
                    <div class="col-md-4"><label class="form-label">Categorie</label><select name="related_product_type" class="form-select"><option value="infrastructure">Infrastructure</option><option value="vps">VPS</option><option value="hosting">Hosting</option><option value="navidrome">Navidrome</option></select></div>
                    <div class="col-md-4"><label class="form-label">Kuma ID</label><input type="number" name="kuma_monitor_id" class="form-control"></div>
                </div>
                <div class="mt-3 form-check"><input type="checkbox" name="is_public" class="form-check-input" id="isPublic"><label for="isPublic" class="form-check-label">Visible publiquement</label></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-primary">Ajouter</button></div>
        </form>
    </div>
</div>
