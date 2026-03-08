<?php
Auth::requireAdmin();
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settingsToSave = [
        'business_name', 'business_email', 'business_phone', 'business_address',
        'timezone', 'date_format', 'time_format', 'slot_interval',
        'booking_advance_min', 'booking_advance_max', 'cancellation_limit',
        'require_phone', 'allow_customer_cancel', 'auto_confirm',
        'currency', 'currency_symbol', 'deposit_instructions',
        'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password',
        'smtp_from_email', 'smtp_from_name',
        'google_client_id', 'google_client_secret', 'google_redirect_uri',
        'primary_color', 'secondary_color', 'embed_enabled',
        'booking_announcement', 'admin_announcement',
    ];

    foreach ($settingsToSave as $key) {
        if (isset($_POST[$key])) {
            Settings::set($key, $_POST[$key]);
        }
    }

    // Handle checkboxes
    foreach (['require_phone', 'allow_customer_cancel', 'auto_confirm', 'embed_enabled'] as $key) {
        Settings::set($key, isset($_POST[$key]) ? '1' : '0');
    }

    ab_flash('success', 'Paramètres enregistrés.');
    ab_redirect(ab_url('admin/index.php?page=settings'));
}

Settings::loadAll();
$tab = $_GET['tab'] ?? 'general';
?>

<h4 class="mb-3"><i class="bi bi-gear"></i> Paramètres</h4>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link <?= $tab === 'general' ? 'active' : '' ?>" href="?page=settings&tab=general">Général</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'booking' ? 'active' : '' ?>" href="?page=settings&tab=booking">Réservation</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'deposit' ? 'active' : '' ?>" href="?page=settings&tab=deposit">Acomptes</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'smtp' ? 'active' : '' ?>" href="?page=settings&tab=smtp">Email SMTP</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'google' ? 'active' : '' ?>" href="?page=settings&tab=google">Google Calendar</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'appearance' ? 'active' : '' ?>" href="?page=settings&tab=appearance">Apparence</a></li>
</ul>

