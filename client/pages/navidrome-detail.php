<?php
$pageTitle = 'Detail Navidrome';
$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$subId = (int)($_GET['id'] ?? 0);

$sub = $db->fetchOne(
    "SELECT s.*, p.name as product_name FROM wp_subscriptions s JOIN wp_products p ON s.product_id = p.id WHERE s.id = ? AND s.user_id = ? AND p.type = 'navidrome'",
    [$subId, $userId]
);
if (!$sub) { wp_flash('error', 'Service introuvable.'); wp_redirect(wp_url('client/?page=subscriptions')); }

$nd = $db->fetchOne("SELECT * FROM wp_services_navidrome WHERE subscription_id = ?", [$subId]);

// Auto-provision if subscription is active but service doesn't exist
if (!$nd && $sub['status'] === 'active') {
    try {
        ServiceManager::provisionService($subId);
        $nd = $db->fetchOne("SELECT * FROM wp_services_navidrome WHERE subscription_id = ?", [$subId]);
    } catch (Exception $e) {
        error_log("Navidrome auto-provision failed for sub $subId: " . $e->getMessage());
    }
}

if (!$nd) { wp_flash('error', 'Service Navidrome non provisionne. Veuillez contacter le support.'); wp_redirect(wp_url('client/?page=subscriptions')); }

// If service is in error state, show retry option
if ($nd['status'] === 'error') {
    if (isset($_GET['retry_provision'])) {
        try {
            $db->delete('wp_services_navidrome', 'id = ?', [$nd['id']]);
            ServiceManager::provisionService($subId);
            $nd = $db->fetchOne("SELECT * FROM wp_services_navidrome WHERE subscription_id = ?", [$subId]);
            if ($nd && $nd['status'] === 'active') {
                wp_flash('success', 'Service Navidrome provisionne avec succes !');
            }
        } catch (Exception $e) {
            wp_flash('error', 'Echec du provisioning : ' . $e->getMessage());
            $nd = $db->fetchOne("SELECT * FROM wp_services_navidrome WHERE subscription_id = ?", [$subId]);
        }
    }
}

$password = ServiceManager::decryptPassword($nd['navidrome_password']);
$navidromeUrl = wp_setting('navidrome_url', '#');
$storagePercent = $nd['storage_mb'] > 0 ? min(100, round($nd['storage_used_mb'] / $nd['storage_mb'] * 100)) : 0;
$pageTitle = 'Navidrome - ' . $nd['navidrome_username'];
$statusColors = ['active' => 'success', 'creating' => 'info', 'suspended' => 'warning', 'error' => 'danger'];
?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-music-note-beamed me-2"></i> <?= wp_escape($sub['product_name']) ?></h6>
                <span class="badge bg-<?= $statusColors[$nd['status']] ?? 'secondary' ?>"><?= ucfirst($nd['status']) ?></span>
            </div>
            <div class="card-body">
                <!-- Spotify-like UI -->
                <div class="text-center py-4">
                    <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3" style="width:80px;height:80px">
                        <i class="bi bi-music-note-beamed fs-1 text-primary"></i>
                    </div>
                    <h4><?= wp_escape($nd['navidrome_username']) ?></h4>
                    <p class="text-muted"><?= wp_escape($sub['product_name']) ?></p>

                    <?php if ($nd['status'] === 'error'): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i> Le provisioning du service a echoue.
                        <a href="<?= wp_url("client/?page=navidrome-detail&id={$sub['id']}&retry_provision=1") ?>" class="btn btn-warning btn-sm ms-2">
                            <i class="bi bi-arrow-clockwise me-1"></i> Reessayer
                        </a>
                    </div>
                    <?php elseif ($nd['status'] === 'active'): ?>
                    <a href="<?= wp_escape($navidromeUrl) ?>" target="_blank" class="btn btn-primary btn-lg">
                        <i class="bi bi-play-circle me-2"></i> Ouvrir Navidrome
                    </a>
                    <?php endif; ?>
                </div>

                <div class="row g-3 mt-3">
                    <div class="col-md-6">
                        <label class="small text-muted">Stockage utilise</label>
                        <div class="progress mt-1" style="height: 10px;">
                            <div class="progress-bar" style="width: <?= $storagePercent ?>%; background: linear-gradient(90deg, #1DB954, #1ed760)"></div>
                        </div>
                        <small class="text-muted"><?= round($nd['storage_used_mb']/1024, 1) ?> GB / <?= round($nd['storage_mb']/1024, 1) ?> GB</small>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded text-center">
                            <i class="bi bi-music-note-list fs-4 text-info"></i>
                            <div class="fw-bold"><?= $nd['max_playlists'] ?: 'Illimitees' ?></div>
                            <small class="text-muted">Playlists</small>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <h6>Applications compatibles</h6>
                    <div class="row g-2">
                        <div class="col-auto"><span class="badge bg-dark p-2"><i class="bi bi-globe me-1"></i> Web</span></div>
                        <div class="col-auto"><span class="badge bg-dark p-2"><i class="bi bi-phone me-1"></i> Subsonic</span></div>
                        <div class="col-auto"><span class="badge bg-dark p-2"><i class="bi bi-phone me-1"></i> DSub</span></div>
                        <div class="col-auto"><span class="badge bg-dark p-2"><i class="bi bi-phone me-1"></i> play:Sub</span></div>
                        <div class="col-auto"><span class="badge bg-dark p-2"><i class="bi bi-phone me-1"></i> Symfonium</span></div>
                    </div>

                    <div class="mt-3 p-3 bg-light rounded">
                        <small class="text-muted d-block mb-1">URL du serveur (pour les apps)</small>
                        <div class="input-group">
                            <input type="text" class="form-control" value="<?= wp_escape($navidromeUrl) ?>" readonly>
                            <button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText('<?= wp_escape($navidromeUrl) ?>')"><i class="bi bi-clipboard"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-key me-2"></i> Identifiants</h6></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label text-muted small">Utilisateur</label>
                    <div class="input-group">
                        <input type="text" class="form-control" value="<?= wp_escape($nd['navidrome_username']) ?>" readonly>
                        <button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText('<?= wp_escape($nd['navidrome_username']) ?>')"><i class="bi bi-clipboard"></i></button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted small">Mot de passe</label>
                    <div class="input-group">
                        <input type="password" class="form-control" value="<?= wp_escape($password) ?>" id="ndPass" readonly>
                        <button class="btn btn-outline-secondary" onclick="document.getElementById('ndPass').type = document.getElementById('ndPass').type === 'password' ? 'text' : 'password'"><i class="bi bi-eye"></i></button>
                        <button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText('<?= wp_escape($password) ?>')"><i class="bi bi-clipboard"></i></button>
                    </div>
                </div>
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
