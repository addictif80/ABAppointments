<?php
$pageTitle = 'Templates OS';
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create' || $action === 'update') {
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'slug' => trim($_POST['slug'] ?? '') ?: strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($_POST['name'] ?? ''))),
            'proxmox_template' => trim($_POST['proxmox_template'] ?? ''),
            'icon' => trim($_POST['icon'] ?? ''),
            'category' => $_POST['category'] ?? 'linux',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'sort_order' => (int)($_POST['sort_order'] ?? 0)
        ];
        if ($action === 'create') {
            $db->insert('wp_os_templates', $data);
            wp_flash('success', 'Template ajoute.');
        } else {
            $db->update('wp_os_templates', $data, 'id = ?', [(int)$_POST['template_id']]);
            wp_flash('success', 'Template mis a jour.');
        }
    } elseif ($action === 'delete') {
        $db->delete('wp_os_templates', 'id = ?', [(int)$_POST['template_id']]);
        wp_flash('success', 'Template supprime.');
    }
    wp_redirect(wp_url('admin/?page=os-templates'));
}

$templates = $db->fetchAll("SELECT * FROM wp_os_templates ORDER BY sort_order, name");
?>

<div class="d-flex justify-content-end mb-4">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#osModal" onclick="document.getElementById('osForm').reset(); document.getElementById('osAction').value='create'; document.getElementById('osTitle').textContent='Nouveau template'"><i class="bi bi-plus-lg me-1"></i> Ajouter</button>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Ordre</th><th>Nom</th><th>Slug</th><th>Template Proxmox</th><th>Categorie</th><th>Actif</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($templates as $t): ?>
            <tr>
                <td><?= $t['sort_order'] ?></td>
                <td class="fw-semibold"><?= wp_os_icon($t['icon']) ?> <?= wp_escape($t['name']) ?></td>
                <td><code><?= wp_escape($t['slug']) ?></code></td>
                <td class="small"><?= wp_escape($t['proxmox_template']) ?></td>
                <td><?= ucfirst($t['category']) ?></td>
                <td><?= $t['is_active'] ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-x-circle text-danger"></i>' ?></td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick='editOS(<?= json_encode($t) ?>)'><i class="bi bi-pencil"></i></button>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer ?')"><?= wp_csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="template_id" value="<?= $t['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="osModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content" id="osForm"><?= wp_csrf_field() ?>
            <input type="hidden" name="action" value="create" id="osAction">
            <input type="hidden" name="template_id" id="osId">
            <div class="modal-header"><h5 class="modal-title" id="osTitle">Nouveau template</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Nom *</label><input type="text" name="name" id="osName" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Slug</label><input type="text" name="slug" id="osSlug" class="form-control"></div>
                <div class="mb-3"><label class="form-label">Template Proxmox *</label><input type="text" name="proxmox_template" id="osTpl" class="form-control" required placeholder="local:vztmpl/debian-12-..."></div>
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Icone</label><input type="text" name="icon" id="osIcon" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label">Categorie</label><select name="category" id="osCat" class="form-select"><option value="linux">Linux</option><option value="windows">Windows</option></select></div>
                    <div class="col-md-4"><label class="form-label">Ordre</label><input type="number" name="sort_order" id="osSort" class="form-control" value="0"></div>
                </div>
                <div class="mt-3 form-check"><input type="checkbox" name="is_active" id="osActive" class="form-check-input" checked><label for="osActive" class="form-check-label">Actif</label></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-primary">Enregistrer</button></div>
        </form>
    </div>
</div>

<script>
function editOS(t) {
    document.getElementById('osAction').value = 'update';
    document.getElementById('osId').value = t.id;
    document.getElementById('osTitle').textContent = 'Modifier: ' + t.name;
    document.getElementById('osName').value = t.name;
    document.getElementById('osSlug').value = t.slug;
    document.getElementById('osTpl').value = t.proxmox_template;
    document.getElementById('osIcon').value = t.icon || '';
    document.getElementById('osCat').value = t.category;
    document.getElementById('osSort').value = t.sort_order;
    document.getElementById('osActive').checked = t.is_active == 1;
    new bootstrap.Modal(document.getElementById('osModal')).show();
}
</script>
