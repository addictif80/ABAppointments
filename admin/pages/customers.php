<?php
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    if ($postAction === 'save') {
        $dobRaw = trim($_POST['date_of_birth'] ?? '');
        $dobDb = null;
        if ($dobRaw !== '') {
            $dobDb = ab_parse_dob($dobRaw);
            if ($dobDb === null) {
                ab_flash('error', 'Date de naissance invalide. Utilisez le format JJ.MM.AAAA.');
                $redirectId = !empty($_POST['id']) ? '&action=edit&id=' . (int)$_POST['id'] : '&action=create';
                ab_redirect(ab_url('admin/index.php?page=customers' . $redirectId));
            }
        }
        $data = [
            'first_name' => trim($_POST['first_name']),
            'last_name' => trim($_POST['last_name']),
            'email' => trim($_POST['email']),
            'phone' => trim($_POST['phone'] ?? ''),
            'date_of_birth' => $dobDb,
            'address' => trim($_POST['address'] ?? ''),
            'city' => trim($_POST['city'] ?? ''),
            'zip_code' => trim($_POST['zip_code'] ?? ''),
            'notes' => trim($_POST['notes'] ?? ''),
        ];
        if (!empty($_POST['id'])) {
            $db->update('ab_customers', $data, 'id = ?', [(int)$_POST['id']]);
            ab_flash('success', 'Client mis à jour.');
        } else {
            $db->insert('ab_customers', $data);
            ab_flash('success', 'Client créé.');
        }
        ab_redirect(ab_url('admin/index.php?page=customers'));
    }
    if ($postAction === 'delete' && isset($_POST['id'])) {
        $db->delete('ab_customers', 'id = ?', [(int)$_POST['id']]);
        ab_flash('success', 'Client supprimé.');
        ab_redirect(ab_url('admin/index.php?page=customers'));
    }
    if ($postAction === 'send_dob_reminders') {
        Auth::requireAdmin();
        $missing = $db->fetchAll(
            "SELECT id, first_name, last_name, email FROM ab_customers WHERE date_of_birth IS NULL"
        );
        if (empty($missing)) {
            ab_flash('success', 'Tous les clients ont déjà renseigné leur date de naissance.');
            ab_redirect(ab_url('admin/index.php?page=customers'));
        }
        $mailer = new Mailer();
        $businessName = ab_setting('business_name', 'ABAppointments');
        $sent = 0; $failed = 0;
        foreach ($missing as $c) {
            $token   = bin2hex(random_bytes(24));
            $expires = date('Y-m-d H:i:s', strtotime('+7 days'));
            $db->update('ab_customers',
                ['dob_token' => $token, 'dob_token_expires' => $expires],
                'id = ?', [$c['id']]
            );
            $link = ab_url('public/set-dob.php?token=' . $token);
            $html = '<p>Bonjour <strong>' . htmlspecialchars($c['first_name']) . '</strong>,</p>'
                  . '<p>Pour pouvoir continuer à prendre rendez-vous en ligne chez <strong>' . htmlspecialchars($businessName) . '</strong>, '
                  . 'nous vous invitons à compléter votre profil en renseignant votre date de naissance.</p>'
                  . '<p style="text-align:center;margin:30px 0;">'
                  . '<a href="' . $link . '" style="background:#e91e63;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block;">'
                  . 'Renseigner ma date de naissance</a></p>'
                  . '<p style="color:#999;font-size:0.85em;">Ce lien est valable 7 jours. Si vous n\'êtes pas à l\'origine de cette demande, ignorez simplement ce message.</p>';
            if ($mailer->send($c['email'], 'Complétez votre profil – ' . $businessName, $html, $c['first_name'] . ' ' . $c['last_name'])) {
                $sent++;
            } else {
                $failed++;
            }
        }
        $msg = $sent . ' email' . ($sent > 1 ? 's' : '') . ' envoyé' . ($sent > 1 ? 's' : '');
        if ($failed) $msg .= ', ' . $failed . ' échec' . ($failed > 1 ? 's' : '');
        ab_flash($failed === 0 ? 'success' : 'warning', $msg);
        ab_redirect(ab_url('admin/index.php?page=customers'));
    }
}

