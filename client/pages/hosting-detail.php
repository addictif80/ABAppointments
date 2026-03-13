<?php
$pageTitle = 'Detail Hebergement';
$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$subId = (int)($_GET['id'] ?? 0);

$sub = $db->fetchOne(
    "SELECT s.*, p.name as product_name FROM wp_subscriptions s JOIN wp_products p ON s.product_id = p.id WHERE s.id = ? AND s.user_id = ? AND p.type = 'hosting'",
    [$subId, $userId]
);
if (!$sub) { wp_flash('error', 'Service introuvable.'); wp_redirect(wp_url('client/?page=subscriptions')); }

$hosting = $db->fetchOne("SELECT * FROM wp_services_hosting WHERE subscription_id = ?", [$subId]);
if (!$hosting) { wp_flash('error', 'Hebergement non provisionne.'); wp_redirect(wp_url('client/?page=subscriptions')); }

$password = ServiceManager::decryptPassword($hosting['cyberpanel_password']);
$pageTitle = $hosting['domain'];
$statusColors = ['active' => 'success', 'creating' => 'info', 'suspended' => 'warning', 'error' => 'danger'];
$statusLabels = ['active' => 'Actif', 'creating' => 'Creation...', 'suspended' => 'Suspendu', 'error' => 'Erreur'];

$diskPercent = $hosting['disk_mb'] > 0 ? min(100, round($hosting['disk_used_mb'] / $hosting['disk_mb'] * 100)) : 0;
$bwPercent = $hosting['bandwidth_mb'] > 0 ? min(100, round($hosting['bandwidth_used_mb'] / $hosting['bandwidth_mb'] * 100)) : 0;
?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-globe me-2"></i> <?= wp_escape($hosting['domain']) ?></h6>
                <span class="badge bg-<?= $statusColors[$hosting['status']] ?? 'secondary' ?>"><?= $statusLabels[$hosting['status']] ?? $hosting['status'] ?></span>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="small text-muted">Espace disque</label>
                        <div class="progress mt-1" style="height: 8px;">
                            <div class="progress-bar bg-<?= $diskPercent > 80 ? 'danger' : 'primary' ?>" style="width: <?= $diskPercent ?>%"></div>
                        </div>
                        <small class="text-muted"><?= $hosting['disk_used_mb'] ?> / <?= $hosting['disk_mb'] ?> MB (<?= $diskPercent ?>%)</small>
                    </div>
                    <div class="col-md-6">
                        <label class="small text-muted">Bande passante</label>
                        <div class="progress mt-1" style="height: 8px;">
                            <div class="progress-bar bg-<?= $bwPercent > 80 ? 'danger' : 'success' ?>" style="width: <?= $bwPercent ?>%"></div>
                        </div>
                        <small class="text-muted"><?= $hosting['bandwidth_used_mb'] ?> / <?= $hosting['bandwidth_mb'] ?> MB (<?= $bwPercent ?>%)</small>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded text-center">
                            <i class="bi bi-envelope fs-4 text-success"></i>
                            <div class="fw-bold"><?= $hosting['email_accounts'] ?></div>
                            <small class="text-muted">Comptes email</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded text-center">
                            <i class="bi bi-database fs-4 text-primary"></i>
                            <div class="fw-bold"><?= $hosting['databases'] ?></div>
                            <small class="text-muted">Bases de donnees</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded text-center">
                            <i class="bi bi-shield-check fs-4 <?= $hosting['ssl_active'] ? 'text-success' : 'text-muted' ?>"></i>
                            <div class="fw-bold"><?= $hosting['ssl_active'] ? 'Actif' : 'Inactif' ?></div>
                            <small class="text-muted">Certificat SSL</small>
                        </div>
                    </div>
                </div>

                <?php if ($hosting['nameserver1']): ?>
                <div class="mt-4">
                    <h6>Serveurs DNS</h6>
                    <code><?= wp_escape($hosting['nameserver1']) ?></code><br>
                    <?php if ($hosting['nameserver2']): ?><code><?= wp_escape($hosting['nameserver2']) ?></code><?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="mt-4">
                    <label class="small text-muted">Version PHP</label>
                    <div><span class="badge bg-secondary"><?= wp_escape($hosting['php_version']) ?></span></div>
                </div>
            </div>
        </div>

        <?php if (wp_setting('cyberpanel_url')): ?>
        <div class="card">
            <div class="card-body text-center">
                <p>Gerez votre hebergement directement depuis CyberPanel :</p>
                <a href="<?= wp_escape(wp_setting('cyberpanel_url')) ?>" target="_blank" class="btn btn-primary">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Ouvrir CyberPanel
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-key me-2"></i> Identifiants CyberPanel</h6></div>
            <div class="card-body">
                <?php if (!empty($hosting['cyberpanel_username']) && !empty($password)): ?>
                <div class="mb-3">
                    <label class="form-label text-muted small">Utilisateur</label>
                    <div class="input-group">
                        <input type="text" class="form-control" value="<?= wp_escape($hosting['cyberpanel_username']) ?>" readonly>
                        <button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText('<?= wp_escape($hosting['cyberpanel_username']) ?>')"><i class="bi bi-clipboard"></i></button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted small">Mot de passe</label>
                    <div class="input-group">
                        <input type="password" class="form-control" value="<?= wp_escape($password) ?>" id="cpPass" readonly>
                        <button class="btn btn-outline-secondary" onclick="document.getElementById('cpPass').type = document.getElementById('cpPass').type === 'password' ? 'text' : 'password'"><i class="bi bi-eye"></i></button>
                        <button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText('<?= wp_escape($password) ?>')"><i class="bi bi-clipboard"></i></button>
                    </div>
                </div>
                <?php else: ?>
                <div class="text-center text-muted py-3">
                    <i class="bi bi-exclamation-triangle fs-3 text-warning"></i>
                    <p class="mt-2 mb-0">Identifiants non disponibles.<br><small>Le provisioning du service est en cours ou a echoue. Contactez le support si le probleme persiste.</small></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-info-circle me-2"></i> Abonnement</h6></div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Offre</span><span><?= wp_escape($sub['product_name']) ?></span></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Prix</span><span><?= wp_format_price($sub['price']) ?>/<?= $sub['billing_cycle'] === 'yearly' ? 'an' : 'mois' ?></span></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Echeance</span><span><?= wp_format_date($sub['next_due_date']) ?></span></div>
            </div>
        </div>
    </div>
</div>
