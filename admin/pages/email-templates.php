<?php
$pageTitle = 'Templates Email';
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $templateId = (int)($_POST['template_id'] ?? 0);
    if ($templateId) {
        $db->update('wp_email_templates', [
            'subject' => trim($_POST['subject'] ?? ''),
            'body_html' => $_POST['body_html'] ?? '',
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ], 'id = ?', [$templateId]);
        wp_flash('success', 'Template mis a jour.');
    }
    wp_redirect(wp_url('admin/?page=email-templates'));
}

$templates = $db->fetchAll("SELECT * FROM wp_email_templates ORDER BY name");
$editTemplate = null;
if (isset($_GET['edit'])) {
    $editTemplate = $db->fetchOne("SELECT * FROM wp_email_templates WHERE id = ?", [(int)$_GET['edit']]);
}
?>

<?php if ($editTemplate): ?>
<div class="card">
    <div class="card-header d-flex justify-content-between">
        <h6 class="mb-0">Modifier: <?= wp_escape($editTemplate['name']) ?> <code>(<?= wp_escape($editTemplate['slug']) ?>)</code></h6>
        <a href="<?= wp_url('admin/?page=email-templates') ?>" class="btn btn-sm btn-outline-secondary">Retour</a>
    </div>
    <div class="card-body">
        <form method="POST">
            <?= wp_csrf_field() ?>
            <input type="hidden" name="template_id" value="<?= $editTemplate['id'] ?>">
            <div class="mb-3"><label class="form-label">Sujet</label><input type="text" name="subject" class="form-control" value="<?= wp_escape($editTemplate['subject']) ?>"></div>
            <div class="mb-3"><label class="form-label">Corps HTML</label><textarea name="body_html" class="form-control font-monospace" rows="12"><?= wp_escape($editTemplate['body_html']) ?></textarea></div>
            <?php $vars = json_decode($editTemplate['variables'] ?? '[]', true); ?>
            <?php if ($vars): ?>
                <div class="mb-3"><label class="form-label">Variables disponibles</label><div>
                    <?php foreach ($vars as $v): ?><code class="me-2">{{<?= wp_escape($v) ?>}}</code><?php endforeach; ?>
                </div></div>
            <?php endif; ?>
            <div class="form-check mb-3"><input type="checkbox" name="is_active" class="form-check-input" id="tplActive" <?= $editTemplate['is_active'] ? 'checked' : '' ?>><label for="tplActive" class="form-check-label">Actif</label></div>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
        </form>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Nom</th><th>Slug</th><th>Sujet</th><th>Actif</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($templates as $t): ?>
            <tr>
                <td class="fw-semibold"><?= wp_escape($t['name']) ?></td>
                <td><code><?= wp_escape($t['slug']) ?></code></td>
                <td><?= wp_escape($t['subject']) ?></td>
                <td><?= $t['is_active'] ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-x-circle text-danger"></i>' ?></td>
                <td><a href="<?= wp_url("admin/?page=email-templates&edit={$t['id']}") ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Modifier</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
