<?php
Auth::requireAdmin();
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $db->update('ab_email_templates', [
        'subject' => trim($_POST['subject']),
        'body' => $_POST['body'],
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ], 'id = ?', [(int)$_POST['id']]);
    ab_flash('success', 'Template mis à jour.');
    ab_redirect(ab_url('admin/index.php?page=email-templates'));
}

$templates = $db->fetchAll("SELECT * FROM ab_email_templates ORDER BY id");
$editTemplate = isset($_GET['edit']) ? $db->fetchOne("SELECT * FROM ab_email_templates WHERE id = ?", [(int)$_GET['edit']]) : null;
?>

<h4 class="mb-3"><i class="bi bi-envelope"></i> Templates email</h4>

<?php if ($editTemplate): ?>
<div class="card">
    <div class="card-header bg-white"><h5 class="mb-0">Modifier : <?= ab_escape($editTemplate['name']) ?></h5></div>
    <div class="card-body">
        <form method="POST">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="id" value="<?= $editTemplate['id'] ?>">
            <div class="mb-3">
                <label class="form-label">Sujet</label>
                <input type="text" name="subject" class="form-control" value="<?= ab_escape($editTemplate['subject']) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Corps du mail (HTML)</label>
                <textarea name="body" class="form-control" rows="15" style="font-family:monospace;font-size:13px;"><?= ab_escape($editTemplate['body']) ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Variables disponibles :</label>
                <div class="d-flex flex-wrap gap-1">
                    <?php foreach (json_decode($editTemplate['variables'] ?? '[]', true) as $v): ?>
                    <code class="badge bg-light text-dark border">{<?= $v ?>}</code>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-check form-switch mb-3">
                <input type="checkbox" name="is_active" class="form-check-input" <?= $editTemplate['is_active'] ? 'checked' : '' ?>>
                <label class="form-check-label">Actif</label>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check"></i> Enregistrer</button>
            <a href="<?= ab_url('admin/index.php?page=email-templates') ?>" class="btn btn-outline-secondary">Annuler</a>
        </form>
    </div>
</div>

<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Nom</th><th>Sujet</th><th>Actif</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($templates as $t): ?>
            <tr>
                <td><strong><?= ab_escape($t['name']) ?></strong><br><small class="text-muted"><?= $t['slug'] ?></small></td>
                <td><?= ab_escape($t['subject']) ?></td>
                <td><?= $t['is_active'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-danger"></i>' ?></td>
                <td><a href="<?= ab_url('admin/index.php?page=email-templates&edit=' . $t['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Modifier</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
