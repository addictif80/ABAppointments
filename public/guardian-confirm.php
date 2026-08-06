<?php
/**
 * ABAppointments - Guardian Confirmation Page
 */
require_once __DIR__ . '/../core/App.php';

$primaryColor   = ab_setting('primary_color', '#e91e63');
$secondaryColor = ab_setting('secondary_color', '#9c27b0');
$businessName   = ab_setting('business_name', 'ABAppointments');

$token       = trim($_GET['token'] ?? '');
$appointment = null;
$error       = '';
$success     = false;

if ($token) {
    $db = Database::getInstance();
    $appointment = $db->fetchOne(
        "SELECT a.*,
                c.first_name as customer_first_name, c.last_name as customer_last_name,
                c.email as customer_email,
                s.name as service_name, s.duration as service_duration, s.price as service_price,
                u.first_name as provider_first_name, u.last_name as provider_last_name
         FROM ab_appointments a
         JOIN ab_customers c ON a.customer_id = c.id
         JOIN ab_services s ON a.service_id = s.id
         JOIN ab_users u ON a.provider_id = u.id
         WHERE a.guardian_token = ?
           AND a.guardian_token_expires > NOW()
           AND a.guardian_confirmed_at IS NULL
           AND a.status NOT IN ('cancelled')",
        [$token]
    );
}

if (!$token || !$appointment) {
    $error = 'Ce lien est invalide ou a expiré. Le rendez-vous a peut-être déjà été annulé, ou votre accord a déjà été enregistré.';
}

// Handle form submission (POST = confirm)
if ($appointment && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm') {
    $manager = new AppointmentManager();
    $result  = $manager->confirmByGuardianToken($token);
    if ($result) {
        $success = true;
        $appointment = $result; // updated data
    } else {
        $error = 'Une erreur est survenue. Ce lien est peut-être déjà utilisé.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation accompagnateur – <?= ab_escape($businessName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --ab-primary: <?= $primaryColor ?>; --ab-secondary: <?= $secondaryColor ?>; }
        html, body { overflow-x: hidden; max-width: 100%; }
        body { background: #f8f9fa; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
        .booking-header { background: linear-gradient(135deg, var(--ab-primary), var(--ab-secondary)); color: #fff; padding: 40px 20px; text-align: center; position: relative; }
        .booking-header h1 { font-size: 1.8rem; margin-bottom: 5px; }
        .form-card { max-width: 560px; margin: -30px auto 40px; padding: 0 15px; }
        .card { border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.08); border-radius: 12px; }
        .btn-confirm { background: #28a745; border: none; color: #fff; padding: 14px 32px; border-radius: 8px; font-weight: 700; font-size: 1.05rem; }
        .btn-confirm:hover { background: #218838; color: #fff; }
        .detail-row td { padding: 8px 12px; }
        .detail-row td:first-child { color: #666; font-weight: 500; white-space: nowrap; }
        .prestataire-link { position: absolute; top: 12px; right: 15px; color: rgba(255,255,255,0.7); font-size: 0.75rem; text-decoration: none; background: rgba(0,0,0,0.18); padding: 5px 12px; border-radius: 20px; transition: all 0.2s; }
        .prestataire-link:hover { color: #fff; background: rgba(0,0,0,0.35); }
    </style>
</head>
<body>

<div class="booking-header">
    <a href="<?= ab_url('admin/index.php') ?>" class="prestataire-link"><i class="bi bi-grid-3x3-gap-fill"></i> Prestataire</a>
    <h1><i class="bi bi-person-check-fill"></i> Confirmation accompagnateur</h1>
    <p class="mb-0"><?= ab_escape($businessName) ?></p>
</div>

<div class="form-card">
    <div class="card p-4">

    <?php if ($success): ?>
        <div class="text-center py-3">
            <div style="font-size:3.5rem;color:#28a745;"><i class="bi bi-check-circle-fill"></i></div>
            <h4 class="mt-3">Merci !</h4>
            <p class="text-muted">
                Votre accord a bien été enregistré.<br>
                Vous accompagnerez <strong><?= ab_escape($appointment['customer_first_name'] . ' ' . $appointment['customer_last_name']) ?></strong>
                lors de son rendez-vous le <strong><?= ab_format_date($appointment['start_datetime']) ?></strong>
                à <strong><?= ab_format_time($appointment['start_datetime']) ?></strong>.
            </p>
            <p class="text-muted small">L'établissement a été notifié. À bientôt !</p>
        </div>

    <?php elseif ($error): ?>
        <div class="text-center py-3">
            <div style="font-size:3rem;color:#dc3545;"><i class="bi bi-exclamation-circle"></i></div>
            <h5 class="mt-3">Lien invalide</h5>
            <p class="text-muted"><?= ab_escape($error) ?></p>
        </div>

    <?php else: ?>
        <h5 class="mb-1"><i class="bi bi-person-check-fill text-success"></i> Confirmation de présence</h5>
        <p class="text-muted mb-4">
            Bonjour <strong><?= ab_escape($appointment['guardian_first_name'] . ' ' . $appointment['guardian_last_name']) ?></strong>,
            vous avez été désigné(e) comme accompagnateur(trice) pour le rendez-vous suivant.
        </p>

        <table class="table detail-row mb-4">
            <tr><td>Client</td><td class="fw-semibold"><?= ab_escape($appointment['customer_first_name'] . ' ' . $appointment['customer_last_name']) ?></td></tr>
            <tr><td>Prestation</td><td><?= ab_escape($appointment['service_name']) ?></td></tr>
            <tr><td>Date</td><td><?= ab_format_date($appointment['start_datetime']) ?></td></tr>
            <tr><td>Heure</td><td><?= ab_format_time($appointment['start_datetime']) ?> (<?= $appointment['service_duration'] ?> min)</td></tr>
            <tr><td>Prestataire</td><td><?= ab_escape($appointment['provider_first_name'] . ' ' . $appointment['provider_last_name']) ?></td></tr>
        </table>

        <div class="alert alert-warning">
            <i class="bi bi-info-circle-fill"></i>
            En confirmant, vous attestez que vous serez <strong>présent(e)</strong> pour accompagner ce client lors de son rendez-vous.
        </div>

        <form method="POST" class="text-center mt-3">
            <input type="hidden" name="action" value="confirm">
            <button type="submit" class="btn btn-confirm w-100">
                <i class="bi bi-check-circle-fill"></i> Je confirme ma présence
            </button>
        </form>

    <?php endif; ?>

    </div>
</div>

<footer class="text-center py-3 text-muted small">
    <?= ab_escape($businessName) ?> &middot; <?= ab_escape(ab_setting('business_phone')) ?>
</footer>

</body>
</html>
