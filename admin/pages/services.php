<?php
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'save') {
        $data = [
            'name' => trim($_POST['name']),
            'category_id' => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
            'description' => trim($_POST['description'] ?? ''),
            'duration' => (int)$_POST['duration'],
            'price' => (float)$_POST['price'],
            'color' => $_POST['color'] ?? '#e91e63',
            'deposit_enabled' => isset($_POST['deposit_enabled']) ? 1 : 0,
            'deposit_type' => $_POST['deposit_type'] ?? 'percentage',
            'deposit_amount' => (float)($_POST['deposit_amount'] ?? 0),
            'buffer_before' => (int)($_POST['buffer_before'] ?? 0),
            'buffer_after' => (int)($_POST['buffer_after'] ?? 0),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        if (!empty($_POST['id'])) {
            $db->update('ab_services', $data, 'id = ?', [(int)$_POST['id']]);
            ab_flash('success', 'Prestation mise à jour.');
        } else {
            $id = $db->insert('ab_services', $data);
            // Assign to all providers by default
            $providers = $db->fetchAll("SELECT id FROM ab_users WHERE is_active = 1");
            foreach ($providers as $p) {
                $db->insert('ab_provider_services', ['provider_id' => $p['id'], 'service_id' => $id]);
            }
            ab_flash('success', 'Prestation créée.');
        }
        ab_redirect(ab_url('admin/index.php?page=services'));
    }

    if ($postAction === 'delete' && isset($_POST['id'])) {
        $db->delete('ab_services', 'id = ?', [(int)$_POST['id']]);
        ab_flash('success', 'Prestation supprimée.');
        ab_redirect(ab_url('admin/index.php?page=services'));
    }
}

$action = $_GET['action'] ?? 'list';
$categories = $db->fetchAll("SELECT * FROM ab_service_categories WHERE is_active = 1 ORDER BY sort_order, name");

if ($action === 'edit' || $action === 'create'):
    $service = null;
    if ($action === 'edit' && isset($_GET['id'])) {
        $service = $db->fetchOne("SELECT * FROM ab_services WHERE id = ?", [(int)$_GET['id']]);
    }
?>
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-palette"></i> <?= $service ? 'Modifier' : 'Nouvelle' ?> prestation</h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="save">
            <?php if ($service): ?><input type="hidden" name="id" value="<?= $service['id'] ?>"><?php endif; ?>

            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Nom de la prestation *</label>
                    <input type="text" name="name" class="form-control" required value="<?= ab_escape($service['name'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Catégorie</label>
                    <select name="category_id" class="form-select">
                        <option value="">Aucune</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($service['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>><?= ab_escape($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2"><?= ab_escape($service['description'] ?? '') ?></textarea>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Durée (minutes) *</label>
                    <input type="number" name="duration" class="form-control" required min="5" step="5" value="<?= $service['duration'] ?? 60 ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Prix (€) *</label>
                    <input type="number" name="price" class="form-control" required min="0" step="0.01" value="<?= $service['price'] ?? '0.00' ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Couleur</label>
                    <input type="color" name="color" class="form-control form-control-color w-100" value="<?= $service['color'] ?? '#e91e63' ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Ordre d'affichage</label>
                    <input type="number" name="sort_order" class="form-control" value="<?= $service['sort_order'] ?? 0 ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Tampon avant (min)</label>
                    <input type="number" name="buffer_before" class="form-control" min="0" step="5" value="<?= $service['buffer_before'] ?? 0 ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tampon après (min)</label>
                    <input type="number" name="buffer_after" class="form-control" min="0" step="5" value="<?= $service['buffer_after'] ?? 0 ?>">
                </div>

                <div class="col-12"><hr></div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input type="checkbox" name="deposit_enabled" class="form-check-input" id="depositEnabled" <?= ($service['deposit_enabled'] ?? 0) ? 'checked' : '' ?> onchange="document.getElementById('depositConfig').style.display = this.checked ? 'flex' : 'none'">
                        <label class="form-check-label" for="depositEnabled"><strong>Demander un acompte</strong></label>
                    </div>
                </div>

                <div class="col-12 row g-3" id="depositConfig" style="display: <?= ($service['deposit_enabled'] ?? 0) ? 'flex' : 'none' ?>;">
                    <div class="col-md-4">
                        <label class="form-label">Type d'acompte</label>
                        <select name="deposit_type" class="form-select">
                            <option value="percentage" <?= ($service['deposit_type'] ?? '') === 'percentage' ? 'selected' : '' ?>>Pourcentage du prix</option>
                            <option value="fixed" <?= ($service['deposit_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Montant fixe</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Montant / Pourcentage</label>
                        <input type="number" name="deposit_amount" class="form-control" min="0" step="0.01" value="<?= $service['deposit_amount'] ?? 30 ?>">
                        <small class="text-muted">Ex: 30 = 30% ou 30€ selon le type</small>
                    </div>
                </div>

                <div class="col-12"><hr></div>

                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input type="checkbox" name="is_active" class="form-check-input" id="isActive" <?= ($service['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isActive">Active</label>
                    </div>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check"></i> Enregistrer</button>
                    <a href="<?= ab_url('admin/index.php?page=services') ?>" class="btn btn-outline-secondary">Annuler</a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php else: // List ?>
<?php $services = $db->fetchAll("SELECT s.*, sc.name as category_name FROM ab_services s LEFT JOIN ab_service_categories sc ON s.category_id = sc.id ORDER BY s.sort_order, s.name"); ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-palette"></i> Prestations</h4>
    <a href="<?= ab_url('admin/index.php?page=services&action=create') ?>" class="btn btn-primary"><i class="bi bi-plus"></i> Nouvelle prestation</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Couleur</th><th>Nom</th><th>Catégorie</th><th>Durée</th><th>Prix</th><th>Acompte</th><th>Active</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($services as $s): ?>
            <tr>
                <td><span style="display:inline-block;width:20px;height:20px;border-radius:50%;background:<?= ab_escape($s['color']) ?>"></span></td>
                <td><strong><?= ab_escape($s['name']) ?></strong></td>
                <td><?= ab_escape($s['category_name'] ?? '-') ?></td>
                <td><?= $s['duration'] ?> min</td>
                <td><?= ab_format_price($s['price']) ?></td>
                <td>
                    <?php if ($s['deposit_enabled']): ?>
                    <span class="badge bg-info"><?= $s['deposit_type'] === 'percentage' ? $s['deposit_amount'] . '%' : ab_format_price($s['deposit_amount']) ?></span>
                    <?php else: ?>
                    <span class="text-muted">-</span>
                    <?php endif; ?>
                </td>
                <td><?= $s['is_active'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-danger"></i>' ?></td>
                <td>
                    <a href="<?= ab_url('admin/index.php?page=services&action=edit&id=' . $s['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer cette prestation ?')">
                        <?= Auth::csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
