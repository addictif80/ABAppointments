<?php
$pageTitle = 'Detail Abonnement';
$db = Database::getInstance();
$subId = (int)($_GET['id'] ?? 0);

$sub = $db->fetchOne(
    "SELECT s.*, p.name as product_name, p.type, u.first_name, u.last_name, u.email
     FROM wp_subscriptions s JOIN wp_products p ON s.product_id = p.id JOIN wp_users u ON s.user_id = u.id WHERE s.id = ?",
    [$subId]
);
if (!$sub) { wp_flash('error', 'Abonnement introuvable.'); wp_redirect(wp_url('admin/?page=subscriptions')); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update') {
        $db->update('wp_subscriptions', [
            'price' => (float)($_POST['price'] ?? $sub['price']),
            'billing_cycle' => $_POST['billing_cycle'] ?? $sub['billing_cycle'],
            'next_due_date' => $_POST['next_due_date'] ?? $sub['next_due_date'],
            'auto_renew' => isset($_POST['auto_renew']) ? 1 : 0,
            'notes' => trim($_POST['notes'] ?? '')
        ], 'id = ?', [$subId]);
        wp_flash('success', 'Abonnement mis a jour.');
    } elseif ($action === 'suspend') {
        ServiceManager::suspendService($subId, $_POST['reason'] ?? 'Admin');
        wp_flash('success', 'Service suspendu.');
    } elseif ($action === 'unsuspend') {
        ServiceManager::unsuspendService($subId);
        $db->update('wp_subscriptions', ['status' => 'active', 'suspended_at' => null, 'suspension_reason' => null], 'id = ?', [$subId]);
        wp_flash('success', 'Service reactive.');
    } elseif ($action === 'terminate') {
        ServiceManager::terminateService($subId);
        wp_flash('success', 'Service supprime.');
    } elseif ($action === 'generate_invoice') {
        $invId = InvoiceManager::generateRenewalInvoice($subId);
        wp_flash('success', "Facture generee (#$invId).");
    }
    wp_redirect(wp_url("admin/?page=subscription-detail&id=$subId"));
}

// Get service details
$serviceDetail = null;
switch ($sub['type']) {
    case 'vps': $serviceDetail = $db->fetchOne("SELECT v.*, o.name as os_name FROM wp_services_vps v LEFT JOIN wp_os_templates o ON v.os_template_id = o.id WHERE v.subscription_id = ?", [$subId]); break;
    case 'hosting': $serviceDetail = $db->fetchOne("SELECT * FROM wp_services_hosting WHERE subscription_id = ?", [$subId]); break;
    case 'navidrome': $serviceDetail = $db->fetchOne("SELECT * FROM wp_services_navidrome WHERE subscription_id = ?", [$subId]); break;
}

