<?php
Auth::requireAdmin();
$db = Database::getInstance();
$action = $_GET['action'] ?? 'list';

// ── POST handlers ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'save') {
        $id          = (int)($_POST['id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priceType   = in_array($_POST['price_type'] ?? '', ['fixed', 'percentage']) ? $_POST['price_type'] : 'fixed';
        $priceValue  = max(0, (float)($_POST['price_value'] ?? 0));
        $isActive    = isset($_POST['is_active']) ? 1 : 0;
        $sortOrder   = (int)($_POST['sort_order'] ?? 0);
        $serviceIds  = array_map('intval', $_POST['service_ids'] ?? []);

        if (!$name) {
            ab_flash('error', 'Le nom est requis.');
            ab_redirect(ab_url('admin/index.php?page=booking-options&action=' . ($id ? 'edit&id=' . $id : 'create')));
        }

        $data = compact('name', 'description') + [
            'price_type'  => $priceType,
            'price_value' => $priceValue,
            'is_active'   => $isActive,
            'sort_order'  => $sortOrder,
        ];

        if ($id) {
            $db->update('ab_booking_options', $data, 'id = ?', [$id]);
        } else {
            $id = $db->insert('ab_booking_options', $data);
        }

        // Update service restrictions
        $db->delete('ab_booking_option_services', 'option_id = ?', [$id]);
        foreach ($serviceIds as $sid) {
            if ($sid > 0) {
                $db->query("INSERT IGNORE INTO ab_booking_option_services (option_id, service_id) VALUES (?, ?)", [$id, $sid]);
            }
        }

        ab_flash('success', 'Option enregistrée.');
        ab_redirect(ab_url('admin/index.php?page=booking-options'));
    }

    if ($postAction === 'delete' && isset($_POST['id'])) {
        $db->delete('ab_booking_options', 'id = ?', [(int)$_POST['id']]);
        ab_flash('success', 'Option supprimée.');
        ab_redirect(ab_url('admin/index.php?page=booking-options'));
    }
}

// ── Edit/Create form ───────────────────────────────────────────────────────────
if ($action === 'edit' || $action === 'create') {
    $opt = $action === 'edit'
        ? $db->fetchOne("SELECT * FROM ab_booking_options WHERE id = ?", [(int)($_GET['id'] ?? 0)])
        : ['id' => 0, 'name' => '', 'description' => '', 'price_type' => 'fixed', 'price_value' => 0, 'is_active' => 1, 'sort_order' => 0];

    if (!$opt) { ab_flash('error', 'Option introuvable.'); ab_redirect(ab_url('admin/index.php?page=booking-options')); }

    $linkedServiceIds = array_column(
        $db->fetchAll("SELECT service_id FROM ab_booking_option_services WHERE option_id = ?", [$opt['id']]),
        'service_id'
    );
    $allServices = $db->fetchAll("SELECT id, name FROM ab_services WHERE is_active = 1 ORDER BY sort_order, name");
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-plus-square"></i> <?= $action === 'create' ? 'Nouvelle option' : 'Modifier l\'option' ?></h4>
    <a href="<?= ab_url('admin/index.php?page=booking-options') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Retour</a>
</div>
<div class="card">
    <div class="card-body">
        <form method="POST">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $opt['id'] ?>">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Nom de l'option *</label>
                    <input type="text" name="name" class="form-control" required value="<?= ab_escape($opt['name']) ?>" placeholder="Ex : Nail art simple, Vernis semi-permanent…">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Ordre d'affichage</label>
                    <input type="number" name="sort_order" class="form-control" value="<?= (int)$opt['sort_order'] ?>" min="0">
                </div>
                <div class="col-12">
                    <label class="form-label">Description <small class="text-muted">(facultative)</small></label>
                    <input type="text" name="description" class="form-control" value="<?= ab_escape($opt['description'] ?? '') ?>" placeholder="Courte description affichée sous le nom">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Type de tarif</label>
                    <select name="price_type" class="form-select" id="priceTypeSelect">
                        <option value="fixed" <?= ($opt['price_type'] ?? 'fixed') === 'fixed' ? 'selected' : '' ?>>Montant fixe (€)</option>
                        <option value="percentage" <?= ($opt['price_type'] ?? '') === 'percentage' ? 'selected' : '' ?>>Pourcentage (%)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" id="priceValueLabel">Montant (€)</label>
                    <input type="number" name="price_value" class="form-control" min="0" step="0.01" value="<?= number_format((float)$opt['price_value'], 2, '.', '') ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check form-switch mb-2">
                        <input type="checkbox" name="is_active" class="form-check-input" <?= !empty($opt['is_active']) ? 'checked' : '' ?>>
                        <label class="form-check-label">Active</label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Prestations concernées</label>
                    <div class="border rounded p-3" style="max-height:200px;overflow-y:auto;">
                        <div class="mb-2">
                            <small class="text-muted"><i class="bi bi-info-circle"></i> Laissez tout décoché pour que l'option s'applique à <strong>toutes</strong> les prestations.</small>
                        </div>
                        <?php foreach ($allServices as $svc): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="service_ids[]" value="<?= $svc['id'] ?>"
                                   id="svc-<?= $svc['id'] ?>" <?= in_array($svc['id'], $linkedServiceIds) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="svc-<?= $svc['id'] ?>"><?= ab_escape($svc['name']) ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <hr>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check"></i> Enregistrer</button>
        </form>
    </div>
</div>
<script>
document.getElementById('priceTypeSelect').addEventListener('change', function() {
    document.getElementById('priceValueLabel').textContent = this.value === 'percentage' ? 'Pourcentage (%)' : 'Montant (€)';
});
</script>
<?php
    return;
}

