<?php
$pageTitle = 'Parametres';
$settings = new Settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'test_cyberpanel') {
    header('Content-Type: application/json');
    try {
        $cyberpanel = new CyberPanelAPI();
        if (!$cyberpanel->isConfigured()) {
            echo json_encode(['success' => false, 'message' => 'CyberPanel non configure (URL ou mot de passe manquant).']);
        } else {
            $result = $cyberpanel->verifyConnection();
            echo json_encode(['success' => true, 'message' => 'Connexion reussie !']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tab = $_POST['tab'] ?? 'general';
    $fields = [];

    switch ($tab) {
        case 'general':
            $fields = ['site_name', 'site_url', 'company_name', 'company_email', 'company_phone', 'company_address', 'company_siret', 'company_tva', 'primary_color', 'logo_url', 'terms_url', 'privacy_url'];
            break;
        case 'billing':
            $fields = ['currency', 'currency_symbol', 'tax_rate', 'invoice_prefix', 'payment_grace_days', 'suspension_grace_days', 'deletion_grace_days', 'reminder_days_before'];
            break;
        case 'stripe':
            $fields = ['stripe_public_key', 'stripe_secret_key', 'stripe_webhook_secret'];
            break;
        case 'proxmox':
            $fields = ['proxmox_host', 'proxmox_port', 'proxmox_user', 'proxmox_token_id', 'proxmox_token_secret', 'proxmox_default_node', 'proxmox_default_storage', 'proxmox_default_bridge', 'proxmox_vmid_start'];
            break;
        case 'cyberpanel':
            $fields = ['cyberpanel_url', 'cyberpanel_api_url', 'cyberpanel_admin_user', 'cyberpanel_admin_pass'];
            break;
        case 'navidrome':
            $fields = ['navidrome_url', 'navidrome_admin_user', 'navidrome_admin_pass'];
            break;
        case 'smtp':
            $fields = ['smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_encryption', 'smtp_from_email', 'smtp_from_name'];
            break;
        case 'uptime':
            $fields = ['uptime_kuma_url', 'uptime_kuma_api_key'];
            break;
    }

    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            $settings->set($field, trim($_POST[$field]));
        }
    }
    wp_flash('success', 'Parametres enregistres.');
    wp_redirect(wp_url("admin/?page=settings&tab=$tab"));
}

$tab = $_GET['tab'] ?? 'general';
?>

<ul class="nav nav-tabs mb-4">
    <?php foreach (['general' => 'General', 'billing' => 'Facturation', 'stripe' => 'Stripe', 'smtp' => 'SMTP', 'proxmox' => 'Proxmox', 'cyberpanel' => 'CyberPanel', 'navidrome' => 'Navidrome', 'uptime' => 'Uptime Kuma'] as $k => $v): ?>
    <li class="nav-item"><a class="nav-link <?= $tab === $k ? 'active' : '' ?>" href="<?= wp_url("admin/?page=settings&tab=$k") ?>"><?= $v ?></a></li>
    <?php endforeach; ?>
</ul>

<div class="card">
    <div class="card-body">
        <form method="POST">
            <?= wp_csrf_field() ?>
            <input type="hidden" name="tab" value="<?= wp_escape($tab) ?>">

            <?php if ($tab === 'general'): ?>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Nom du site</label><input type="text" name="site_name" class="form-control" value="<?= wp_escape($settings->get('site_name')) ?>"></div>
                <div class="col-md-6"><label class="form-label">URL du site</label><input type="url" name="site_url" class="form-control" value="<?= wp_escape($settings->get('site_url')) ?>"></div>
                <div class="col-md-6"><label class="form-label">Nom de l'entreprise</label><input type="text" name="company_name" class="form-control" value="<?= wp_escape($settings->get('company_name')) ?>"></div>
                <div class="col-md-6"><label class="form-label">Email entreprise</label><input type="email" name="company_email" class="form-control" value="<?= wp_escape($settings->get('company_email')) ?>"></div>
                <div class="col-md-6"><label class="form-label">Telephone</label><input type="text" name="company_phone" class="form-control" value="<?= wp_escape($settings->get('company_phone')) ?>"></div>
                <div class="col-md-6"><label class="form-label">SIRET</label><input type="text" name="company_siret" class="form-control" value="<?= wp_escape($settings->get('company_siret')) ?>"></div>
                <div class="col-12"><label class="form-label">Adresse</label><textarea name="company_address" class="form-control" rows="2"><?= wp_escape($settings->get('company_address')) ?></textarea></div>
                <div class="col-md-6"><label class="form-label">N TVA</label><input type="text" name="company_tva" class="form-control" value="<?= wp_escape($settings->get('company_tva')) ?>"></div>
                <div class="col-md-6"><label class="form-label">Couleur primaire</label><input type="color" name="primary_color" class="form-control form-control-color" value="<?= wp_escape($settings->get('primary_color', '#4F46E5')) ?>"></div>
                <div class="col-md-6"><label class="form-label">URL logo</label><input type="text" name="logo_url" class="form-control" value="<?= wp_escape($settings->get('logo_url')) ?>"></div>
                <div class="col-md-3"><label class="form-label">URL CGV</label><input type="text" name="terms_url" class="form-control" value="<?= wp_escape($settings->get('terms_url')) ?>"></div>
                <div class="col-md-3"><label class="form-label">URL Confidentialite</label><input type="text" name="privacy_url" class="form-control" value="<?= wp_escape($settings->get('privacy_url')) ?>"></div>
            </div>

            <?php elseif ($tab === 'billing'): ?>
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Devise</label><input type="text" name="currency" class="form-control" value="<?= wp_escape($settings->get('currency', 'EUR')) ?>"></div>
                <div class="col-md-4"><label class="form-label">Symbole devise</label><input type="text" name="currency_symbol" class="form-control" value="<?= wp_escape($settings->get('currency_symbol', '€')) ?>"></div>
                <div class="col-md-4"><label class="form-label">Taux TVA (%)</label><input type="number" name="tax_rate" class="form-control" step="0.01" value="<?= wp_escape($settings->get('tax_rate', '20')) ?>"></div>
                <div class="col-md-6"><label class="form-label">Prefixe facture</label><input type="text" name="invoice_prefix" class="form-control" value="<?= wp_escape($settings->get('invoice_prefix', 'INV-')) ?>"></div>
                <div class="col-md-6"><label class="form-label">Delai de grace paiement (jours)</label><input type="number" name="payment_grace_days" class="form-control" value="<?= wp_escape($settings->get('payment_grace_days', '7')) ?>"></div>
                <div class="col-md-4"><label class="form-label">Delai suspension (jours)</label><input type="number" name="suspension_grace_days" class="form-control" value="<?= wp_escape($settings->get('suspension_grace_days', '14')) ?>"></div>
                <div class="col-md-4"><label class="form-label">Delai suppression (jours)</label><input type="number" name="deletion_grace_days" class="form-control" value="<?= wp_escape($settings->get('deletion_grace_days', '30')) ?>"></div>
                <div class="col-md-4"><label class="form-label">Rappel avant echeance (jours)</label><input type="number" name="reminder_days_before" class="form-control" value="<?= wp_escape($settings->get('reminder_days_before', '3')) ?>"></div>
            </div>

            <?php elseif ($tab === 'stripe'): ?>
            <div class="row g-3">
                <div class="col-12"><label class="form-label">Cle publique</label><input type="text" name="stripe_public_key" class="form-control" value="<?= wp_escape($settings->get('stripe_public_key')) ?>" placeholder="pk_..."></div>
                <div class="col-12"><label class="form-label">Cle secrete</label><input type="password" name="stripe_secret_key" class="form-control" value="<?= wp_escape($settings->get('stripe_secret_key')) ?>" placeholder="sk_..."></div>
                <div class="col-12"><label class="form-label">Secret Webhook</label><input type="password" name="stripe_webhook_secret" class="form-control" value="<?= wp_escape($settings->get('stripe_webhook_secret')) ?>" placeholder="whsec_..."></div>
                <div class="col-12"><div class="alert alert-info"><i class="bi bi-info-circle me-1"></i> URL du webhook Stripe : <code><?= wp_escape($settings->get('site_url')) ?>/api/stripe-webhook.php</code></div></div>
            </div>

            <?php elseif ($tab === 'smtp'): ?>
            <div class="row g-3">
                <div class="col-md-8"><label class="form-label">Serveur SMTP</label><input type="text" name="smtp_host" class="form-control" value="<?= wp_escape($settings->get('smtp_host')) ?>"></div>
                <div class="col-md-4"><label class="form-label">Port</label><input type="number" name="smtp_port" class="form-control" value="<?= wp_escape($settings->get('smtp_port', '587')) ?>"></div>
                <div class="col-md-6"><label class="form-label">Utilisateur</label><input type="text" name="smtp_user" class="form-control" value="<?= wp_escape($settings->get('smtp_user')) ?>"></div>
                <div class="col-md-6"><label class="form-label">Mot de passe</label><input type="password" name="smtp_pass" class="form-control" value="<?= wp_escape($settings->get('smtp_pass')) ?>"></div>
                <div class="col-md-4"><label class="form-label">Chiffrement</label><select name="smtp_encryption" class="form-select"><option value="tls" <?= $settings->get('smtp_encryption') === 'tls' ? 'selected' : '' ?>>TLS</option><option value="ssl" <?= $settings->get('smtp_encryption') === 'ssl' ? 'selected' : '' ?>>SSL</option></select></div>
                <div class="col-md-4"><label class="form-label">Email expediteur</label><input type="email" name="smtp_from_email" class="form-control" value="<?= wp_escape($settings->get('smtp_from_email')) ?>"></div>
                <div class="col-md-4"><label class="form-label">Nom expediteur</label><input type="text" name="smtp_from_name" class="form-control" value="<?= wp_escape($settings->get('smtp_from_name')) ?>"></div>
            </div>

            <?php elseif ($tab === 'proxmox'): ?>
            <div class="row g-3">
                <div class="col-md-8"><label class="form-label">Hote Proxmox</label><input type="text" name="proxmox_host" class="form-control" value="<?= wp_escape($settings->get('proxmox_host')) ?>" placeholder="192.168.1.100"></div>
                <div class="col-md-4"><label class="form-label">Port</label><input type="number" name="proxmox_port" class="form-control" value="<?= wp_escape($settings->get('proxmox_port', '8006')) ?>"></div>
                <div class="col-md-4"><label class="form-label">Utilisateur</label><input type="text" name="proxmox_user" class="form-control" value="<?= wp_escape($settings->get('proxmox_user', 'root@pam')) ?>"></div>
                <div class="col-md-4"><label class="form-label">Token ID</label><input type="text" name="proxmox_token_id" class="form-control" value="<?= wp_escape($settings->get('proxmox_token_id')) ?>"></div>
                <div class="col-md-4"><label class="form-label">Token Secret</label><input type="password" name="proxmox_token_secret" class="form-control" value="<?= wp_escape($settings->get('proxmox_token_secret')) ?>"></div>
                <div class="col-md-3"><label class="form-label">Node par defaut</label><input type="text" name="proxmox_default_node" class="form-control" value="<?= wp_escape($settings->get('proxmox_default_node', 'pve')) ?>"></div>
                <div class="col-md-3"><label class="form-label">Storage par defaut</label><input type="text" name="proxmox_default_storage" class="form-control" value="<?= wp_escape($settings->get('proxmox_default_storage', 'local-lvm')) ?>"></div>
                <div class="col-md-3"><label class="form-label">Bridge par defaut</label><input type="text" name="proxmox_default_bridge" class="form-control" value="<?= wp_escape($settings->get('proxmox_default_bridge', 'vmbr0')) ?>"></div>
                <div class="col-md-3"><label class="form-label">VMID debut</label><input type="number" name="proxmox_vmid_start" class="form-control" value="<?= wp_escape($settings->get('proxmox_vmid_start', '200')) ?>"></div>
            </div>

            <?php elseif ($tab === 'cyberpanel'): ?>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">URL publique CyberPanel</label><input type="url" name="cyberpanel_url" class="form-control" value="<?= wp_escape($settings->get('cyberpanel_url')) ?>" placeholder="https://cp.example.com"><small class="form-text text-muted">URL visible par les clients</small></div>
                <div class="col-md-6"><label class="form-label">URL API CyberPanel</label><input type="url" name="cyberpanel_api_url" class="form-control" value="<?= wp_escape($settings->get('cyberpanel_api_url')) ?>" placeholder="https://10.0.0.1:8090"><small class="form-text text-muted">URL interne pour les appels API (IP:8090, Tailscale...)</small></div>
                <div class="col-md-6"><label class="form-label">Utilisateur admin</label><input type="text" name="cyberpanel_admin_user" class="form-control" value="<?= wp_escape($settings->get('cyberpanel_admin_user', 'admin')) ?>"></div>
                <div class="col-md-6"><label class="form-label">Mot de passe admin</label><input type="password" name="cyberpanel_admin_pass" class="form-control" value="<?= wp_escape($settings->get('cyberpanel_admin_pass')) ?>"></div>
                <div class="col-12">
                    <div class="alert alert-info mb-0"><strong>Important :</strong> L'acces API doit etre active dans CyberPanel &rarr; Users &rarr; API Access pour l'utilisateur admin.</div>
                </div>
                <div class="col-12">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="testCyberpanelBtn">Tester la connexion</button>
                    <span id="testCyberpanelResult" class="ms-2"></span>
                </div>
            </div>

            <?php elseif ($tab === 'navidrome'): ?>
            <div class="row g-3">
                <div class="col-12"><label class="form-label">URL Navidrome</label><input type="url" name="navidrome_url" class="form-control" value="<?= wp_escape($settings->get('navidrome_url')) ?>" placeholder="https://music.example.com"></div>
                <div class="col-md-6"><label class="form-label">Utilisateur admin</label><input type="text" name="navidrome_admin_user" class="form-control" value="<?= wp_escape($settings->get('navidrome_admin_user')) ?>"></div>
                <div class="col-md-6"><label class="form-label">Mot de passe admin</label><input type="password" name="navidrome_admin_pass" class="form-control" value="<?= wp_escape($settings->get('navidrome_admin_pass')) ?>"></div>
            </div>

            <?php elseif ($tab === 'uptime'): ?>
            <div class="row g-3">
                <div class="col-12"><label class="form-label">URL Uptime Kuma</label><input type="url" name="uptime_kuma_url" class="form-control" value="<?= wp_escape($settings->get('uptime_kuma_url')) ?>" placeholder="https://status.example.com"></div>
                <div class="col-12"><label class="form-label">Cle API</label><input type="password" name="uptime_kuma_api_key" class="form-control" value="<?= wp_escape($settings->get('uptime_kuma_api_key')) ?>"></div>
            </div>
            <?php endif; ?>

            <div class="mt-4"><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Enregistrer</button></div>
        </form>
    </div>
</div>
<script>
document.getElementById('testCyberpanelBtn')?.addEventListener('click', function() {
    var btn = this, result = document.getElementById('testCyberpanelResult');
    btn.disabled = true; result.textContent = 'Test en cours...'; result.className = 'ms-2 text-muted';
    var fd = new FormData();
    fd.append('action', 'test_cyberpanel');
    fd.append('csrf_token', document.querySelector('input[name=csrf_token]').value);
    fetch(location.href, {method: 'POST', body: fd}).then(r => r.json()).then(function(data) {
        result.textContent = data.message;
        result.className = 'ms-2 ' + (data.success ? 'text-success' : 'text-danger');
        btn.disabled = false;
    }).catch(function() {
        result.textContent = 'Erreur de connexion';
        result.className = 'ms-2 text-danger';
        btn.disabled = false;
    });
});
</script>