$invoices = $db->fetchAll("SELECT * FROM wp_invoices WHERE subscription_id = ? ORDER BY created_at DESC", [$subId]);
$statusColors = ['active' => 'success', 'pending' => 'warning', 'suspended' => 'danger', 'cancelled' => 'dark'];
$pageTitle = $sub['product_name'] . ' - ' . $sub['first_name'] . ' ' . $sub['last_name'];
?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between">
                <h6 class="mb-0"><?= wp_escape($sub['product_name']) ?></h6>
                <span class="badge bg-<?= $statusColors[$sub['status']] ?? 'secondary' ?> fs-6"><?= ucfirst($sub['status']) ?></span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= wp_csrf_field() ?><input type="hidden" name="action" value="update">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Prix</label><input type="number" name="price" class="form-control" step="0.01" value="<?= $sub['price'] ?>"></div>
                        <div class="col-md-4"><label class="form-label">Cycle</label><select name="billing_cycle" class="form-select"><option value="monthly" <?= $sub['billing_cycle'] === 'monthly' ? 'selected' : '' ?>>Mensuel</option><option value="yearly" <?= $sub['billing_cycle'] === 'yearly' ? 'selected' : '' ?>>Annuel</option></select></div>
                        <div class="col-md-4"><label class="form-label">Prochaine echeance</label><input type="date" name="next_due_date" class="form-control" value="<?= $sub['next_due_date'] ?>"></div>
                        <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"><?= wp_escape($sub['notes']) ?></textarea></div>
                        <div class="col-12"><div class="form-check"><input type="checkbox" name="auto_renew" class="form-check-input" <?= $sub['auto_renew'] ? 'checked' : '' ?> id="autoRenew"><label for="autoRenew" class="form-check-label">Renouvellement auto</label></div></div>
                    </div>
                    <div class="mt-3"><button type="submit" class="btn btn-primary">Enregistrer</button></div>
                </form>
            </div>
        </div>

        <?php if ($serviceDetail): ?>
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0">Details du service (<?= strtoupper($sub['type']) ?>)</h6></div>
            <div class="card-body">
                <?php if ($sub['type'] === 'vps'): ?>
                    <div class="row g-2">
                        <div class="col-md-3"><strong>Hostname:</strong> <?= wp_escape($serviceDetail['hostname']) ?></div>
                        <div class="col-md-3"><strong>VMID:</strong> <?= $serviceDetail['proxmox_vmid'] ?? 'N/A' ?></div>
                        <div class="col-md-3"><strong>IP:</strong> <?= wp_escape($serviceDetail['ip_address'] ?: 'N/A') ?></div>
                        <div class="col-md-3"><strong>OS:</strong> <?= wp_escape($serviceDetail['os_name'] ?? 'N/A') ?></div>
                        <div class="col-md-3"><strong>CPU:</strong> <?= $serviceDetail['cores'] ?> cores</div>
                        <div class="col-md-3"><strong>RAM:</strong> <?= $serviceDetail['ram_mb'] ?> MB</div>
                        <div class="col-md-3"><strong>Disque:</strong> <?= $serviceDetail['disk_gb'] ?> GB</div>
                        <div class="col-md-3"><strong>Statut:</strong> <?= $serviceDetail['status'] ?></div>
                    </div>
                <?php elseif ($sub['type'] === 'hosting'): ?>
                    <div class="row g-2">
                        <div class="col-md-4"><strong>Domaine:</strong> <?= wp_escape($serviceDetail['domain']) ?></div>
                        <div class="col-md-4"><strong>User CP:</strong> <?= wp_escape($serviceDetail['cyberpanel_username']) ?></div>
                        <div class="col-md-4"><strong>Statut:</strong> <?= $serviceDetail['status'] ?></div>
                    </div>
                <?php elseif ($sub['type'] === 'navidrome'): ?>
                    <div class="row g-2">
                        <div class="col-md-4"><strong>Username:</strong> <?= wp_escape($serviceDetail['navidrome_username']) ?></div>
                        <div class="col-md-4"><strong>Stockage:</strong> <?= $serviceDetail['storage_mb'] ?> MB</div>
                        <div class="col-md-4"><strong>Statut:</strong> <?= $serviceDetail['status'] ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Invoices -->
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <h6 class="mb-0">Factures</h6>
                <form method="POST"><?= wp_csrf_field() ?><button name="action" value="generate_invoice" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus me-1"></i>Generer facture</button></form>
            </div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead><tr><th>N</th><th>Montant</th><th>Statut</th><th>Echeance</th></tr></thead>
                <tbody>
                <?php foreach ($invoices as $inv): ?>
                <tr><td><a href="<?= wp_url("admin/?page=invoice-detail&id={$inv['id']}") ?>"><?= wp_escape($inv['invoice_number']) ?></a></td><td><?= wp_format_price($inv['total']) ?></td><td><span class="badge bg-<?= $inv['status'] === 'paid' ? 'success' : ($inv['status'] === 'overdue' ? 'danger' : 'warning') ?>"><?= $inv['status'] ?></span></td><td><?= wp_format_date($inv['due_date']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0">Client</h6></div>
            <div class="card-body">
                <a href="<?= wp_url("admin/?page=client-detail&id={$sub['user_id']}") ?>" class="fw-bold"><?= wp_escape($sub['first_name'] . ' ' . $sub['last_name']) ?></a>
                <div class="text-muted small"><?= wp_escape($sub['email']) ?></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h6 class="mb-0">Actions</h6></div>
            <div class="card-body d-grid gap-2">
                <?php if ($sub['status'] === 'active'): ?>
                    <form method="POST"><?= wp_csrf_field() ?><input type="text" name="reason" class="form-control mb-2" placeholder="Raison (optionnel)"><button name="action" value="suspend" class="btn btn-warning w-100"><i class="bi bi-pause me-1"></i>Suspendre</button></form>
                <?php elseif ($sub['status'] === 'suspended'): ?>
                    <form method="POST"><?= wp_csrf_field() ?><button name="action" value="unsuspend" class="btn btn-success w-100"><i class="bi bi-play me-1"></i>Reactiver</button></form>
                <?php endif; ?>
                <?php if ($sub['status'] !== 'cancelled'): ?>
                    <form method="POST" onsubmit="return confirm('ATTENTION: Cette action est irreversible. Le service sera supprime.')"><?= wp_csrf_field() ?><button name="action" value="terminate" class="btn btn-danger w-100"><i class="bi bi-trash me-1"></i>Supprimer le service</button></form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
