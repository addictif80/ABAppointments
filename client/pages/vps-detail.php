<?php
$pageTitle = 'Detail VPS';
$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$subId = (int)($_GET['id'] ?? 0);

$sub = $db->fetchOne(
    "SELECT s.*, p.name as product_name, p.type FROM wp_subscriptions s JOIN wp_products p ON s.product_id = p.id WHERE s.id = ? AND s.user_id = ? AND p.type = 'vps'",
    [$subId, $userId]
);
if (!$sub) { wp_flash('error', 'Service introuvable.'); wp_redirect(wp_url('client/?page=subscriptions')); }

$vps = $db->fetchOne("SELECT v.*, o.name as os_name, o.icon as os_icon FROM wp_services_vps v LEFT JOIN wp_os_templates o ON v.os_template_id = o.id WHERE v.subscription_id = ?", [$subId]);
if (!$vps) { wp_flash('error', 'VPS non provisionne.'); wp_redirect(wp_url('client/?page=subscriptions')); }

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $vps['proxmox_vmid']) {
    $action = $_POST['action'] ?? '';
    try {
        $proxmox = new ProxmoxAPI();
        switch ($action) {
            case 'start':
                $proxmox->startVM($vps['proxmox_node'], $vps['proxmox_vmid']);
                $db->update('wp_services_vps', ['status' => 'running'], 'id = ?', [$vps['id']]);
                wp_flash('success', 'VPS demarre.');
                wp_log_activity('vps_start', 'vps', $vps['id']);
                break;
            case 'stop':
                $proxmox->shutdownVM($vps['proxmox_node'], $vps['proxmox_vmid']);
                $db->update('wp_services_vps', ['status' => 'stopped'], 'id = ?', [$vps['id']]);
                wp_flash('success', 'VPS arrete.');
                wp_log_activity('vps_stop', 'vps', $vps['id']);
                break;
            case 'reboot':
                $proxmox->rebootVM($vps['proxmox_node'], $vps['proxmox_vmid']);
                wp_flash('success', 'VPS redemarrage en cours.');
                wp_log_activity('vps_reboot', 'vps', $vps['id']);
                break;
            case 'reinstall':
                $osId = (int)($_POST['reinstall_os'] ?? $vps['os_template_id']);
                $os = $db->fetchOne("SELECT * FROM wp_os_templates WHERE id = ?", [$osId]);
                if ($os) {
                    $newPass = wp_generate_password(16);
                    $proxmox->reinstallVM($vps['proxmox_node'], $vps['proxmox_vmid'], $os['proxmox_template'], $newPass);
                    $db->update('wp_services_vps', [
                        'os_template_id' => $osId,
                        'root_password' => base64_encode(openssl_encrypt($newPass, 'AES-256-CBC', SECRET_KEY, 0, substr(md5(SECRET_KEY), 0, 16))),
                        'status' => 'running'
                    ], 'id = ?', [$vps['id']]);
                    wp_flash('success', 'Reinstallation en cours. Nouveau mot de passe genere.');
                    wp_log_activity('vps_reinstall', 'vps', $vps['id'], ['os' => $os['name']]);
                }
                break;
        }
    } catch (Exception $e) {
        wp_flash('error', 'Erreur: ' . $e->getMessage());
    }
    wp_redirect(wp_url("client/?page=vps-detail&id=$subId"));
}

$password = ServiceManager::decryptPassword($vps['root_password']);
$statusColors = ['running' => 'success', 'stopped' => 'danger', 'suspended' => 'warning', 'creating' => 'info', 'error' => 'danger', 'reinstalling' => 'info'];
$statusLabels = ['running' => 'En ligne', 'stopped' => 'Arrete', 'suspended' => 'Suspendu', 'creating' => 'Creation...', 'error' => 'Erreur', 'reinstalling' => 'Reinstallation...'];
$osTemplates = $db->fetchAll("SELECT * FROM wp_os_templates WHERE is_active = 1 ORDER BY sort_order");
$pageTitle = $vps['hostname'];
?>