// ── List view ──────────────────────────────────────────────────────────────────
$options = $db->fetchAll("SELECT o.*,
    (SELECT GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ', ')
     FROM ab_booking_option_services bos
     JOIN ab_services s ON bos.service_id = s.id
     WHERE bos.option_id = o.id) as service_names
    FROM ab_booking_options o ORDER BY o.sort_order, o.name");
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-plus-square"></i> Options de réservation</h4>
    <a href="<?= ab_url('admin/index.php?page=booking-options&action=create') ?>" class="btn btn-primary btn-sm">
        <i class="bi bi-plus"></i> Nouvelle option
    </a>
</div>
<div class="card">
    <div class="card-body p-0">
        <?php if (empty($options)): ?>
        <p class="text-muted p-4 mb-0">Aucune option définie. Les options permettent aux clients de personnaliser leur réservation (ex : nail art, vernis spécial…).</p>
        <?php else: ?>
        <table class="table table-hover mb-0">
            <thead><tr><th>Nom</th><th>Tarif</th><th>Prestations</th><th>Statut</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($options as $o): ?>
            <tr>
                <td>
                    <strong><?= ab_escape($o['name']) ?></strong>
                    <?php if ($o['description']): ?>
                    <br><small class="text-muted"><?= ab_escape($o['description']) ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($o['price_value'] == 0): ?>
                    <span class="badge bg-secondary">Gratuit</span>
                    <?php elseif ($o['price_type'] === 'percentage'): ?>
                    <span class="badge bg-info">+<?= number_format($o['price_value'], 2, ',', ' ') ?>%</span>
                    <?php else: ?>
                    <span class="badge bg-success">+<?= ab_format_price($o['price_value']) ?></span>
                    <?php endif; ?>
                </td>
                <td><small class="text-muted"><?= $o['service_names'] ? ab_escape($o['service_names']) : '<em>Toutes</em>' ?></small></td>
                <td>
                    <?php if ($o['is_active']): ?>
                    <span class="badge bg-success">Active</span>
                    <?php else: ?>
                    <span class="badge bg-secondary">Inactive</span>
                    <?php endif; ?>
                </td>
                <td class="text-end">
                    <a href="<?= ab_url('admin/index.php?page=booking-options&action=edit&id=' . $o['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer cette option ?')">
                        <?= Auth::csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $o['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