$action = $_GET['action'] ?? 'list';

if ($action === 'edit' || $action === 'create'):
    $customer = null;
    if ($action === 'edit' && isset($_GET['id'])) {
        $customer = $db->fetchOne("SELECT * FROM ab_customers WHERE id = ?", [(int)$_GET['id']]);
    }
?>
<div class="card">
    <div class="card-header bg-white"><h5 class="mb-0"><?= $customer ? 'Modifier' : 'Nouveau' ?> client</h5></div>
    <div class="card-body">
        <form method="POST">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="save">
            <?php if ($customer): ?><input type="hidden" name="id" value="<?= $customer['id'] ?>"><?php endif; ?>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Prénom *</label><input type="text" name="first_name" class="form-control" required value="<?= ab_escape($customer['first_name'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label">Nom *</label><input type="text" name="last_name" class="form-control" required value="<?= ab_escape($customer['last_name'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required value="<?= ab_escape($customer['email'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label">Téléphone</label><input type="tel" name="phone" class="form-control" value="<?= ab_escape($customer['phone'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label">Date de naissance</label><input type="text" name="date_of_birth" class="form-control" placeholder="JJ.MM.AAAA" maxlength="10" value="<?= ab_escape(ab_format_dob($customer['date_of_birth'] ?? null)) ?>"></div>
                <div class="col-12"><label class="form-label">Adresse</label><input type="text" name="address" class="form-control" value="<?= ab_escape($customer['address'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label">Ville</label><input type="text" name="city" class="form-control" value="<?= ab_escape($customer['city'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label">Code postal</label><input type="text" name="zip_code" class="form-control" value="<?= ab_escape($customer['zip_code'] ?? '') ?>"></div>
                <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"><?= ab_escape($customer['notes'] ?? '') ?></textarea></div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check"></i> Enregistrer</button>
                    <a href="<?= ab_url('admin/index.php?page=customers') ?>" class="btn btn-outline-secondary">Annuler</a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php elseif ($action === 'view' && isset($_GET['id'])):
    $customer = $db->fetchOne("SELECT * FROM ab_customers WHERE id = ?", [(int)$_GET['id']]);
    if (!$customer) { ab_flash('error', 'Client non trouvé.'); ab_redirect(ab_url('admin/index.php?page=customers')); }
    $appointments = $db->fetchAll(
        "SELECT a.*, s.name as sn, s.color as sc FROM ab_appointments a JOIN ab_services s ON a.service_id = s.id WHERE a.customer_id = ? ORDER BY a.start_datetime DESC",
        [$customer['id']]
    );
?>
<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-white"><h6 class="mb-0"><?= ab_escape($customer['first_name'] . ' ' . $customer['last_name']) ?></h6></div>
            <div class="card-body">
                <p><i class="bi bi-envelope"></i> <?= ab_escape($customer['email']) ?></p>
                <p><i class="bi bi-phone"></i> <?= ab_escape($customer['phone'] ?: '-') ?></p>
                <p><i class="bi bi-geo-alt"></i> <?= ab_escape(($customer['address'] ?: '') . ' ' . ($customer['zip_code'] ?: '') . ' ' . ($customer['city'] ?: '')) ?></p>
                <?php if ($customer['notes']): ?><p class="text-muted"><?= nl2br(ab_escape($customer['notes'])) ?></p><?php endif; ?>
                <p class="text-muted small">Client depuis le <?= ab_format_date($customer['created_at']) ?></p>
                <?php if (!empty($customer['date_of_birth'])): ?>
                <p><i class="bi bi-cake"></i> <?= ab_escape(ab_format_dob($customer['date_of_birth'])) ?></p>
                <?php endif; ?>
                <a href="<?= ab_url('admin/index.php?page=customers&action=edit&id=' . $customer['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Modifier</a>
                <a href="<?= ab_url('public/account.php?impersonate=' . $customer['id']) ?>" target="_blank" class="btn btn-sm btn-outline-info"><i class="bi bi-eye"></i> Voir compte client</a>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-white"><h6 class="mb-0">Historique (<?= count($appointments) ?>)</h6></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Date</th><th>Heure</th><th>Prestation</th><th>Statut</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($appointments as $a): ?>
                    <tr>
                        <td><?= ab_format_date($a['start_datetime']) ?></td>
                        <td><?= ab_format_time($a['start_datetime']) ?></td>
                        <td><span class="badge" style="background:<?= ab_escape($a['sc']) ?>"><?= ab_escape($a['sn']) ?></span></td>
                        <td><span class="badge badge-<?= $a['status'] ?>"><?= $a['status'] ?></span></td>
                        <td><a href="<?= ab_url('admin/index.php?page=appointments&action=view&id=' . $a['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<?php
$search = trim($_GET['q'] ?? '');
$sql = "SELECT c.*, COUNT(a.id) as appt_count, MAX(a.start_datetime) as last_appt FROM ab_customers c LEFT JOIN ab_appointments a ON c.id = a.customer_id";
$params = [];
if ($search) {
    $sql .= " WHERE c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%", "%$search%"];
}
$sql .= " GROUP BY c.id ORDER BY c.created_at DESC";
$customers = $db->fetchAll($sql, $params);
?>
<?php $missingDobCount = $db->count('ab_customers', 'date_of_birth IS NULL'); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-people"></i> Clients (<?= count($customers) ?>)</h4>
    <div class="d-flex gap-2">
        <?php if ($missingDobCount > 0): ?>
        <form method="POST" onsubmit="return confirm('Envoyer un email de rappel aux <?= $missingDobCount ?> client(s) sans date de naissance ?')">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="send_dob_reminders">
            <button type="submit" class="btn btn-warning">
                <i class="bi bi-envelope-exclamation"></i> Rappel DDN
                <span class="badge bg-dark ms-1"><?= $missingDobCount ?></span>
            </button>
        </form>
        <?php endif; ?>
        <a href="<?= ab_url('admin/index.php?page=customers&action=create') ?>" class="btn btn-primary"><i class="bi bi-plus"></i> Nouveau</a>
    </div>
</div>
<div class="card mb-3">
    <div class="card-body py-2">
        <form class="row g-2 align-items-center" method="GET">
            <input type="hidden" name="page" value="customers">
            <div class="col-auto flex-grow-1">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Rechercher un client..." value="<?= ab_escape($search) ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
            </div>
        </form>
    </div>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Nom</th><th>Email</th><th>Téléphone</th><th>RDV</th><th>Dernier RDV</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($customers as $c): ?>
            <tr>
                <td><a href="<?= ab_url('admin/index.php?page=customers&action=view&id=' . $c['id']) ?>"><strong><?= ab_escape($c['first_name'] . ' ' . $c['last_name']) ?></strong></a></td>
                <td><?= ab_escape($c['email']) ?></td>
                <td><?= ab_escape($c['phone'] ?: '-') ?></td>
                <td><span class="badge bg-secondary"><?= $c['appt_count'] ?></span></td>
                <td><?= $c['last_appt'] ? ab_format_date($c['last_appt']) : '-' ?></td>
                <td>
                    <a href="<?= ab_url('admin/index.php?page=customers&action=edit&id=' . $c['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                    <a href="<?= ab_url('public/account.php?impersonate=' . $c['id']) ?>" target="_blank" class="btn btn-sm btn-outline-info" title="Voir compte client"><i class="bi bi-eye"></i></a>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce client et tous ses rendez-vous ?')">
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
<?php endif; ?>