<div class="row g-4">
    <div class="col-lg-8">
        <!-- Control Panel -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-hdd-rack me-2"></i> <?= wp_escape($vps['hostname']) ?></h6>
                <span class="badge bg-<?= $statusColors[$vps['status']] ?? 'secondary' ?> fs-6">
                    <i class="bi bi-circle-fill me-1 small"></i> <?= $statusLabels[$vps['status']] ?? $vps['status'] ?>
                </span>
            </div>
            <div class="card-body">
                <div class="d-flex gap-2 flex-wrap mb-4">
                    <form method="POST" class="d-inline">
                        <?= wp_csrf_field() ?>
                        <input type="hidden" name="action" value="start">
                        <button type="submit" class="btn btn-success" <?= $vps['status'] === 'running' ? 'disabled' : '' ?>>
                            <i class="bi bi-play-fill"></i> Demarrer
                        </button>
                    </form>
                    <form method="POST" class="d-inline">
                        <?= wp_csrf_field() ?>
                        <input type="hidden" name="action" value="stop">
                        <button type="submit" class="btn btn-danger" <?= $vps['status'] !== 'running' ? 'disabled' : '' ?> onclick="return confirm('Etes-vous sur de vouloir arreter le VPS ?')">
                            <i class="bi bi-stop-fill"></i> Arreter
                        </button>
                    </form>
                    <form method="POST" class="d-inline">
                        <?= wp_csrf_field() ?>
                        <input type="hidden" name="action" value="reboot">
                        <button type="submit" class="btn btn-warning" <?= $vps['status'] !== 'running' ? 'disabled' : '' ?>>
                            <i class="bi bi-arrow-clockwise"></i> Redemarrer
                        </button>
                    </form>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded">
                            <small class="text-muted d-block">Adresse IP</small>
                            <code class="fs-5"><?= wp_escape($vps['ip_address'] ?: 'Non attribuee') ?></code>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded">
                            <small class="text-muted d-block">Systeme</small>
                            <span><?= wp_os_icon($vps['os_icon'] ?? '') ?> <?= wp_escape($vps['os_name'] ?? 'N/A') ?></span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded text-center">
                            <small class="text-muted d-block">CPU</small>
                            <span class="fw-bold"><?= $vps['cores'] ?> vCPU</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded text-center">
                            <small class="text-muted d-block">RAM</small>
                            <span class="fw-bold"><?= $vps['ram_mb'] >= 1024 ? ($vps['ram_mb']/1024) . ' GB' : $vps['ram_mb'] . ' MB' ?></span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded text-center">
                            <small class="text-muted d-block">Disque</small>
                            <span class="fw-bold"><?= $vps['disk_gb'] ?> GB</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded text-center">
                            <small class="text-muted d-block">VMID</small>
                            <span class="fw-bold"><?= $vps['proxmox_vmid'] ?? 'N/A' ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SSH Console -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-terminal me-2"></i> Console SSH</h6>
            </div>
            <div class="card-body">
                <?php if ($vps['status'] === 'running' && $vps['ip_address']): ?>
                    <p class="text-muted">Connexion SSH :</p>
                    <div class="bg-dark text-success p-3 rounded font-monospace">
                        ssh root@<?= wp_escape($vps['ip_address']) ?>
                    </div>
                    <div class="mt-3">
                        <a href="<?= wp_url("api/?action=vnc&vps_id={$vps['id']}") ?>" target="_blank" class="btn btn-dark">
                            <i class="bi bi-terminal me-1"></i> Ouvrir la console web
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-muted text-center py-3">
                        <i class="bi bi-terminal fs-1"></i>
                        <p class="mt-2">La console n'est disponible que lorsque le VPS est en ligne.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Reinstall -->
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-arrow-repeat me-2"></i> Reinstaller</h6></div>
            <div class="card-body">
                <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i> La reinstallation supprimera toutes les donnees du VPS !</div>
                <form method="POST" onsubmit="return confirm('ATTENTION : Toutes les donnees seront perdues. Continuer ?')">
                    <?= wp_csrf_field() ?>
                    <input type="hidden" name="action" value="reinstall">
                    <div class="mb-3">
                        <label class="form-label">Systeme d'exploitation</label>
                        <select name="reinstall_os" class="form-select">
                            <?php foreach ($osTemplates as $os): ?>
                                <option value="<?= $os['id'] ?>" <?= $os['id'] == $vps['os_template_id'] ? 'selected' : '' ?>><?= wp_escape($os['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-arrow-repeat me-1"></i> Reinstaller</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Credentials -->
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-key me-2"></i> Identifiants</h6></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label text-muted small">Utilisateur</label>
                    <div class="input-group">
                        <input type="text" class="form-control" value="root" readonly>
                        <button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText('root')"><i class="bi bi-clipboard"></i></button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted small">Mot de passe</label>
                    <div class="input-group">
                        <input type="password" class="form-control" value="<?= wp_escape($password) ?>" id="rootPass" readonly>
                        <button class="btn btn-outline-secondary" onclick="document.getElementById('rootPass').type = document.getElementById('rootPass').type === 'password' ? 'text' : 'password'"><i class="bi bi-eye"></i></button>
                        <button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText('<?= wp_escape($password) ?>')"><i class="bi bi-clipboard"></i></button>
                    </div>
                </div>
                <?php if ($vps['ip_address']): ?>
                <div>
                    <label class="form-label text-muted small">IP</label>
                    <div class="input-group">
                        <input type="text" class="form-control" value="<?= wp_escape($vps['ip_address']) ?>" readonly>
                        <button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText('<?= wp_escape($vps['ip_address']) ?>')"><i class="bi bi-clipboard"></i></button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Subscription info -->
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-info-circle me-2"></i> Abonnement</h6></div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Offre</span><span><?= wp_escape($sub['product_name']) ?></span></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Prix</span><span><?= wp_format_price($sub['price']) ?>/<?= $sub['billing_cycle'] === 'yearly' ? 'an' : 'mois' ?></span></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Echeance</span><span><?= wp_format_date($sub['next_due_date']) ?></span></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Cree le</span><span><?= wp_format_date($sub['created_at']) ?></span></div>
            </div>
        </div>
    </div>
</div>
