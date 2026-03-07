<?php
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    if ($postAction === 'save') {
        $data = [
            'name' => trim($_POST['name']),
            'description' => trim($_POST['description'] ?? ''),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
        if (!empty($_POST['id'])) {
            $db->update('ab_service_categories', $data, 'id = ?', [(int)$_POST['id']]);
        } else {
            $db->insert('ab_service_categories', $data);
        }
        ab_flash('success', 'Catégorie enregistrée.');
        ab_redirect(ab_url('admin/index.php?page=categories'));
    }
    if ($postAction === 'delete' && isset($_POST['id'])) {
        $db->delete('ab_service_categories', 'id = ?', [(int)$_POST['id']]);
        ab_flash('success', 'Catégorie supprimée.');
        ab_redirect(ab_url('admin/index.php?page=categories'));
    }
}

$categories = $db->fetchAll("SELECT sc.*, COUNT(s.id) as service_count FROM ab_service_categories sc LEFT JOIN ab_services s ON sc.id = s.category_id GROUP BY sc.id ORDER BY sc.sort_order, sc.name");
$editCat = isset($_GET['edit']) ? $db->fetchOne("SELECT * FROM ab_service_categories WHERE id = ?", [(int)$_GET['edit']]) : null;
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-tags"></i> Catégories</h4>
</div>

<div class="row">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header bg-white"><h6 class="mb-0"><?= $editCat ? 'Modifier' : 'Nouvelle' ?> catégorie</h6></div>
            <div class="card-body">
                <form method="POST">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="action" value="save">
                    <?php if ($editCat): ?><input type="hidden" name="id" value="<?= $editCat['id'] ?>"><?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Nom *</label>
                        <input type="text" name="name" class="form-control" required value="<?= ab_escape($editCat['name'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"><?= ab_escape($editCat['description'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ordre</label>
                        <input type="number" name="sort_order" class="form-control" value="<?= $editCat['sort_order'] ?? 0 ?>">
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input type="checkbox" name="is_active" class="form-check-input" <?= ($editCat['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label">Active</label>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check"></i> Enregistrer</button>
                    <?php if ($editCat): ?>
                    <a href="<?= ab_url('admin/index.php?page=categories') ?>" class="btn btn-outline-secondary">Annuler</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Nom</th><th>Prestations</th><th>Active</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($categories as $c): ?>
                    <tr>
                        <td><strong><?= ab_escape($c['name']) ?></strong></td>
                        <td><?= $c['service_count'] ?></td>
                        <td><?= $c['is_active'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-danger"></i>' ?></td>
                        <td>
                            <a href="<?= ab_url('admin/index.php?page=categories&edit=' . $c['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer ?')">
                                <?= Auth::csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
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
