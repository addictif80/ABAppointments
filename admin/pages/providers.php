<?php
Auth::requireAdmin();
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'save') {
        $data = [
            'first_name' => trim($_POST['first_name']),
            'last_name' => trim($_POST['last_name']),
            'email' => trim($_POST['email']),
            'phone' => trim($_POST['phone'] ?? ''),
            'role' => $_POST['role'] ?? 'provider',
            'welcome_message' => trim($_POST['welcome_message'] ?? ''),
            'is_visible_booking' => isset($_POST['is_visible_booking']) ? 1 : 0,
            'notes' => trim($_POST['notes'] ?? ''),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        if (!empty($_POST['id'])) {
            if (!empty($_POST['password'])) {
                $data['password'] = Auth::hashPassword($_POST['password']);
            }
            $db->update('ab_users', $data, 'id = ?', [(int)$_POST['id']]);
            ab_flash('success', 'Prestataire mis à jour.');
        } else {
            $data['password'] = Auth::hashPassword($_POST['password']);
            $id = $db->insert('ab_users', $data);
            // Assign all services
            $services = $db->fetchAll("SELECT id FROM ab_services");
            foreach ($services as $s) {
                $db->insert('ab_provider_services', ['provider_id' => $id, 'service_id' => $s['id']]);
            }
            ab_flash('success', 'Prestataire créé.');
        }
        ab_redirect(ab_url('admin/index.php?page=providers'));
    }

    if ($postAction === 'delete' && isset($_POST['id'])) {
        if ((int)$_POST['id'] !== Auth::userId()) {
            $db->delete('ab_users', 'id = ?', [(int)$_POST['id']]);
            ab_flash('success', 'Prestataire supprimé.');
        }
        ab_redirect(ab_url('admin/index.php?page=providers'));
    }
}

$action = $_GET['action'] ?? 'list';

if ($action === 'edit' || $action === 'create'):
    $provider = null;
    if ($action === 'edit' && isset($_GET['id'])) {
        $provider = $db->fetchOne("SELECT * FROM ab_users WHERE id = ?", [(int)$_GET['id']]);
    }
    $services = $db->fetchAll("SELECT * FROM ab_services WHERE is_active = 1 ORDER BY name");
    $assignedServices = $provider ? array_column($db->fetchAll("SELECT service_id FROM ab_provider_services WHERE provider_id = ?", [$provider['id']]), 'service_id') : [];
?>
<div class="card">
    <div class="card-header bg-white"><h5 class="mb-0"><?= $provider ? 'Modifier' : 'Nouveau' ?> prestataire</h5></div>
    <div class="card-body">
        <form method="POST">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="save">
            <?php if ($provider): ?><input type="hidden" name="id" value="<?= $provider['id'] ?>"><?php endif; ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Prénom *</label>
                    <input type="text" name="first_name" class="form-control" required value="<?= ab_escape($provider['first_name'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nom *</label>
                    <input type="text" name="last_name" class="form-control" required value="<?= ab_escape($provider['last_name'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-control" required value="<?= ab_escape($provider['email'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Téléphone</label>
                    <input type="tel" name="phone" class="form-control" value="<?= ab_escape($provider['phone'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mot de passe <?= $provider ? '(laisser vide pour ne pas changer)' : '*' ?></label>
                    <input type="password" name="password" class="form-control" <?= $provider ? '' : 'required' ?> minlength="6">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Rôle</label>
                    <select name="role" class="form-select">
                        <option value="provider" <?= ($provider['role'] ?? '') === 'provider' ? 'selected' : '' ?>>Prestataire</option>
                        <option value="admin" <?= ($provider['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrateur</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Message pour les clients</label>
                    <textarea name="welcome_message" class="form-control" rows="2" placeholder="Message affiché aux clients qui choisissent ce prestataire"><?= ab_escape($provider['welcome_message'] ?? '') ?></textarea>
                    <small class="text-muted">Ce message sera visible par les clients lors de la sélection du prestataire.</small>
                </div>
                <div class="col-12">
                    <label class="form-label">Notes internes</label>
                    <textarea name="notes" class="form-control" rows="2"><?= ab_escape($provider['notes'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <div class="form-check form-switch mb-2">
                        <input type="checkbox" name="is_active" class="form-check-input" <?= ($provider['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label">Actif</label>
                    </div>
                    <div class="form-check form-switch">
                        <input type="checkbox" name="is_visible_booking" class="form-check-input" <?= ($provider['is_visible_booking'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label">Visible pour les clients (réservation en ligne)</label>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check"></i> Enregistrer</button>
                    <a href="<?= ab_url('admin/index.php?page=providers') ?>" class="btn btn-outline-secondary">Annuler</a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php else: ?>
<?php $providers = $db->fetchAll("SELECT * FROM ab_users ORDER BY role DESC, first_name"); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-person-badge"></i> Prestataires</h4>
    <a href="<?= ab_url('admin/index.php?page=providers&action=create') ?>" class="btn btn-primary"><i class="bi bi-plus"></i> Nouveau</a>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Nom</th><th>Email</th><th>Téléphone</th><th>Rôle</th><th>Actif</th><th>Visible clients</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($providers as $p): ?>
            <tr>
                <td><strong><?= ab_escape($p['first_name'] . ' ' . $p['last_name']) ?></strong></td>
                <td><?= ab_escape($p['email']) ?></td>
                <td><?= ab_escape($p['phone']) ?></td>
                <td><span class="badge <?= $p['role'] === 'admin' ? 'bg-danger' : 'bg-info' ?>"><?= $p['role'] ?></span></td>
                <td><?= $p['is_active'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-danger"></i>' ?></td>
                <td><?= ($p['is_visible_booking'] ?? 1) ? '<i class="bi bi-eye-fill text-success"></i>' : '<i class="bi bi-eye-slash text-muted"></i>' ?></td>
                <td>
                    <a href="<?= ab_url('admin/index.php?page=providers&action=edit&id=' . $p['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                    <?php if ($p['id'] != Auth::userId()): ?>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer ?')">
                        <?= Auth::csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