<div class="card">
    <div class="card-body">
        <form method="POST">
            <?= Auth::csrfField() ?>

            <?php if ($tab === 'general'): ?>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Nom de l'entreprise</label><input type="text" name="business_name" class="form-control" value="<?= ab_escape(ab_setting('business_name')) ?>"></div>
                <div class="col-md-6"><label class="form-label">Email professionnel</label><input type="email" name="business_email" class="form-control" value="<?= ab_escape(ab_setting('business_email')) ?>"></div>
                <div class="col-md-6"><label class="form-label">Téléphone</label><input type="tel" name="business_phone" class="form-control" value="<?= ab_escape(ab_setting('business_phone')) ?>"></div>
                <div class="col-md-6"><label class="form-label">Fuseau horaire</label>
                    <select name="timezone" class="form-select">
                        <?php foreach (['Europe/Paris', 'Europe/Brussels', 'Europe/Zurich', 'America/Guadeloupe', 'America/Martinique', 'Indian/Reunion', 'Pacific/Tahiti'] as $tz): ?>
                        <option value="<?= $tz ?>" <?= ab_setting('timezone') === $tz ? 'selected' : '' ?>><?= $tz ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12"><label class="form-label">Adresse</label><textarea name="business_address" class="form-control" rows="2"><?= ab_escape(ab_setting('business_address')) ?></textarea></div>
                <div class="col-12"><hr></div>
                <div class="col-12">
                    <label class="form-label"><i class="bi bi-megaphone"></i> Annonce pour les clients (page de réservation)</label>
                    <textarea name="booking_announcement" class="form-control" rows="2" placeholder="Ce message sera affiché en haut de la page de réservation"><?= ab_escape(ab_setting('booking_announcement')) ?></textarea>
                    <small class="text-muted">Laissez vide pour ne rien afficher. Idéal pour informer d'une fermeture, promotion, etc.</small>
                </div>
                <div class="col-12">
                    <label class="form-label"><i class="bi bi-info-circle"></i> Annonce interne (panneau d'administration)</label>
                    <textarea name="admin_announcement" class="form-control" rows="2" placeholder="Ce message sera visible par les prestataires et administrateurs sur le tableau de bord"><?= ab_escape(ab_setting('admin_announcement')) ?></textarea>
                    <small class="text-muted">Visible sur le tableau de bord par tous les prestataires et administrateurs.</small>
                </div>
                <div class="col-md-3"><label class="form-label">Format date</label><input type="text" name="date_format" class="form-control" value="<?= ab_escape(ab_setting('date_format', 'd/m/Y')) ?>"></div>
                <div class="col-md-3"><label class="form-label">Format heure</label><input type="text" name="time_format" class="form-control" value="<?= ab_escape(ab_setting('time_format', 'H:i')) ?>"></div>
                <div class="col-md-3"><label class="form-label">Devise</label><input type="text" name="currency" class="form-control" value="<?= ab_escape(ab_setting('currency', 'EUR')) ?>"></div>
                <div class="col-md-3"><label class="form-label">Symbole</label><input type="text" name="currency_symbol" class="form-control" value="<?= ab_escape(ab_setting('currency_symbol', '€')) ?>"></div>
            </div>

            <?php elseif ($tab === 'booking'): ?>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Intervalle créneaux (minutes)</label><input type="number" name="slot_interval" class="form-control" value="<?= ab_escape(ab_setting('slot_interval', '15')) ?>" min="5" step="5"></div>
                <div class="col-md-6"><label class="form-label">Délai min. avant RDV (minutes)</label><input type="number" name="booking_advance_min" class="form-control" value="<?= ab_escape(ab_setting('booking_advance_min', '60')) ?>"><small class="text-muted">Ex: 60 = 1h minimum avant le RDV</small></div>
                <div class="col-md-6"><label class="form-label">Délai max. réservation (minutes)</label><input type="number" name="booking_advance_max" class="form-control" value="<?= ab_escape(ab_setting('booking_advance_max', '43200')) ?>"><small class="text-muted">Ex: 43200 = 30 jours</small></div>
                <div class="col-md-6"><label class="form-label">Délai annulation (minutes)</label><input type="number" name="cancellation_limit" class="form-control" value="<?= ab_escape(ab_setting('cancellation_limit', '1440')) ?>"><small class="text-muted">Ex: 1440 = 24h avant le RDV</small></div>
                <div class="col-12">
                    <div class="form-check form-switch mb-2"><input type="checkbox" name="require_phone" class="form-check-input" <?= ab_setting('require_phone', '1') === '1' ? 'checked' : '' ?>><label class="form-check-label">Téléphone obligatoire</label></div>
                    <div class="form-check form-switch mb-2"><input type="checkbox" name="allow_customer_cancel" class="form-check-input" <?= ab_setting('allow_customer_cancel', '1') === '1' ? 'checked' : '' ?>><label class="form-check-label">Permettre au client d'annuler</label></div>
                    <div class="form-check form-switch"><input type="checkbox" name="auto_confirm" class="form-check-input" <?= ab_setting('auto_confirm', '0') === '1' ? 'checked' : '' ?>><label class="form-check-label">Confirmation automatique (sans acompte)</label></div>
                </div>
            </div>

            <?php elseif ($tab === 'deposit'): ?>
            <div class="row g-3">
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Les acomptes se configurent prestation par prestation dans <a href="<?= ab_url('admin/index.php?page=services') ?>">Prestations</a>.
                        Ici vous pouvez configurer les instructions de paiement globales.
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Instructions de paiement pour les acomptes</label>
                    <textarea name="deposit_instructions" class="form-control" rows="6"><?= ab_escape(ab_setting('deposit_instructions')) ?></textarea>
                    <small class="text-muted">Ces instructions seront envoyées par email au client lorsqu'un acompte est requis.</small>
                </div>
            </div>

            <?php elseif ($tab === 'smtp'): ?>
            <div class="row g-3">
                <div class="col-12"><div class="alert alert-info"><i class="bi bi-info-circle"></i> Configurez votre serveur SMTP pour l'envoi d'emails (confirmations, rappels, etc.)</div></div>
                <div class="col-md-8"><label class="form-label">Serveur SMTP</label><input type="text" name="smtp_host" class="form-control" value="<?= ab_escape(ab_setting('smtp_host')) ?>" placeholder="smtp.gmail.com"></div>
                <div class="col-md-4"><label class="form-label">Port</label><input type="number" name="smtp_port" class="form-control" value="<?= ab_escape(ab_setting('smtp_port', '587')) ?>"></div>
                <div class="col-md-4"><label class="form-label">Chiffrement</label>
                    <select name="smtp_encryption" class="form-select">
                        <option value="tls" <?= ab_setting('smtp_encryption') === 'tls' ? 'selected' : '' ?>>TLS</option>
                        <option value="ssl" <?= ab_setting('smtp_encryption') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                        <option value="none" <?= ab_setting('smtp_encryption') === 'none' ? 'selected' : '' ?>>Aucun</option>
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label">Utilisateur SMTP</label><input type="text" name="smtp_username" class="form-control" value="<?= ab_escape(ab_setting('smtp_username')) ?>"></div>
                <div class="col-md-4"><label class="form-label">Mot de passe SMTP</label><input type="password" name="smtp_password" class="form-control" value="<?= ab_escape(ab_setting('smtp_password')) ?>"></div>
                <div class="col-md-6"><label class="form-label">Email expéditeur</label><input type="email" name="smtp_from_email" class="form-control" value="<?= ab_escape(ab_setting('smtp_from_email')) ?>"></div>
                <div class="col-md-6"><label class="form-label">Nom expéditeur</label><input type="text" name="smtp_from_name" class="form-control" value="<?= ab_escape(ab_setting('smtp_from_name')) ?>"></div>
                <div class="col-12">
                    <button type="button" class="btn btn-outline-info" onclick="testSmtp()"><i class="bi bi-envelope-check"></i> Envoyer un email de test</button>
                </div>
            </div>
            <script>
            function testSmtp() {
                fetch('<?= ab_url('api/index.php?route=test-smtp') ?>', {method:'POST', headers:{'Content-Type':'application/json'}})
                .then(r => r.json())
                .then(d => alert(d.success ? 'Email de test envoyé !' : 'Erreur: ' + d.error))
                .catch(() => alert('Erreur de connexion'));
            }
            </script>

            <?php elseif ($tab === 'google'): ?>
            <div class="row g-3">
                <div class="col-12"><div class="alert alert-info"><i class="bi bi-info-circle"></i> Configurez la synchronisation Google Calendar. Créez un projet sur <a href="https://console.developers.google.com" target="_blank">Google Cloud Console</a> et activez l'API Google Calendar.</div></div>
                <div class="col-md-6"><label class="form-label">Client ID</label><input type="text" name="google_client_id" class="form-control" value="<?= ab_escape(ab_setting('google_client_id')) ?>"></div>
                <div class="col-md-6"><label class="form-label">Client Secret</label><input type="text" name="google_client_secret" class="form-control" value="<?= ab_escape(ab_setting('google_client_secret')) ?>"></div>
                <div class="col-12"><label class="form-label">URI de redirection</label><input type="text" name="google_redirect_uri" class="form-control" value="<?= ab_escape(ab_setting('google_redirect_uri', ab_url('admin/index.php?page=google-callback'))) ?>"><small class="text-muted">Ajoutez cette URL dans les URI de redirection autorisés de votre projet Google.</small></div>
                <?php
                $gcal = new GoogleCalendar();
                if ($gcal->isConfigured()):
                    $syncStatus = $db->fetchOne("SELECT * FROM ab_google_sync WHERE provider_id = ?", [Auth::userId()]);
                ?>
                <div class="col-12">
                    <hr>
                    <?php if ($syncStatus && $syncStatus['sync_enabled']): ?>
                    <div class="alert alert-success"><i class="bi bi-check-circle"></i> Google Calendar connecté. Dernière sync: <?= $syncStatus['last_sync'] ? ab_format_date($syncStatus['last_sync']) : 'jamais' ?></div>
                    <?php else: ?>
                    <a href="<?= $gcal->getAuthUrl(Auth::userId()) ?>" class="btn btn-outline-primary"><i class="bi bi-google"></i> Connecter Google Calendar</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php elseif ($tab === 'appearance'): ?>
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Couleur principale</label><input type="color" name="primary_color" class="form-control form-control-color w-100" value="<?= ab_escape(ab_setting('primary_color', '#e91e63')) ?>"></div>
                <div class="col-md-4"><label class="form-label">Couleur secondaire</label><input type="color" name="secondary_color" class="form-control form-control-color w-100" value="<?= ab_escape(ab_setting('secondary_color', '#9c27b0')) ?>"></div>
                <div class="col-md-4">
                    <label class="form-label">Widget intégrable</label>
                    <div class="form-check form-switch mt-2"><input type="checkbox" name="embed_enabled" class="form-check-input" <?= ab_setting('embed_enabled', '1') === '1' ? 'checked' : '' ?>><label class="form-check-label">Activer</label></div>
                </div>
                <div class="col-12">
                    <label class="form-label">Code d'intégration (WordPress / HTML)</label>
                    <div class="input-group">
                        <input type="text" class="form-control" readonly value='<iframe src="<?= ab_url('public/embed.php') ?>" width="100%" height="700" frameborder="0"></iframe>' id="embedCode">
                        <button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('embedCode').value);this.innerHTML='<i class=\'bi bi-check\'></i> Copié'"><i class="bi bi-clipboard"></i></button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <hr>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check"></i> Enregistrer</button>
        </form>
    </div>
</div>
