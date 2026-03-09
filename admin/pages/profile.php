<?php
$db = Database::getInstance();
$user = $db->fetchOne("SELECT * FROM ab_users WHERE id = ?", [Auth::userId()]);

$caldav = new CalDAV();
try {
    $caldavConfig = $caldav->getConfig(Auth::userId());
} catch (Exception $e) {
    $caldavConfig = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['form_action'] ?? 'profile';

    if ($postAction === 'caldav') {
        try {
            $caldav->saveConfig(Auth::userId(), [
                'caldav_url' => $_POST['caldav_url'] ?? '',
                'caldav_username' => $_POST['caldav_username'] ?? '',
                'caldav_password' => $_POST['caldav_password'] ?? '',
                'caldav_enabled' => isset($_POST['caldav_enabled']),
            ]);
            ab_flash('success', 'Configuration CalDAV enregistrée.');
        } catch (Exception $e) {
            ab_flash('error', 'Erreur CalDAV : la table de synchronisation n\'existe pas. Veuillez exécuter la migration.');
        }
        ab_redirect(ab_url('admin/index.php?page=profile'));
    }

    if ($postAction === 'caldav_test') {
        try {
            $result = $caldav->testConnection(Auth::userId());
            if ($result['success']) {
                ab_flash('success', 'Connexion CalDAV réussie !');
            } else {
                ab_flash('error', 'Échec CalDAV : ' . $result['error']);
            }
        } catch (Exception $e) {
            ab_flash('error', 'Erreur CalDAV : la table de synchronisation n\'existe pas. Veuillez exécuter la migration.');
        }
        ab_redirect(ab_url('admin/index.php?page=profile'));
    }

    $data = [
        'first_name' => trim($_POST['first_name']),
        'last_name' => trim($_POST['last_name']),
        'email' => trim($_POST['email']),
        'phone' => trim($_POST['phone'] ?? ''),
        'welcome_message' => trim($_POST['welcome_message'] ?? ''),
    ];

    if (!empty($_POST['new_password'])) {
        if (!password_verify($_POST['current_password'], $user['password'])) {
            ab_flash('error', 'Mot de passe actuel incorrect.');
            ab_redirect(ab_url('admin/index.php?page=profile'));
        }
        $data['password'] = Auth::hashPassword($_POST['new_password']);
    }

    $db->update('ab_users', $data, 'id = ?', [Auth::userId()]);
    $_SESSION['user_name'] = $data['first_name'] . ' ' . $data['last_name'];
    ab_flash('success', 'Profil mis à jour.');
    ab_redirect(ab_url('admin/index.php?page=profile'));
}
?>

<h4 class="mb-3"><i class="bi bi-person"></i> Mon profil</h4>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <?= Auth::csrfField() ?>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Prénom</label><input type="text" name="first_name" class="form-control" required value="<?= ab_escape($user['first_name']) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Nom</label><input type="text" name="last_name" class="form-control" required value="<?= ab_escape($user['last_name']) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required value="<?= ab_escape($user['email']) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Téléphone</label><input type="tel" name="phone" class="form-control" value="<?= ab_escape($user['phone'] ?? '') ?>"></div>
                        <div class="col-12">
                            <label class="form-label">Message pour les clients</label>
                            <textarea name="welcome_message" class="form-control" rows="2" placeholder="Message affiché aux clients qui vous choisissent"><?= ab_escape($user['welcome_message'] ?? '') ?></textarea>
                            <small class="text-muted">Ce message sera visible lors de la réservation en ligne.</small>
                        </div>
                        <div class="col-12"><hr><h6>Changer le mot de passe</h6></div>
                        <div class="col-md-6"><label class="form-label">Mot de passe actuel</label><input type="password" name="current_password" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Nouveau mot de passe</label><input type="password" name="new_password" class="form-control" minlength="6"></div>
                        <div class="col-12"><button type="submit" class="btn btn-primary"><i class="bi bi-check"></i> Enregistrer</button></div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8 mt-4">
        <div class="card">
            <div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-calendar2-event"></i> Synchronisation CalDAV</h5></div>
            <div class="card-body">
                <p class="text-muted small mb-3">Synchronisez vos rendez-vous avec un calendrier CalDAV (Nextcloud, Radicale, iCloud, Synology, etc.)</p>
                <form method="POST">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="form_action" value="caldav">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">URL du calendrier CalDAV</label>
                            <input type="url" name="caldav_url" class="form-control" placeholder="https://cloud.example.com/remote.php/dav/calendars/user/calendar/" value="<?= ab_escape($caldavConfig['caldav_url'] ?? '') ?>">
                            <small class="text-muted">URL complète vers le calendrier (pas le serveur)</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Identifiant</label>
                            <input type="text" name="caldav_username" class="form-control" value="<?= ab_escape($caldavConfig['caldav_username'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mot de passe</label>
                            <input type="password" name="caldav_password" class="form-control" placeholder="<?= !empty($caldavConfig['caldav_password']) ? '••••••••' : '' ?>">
                            <?php if (!empty($caldavConfig['caldav_password'])): ?>
                            <small class="text-muted">Laisser vide pour ne pas modifier</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input type="checkbox" name="caldav_enabled" class="form-check-input" <?= ($caldavConfig['sync_enabled'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label">Activer la synchronisation</label>
                            </div>
                        </div>
                        <?php if (!empty($caldavConfig['last_sync'])): ?>
                        <div class="col-12">
                            <small class="text-success"><i class="bi bi-check-circle"></i> Dernière synchro : <?= ab_format_date($caldavConfig['last_sync']) ?> à <?= ab_format_time($caldavConfig['last_sync']) ?></small>
                        </div>
                        <?php endif; ?>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check"></i> Enregistrer</button>
                        </div>
                    </div>
                </form>
                <?php if (!empty($caldavConfig['caldav_url'])): ?>
                <hr>
                <form method="POST" class="d-inline">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="form_action" value="caldav_test">
                    <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-lightning"></i> Tester la connexion</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
