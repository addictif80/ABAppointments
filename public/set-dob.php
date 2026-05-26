<?php
/**
 * ABAppointments - Set Date of Birth via token link
 */
require_once __DIR__ . '/../core/App.php';

$primaryColor   = ab_setting('primary_color', '#e91e63');
$secondaryColor = ab_setting('secondary_color', '#9c27b0');
$businessName   = ab_setting('business_name', 'ABAppointments');
$db = Database::getInstance();

$token    = trim($_GET['token'] ?? '');
$customer = null;
$error    = '';
$success  = false;

// Validate token
if ($token) {
    $customer = $db->fetchOne(
        "SELECT id, first_name, last_name, email FROM ab_customers
         WHERE dob_token = ? AND dob_token_expires > NOW() AND date_of_birth IS NULL",
        [$token]
    );
}

if (!$token || !$customer) {
    $error = 'Ce lien est invalide ou a expiré. Contactez-nous pour recevoir un nouveau lien.';
}

// Handle form submission
if ($customer && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $dobRaw = trim($_POST['date_of_birth'] ?? '');
    $dobDb  = $dobRaw !== '' ? ab_parse_dob($dobRaw) : null;

    if (!$dobDb) {
        $error = 'Date de naissance invalide. Utilisez le format JJ.MM.AAAA.';
    } else {
        $db->update('ab_customers',
            ['date_of_birth' => $dobDb, 'dob_token' => null, 'dob_token_expires' => null],
            'id = ?', [$customer['id']]
        );
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compléter mon profil – <?= ab_escape($businessName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --ab-primary: <?= $primaryColor ?>; --ab-secondary: <?= $secondaryColor ?>; }
        body { background: #f8f9fa; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
        .booking-header { background: linear-gradient(135deg, var(--ab-primary), var(--ab-secondary)); color: #fff; padding: 40px 20px; text-align: center; }
        .booking-header h1 { font-size: 1.8rem; margin-bottom: 5px; }
        .form-card { max-width: 440px; margin: -30px auto 40px; padding: 0 15px; }
        .card { border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.08); border-radius: 12px; }
        .btn-ab { background: var(--ab-primary); border: none; color: #fff; padding: 12px 30px; border-radius: 8px; font-weight: 600; }
        .btn-ab:hover { background: color-mix(in srgb, var(--ab-primary) 85%, black); color: #fff; }
    </style>
</head>
<body>

<div class="booking-header">
    <h1><i class="bi bi-calendar-heart"></i> <?= ab_escape($businessName) ?></h1>
    <p class="mb-0">Compléter mon profil</p>
</div>

<div class="form-card">
    <div class="card p-4">

    <?php if ($success): ?>

        <div class="text-center py-3">
            <div style="font-size:3.5rem;color:#28a745;"><i class="bi bi-check-circle-fill"></i></div>
            <h4 class="mt-3">Merci, <?= ab_escape($customer['first_name']) ?> !</h4>
            <p class="text-muted">Votre date de naissance a bien été enregistrée.<br>Vous pouvez désormais prendre rendez-vous en ligne.</p>
            <a href="<?= ab_url('public/') ?>" class="btn btn-ab mt-2">
                <i class="bi bi-calendar-plus"></i> Prendre un rendez-vous
            </a>
        </div>

    <?php elseif (!$customer): ?>

        <div class="text-center py-3">
            <div style="font-size:3rem;color:#dc3545;"><i class="bi bi-exclamation-circle"></i></div>
            <h5 class="mt-3">Lien invalide</h5>
            <p class="text-muted"><?= ab_escape($error) ?></p>
            <a href="<?= ab_url('public/') ?>" class="btn btn-outline-secondary mt-2">
                <i class="bi bi-house"></i> Accueil
            </a>
        </div>

    <?php else: ?>

        <h5 class="mb-1"><i class="bi bi-cake2"></i> Date de naissance</h5>
        <p class="text-muted mb-4">
            Bonjour <strong><?= ab_escape($customer['first_name']) ?></strong>, veuillez renseigner votre date de naissance pour continuer à réserver en ligne.
        </p>

        <?php if ($error): ?>
        <div class="alert alert-danger py-2"><?= ab_escape($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-4">
                <label class="form-label fw-semibold">Date de naissance *</label>
                <input type="text" name="date_of_birth" id="dob-field" class="form-control form-control-lg text-center"
                       required placeholder="JJ.MM.AAAA" maxlength="10" autocomplete="bday"
                       style="letter-spacing:2px;font-size:1.4rem;">
            </div>
            <button type="submit" class="btn btn-ab w-100 btn-lg">
                <i class="bi bi-check-circle"></i> Enregistrer
            </button>
        </form>

    <?php endif; ?>

    </div>
</div>

<footer class="text-center py-3 text-muted small">
    <?= ab_escape($businessName) ?> &middot; <?= ab_escape(ab_setting('business_phone')) ?>
</footer>

<script>
const dobField = document.getElementById('dob-field');
if (dobField) {
    dobField.addEventListener('input', function() {
        let v = this.value.replace(/\D/g, '');
        if (v.length > 2) v = v.substring(0, 2) + '.' + v.substring(2);
        if (v.length > 5) v = v.substring(0, 5) + '.' + v.substring(5);
        this.value = v.substring(0, 10);
    });
}
</script>
</body>
</html>
