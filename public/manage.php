<?php
/**
 * ABAppointments - Customer Appointment Management
 */
require_once __DIR__ . '/../core/App.php';

$hash = $_GET['hash'] ?? '';
if (empty($hash)) {
    http_response_code(404);
    exit('Rendez-vous non trouvé.');
}

$manager = new AppointmentManager();
$appointment = $manager->getByHash($hash);

if (!$appointment) {
    http_response_code(404);
    exit('Rendez-vous non trouvé.');
}

// Handle cancellation
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    if ($manager->cancelByHash($hash)) {
        $message = 'Votre rendez-vous a été annulé.';
        $appointment = $manager->getByHash($hash);
    } else {
        $error = 'Impossible d\'annuler ce rendez-vous. Le délai d\'annulation est peut-être dépassé.';
    }
}

$primaryColor = ab_setting('primary_color', '#e91e63');
$businessName = ab_setting('business_name', 'ABAppointments');
$canCancel = ab_setting('allow_customer_cancel', '1') === '1'
    && in_array($appointment['status'], ['pending', 'confirmed'])
    && strtotime($appointment['start_datetime']) - time() > (int)ab_setting('cancellation_limit', '1440') * 60;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon rendez-vous - <?= ab_escape($businessName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .manage-card { max-width: 600px; margin: 40px auto; }
        .status-badge { font-size: 1rem; }
        .header-bar { background: <?= $primaryColor ?>; color: #fff; padding: 20px; text-align: center; }
    </style>
</head>
<body>
    <div class="header-bar">
        <h4><i class="bi bi-calendar-heart"></i> <?= ab_escape($businessName) ?></h4>
    </div>

    <div class="container">
        <div class="manage-card">
            <?php if ($message): ?>
            <div class="alert alert-success"><?= ab_escape($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="alert alert-danger"><?= ab_escape($error) ?></div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Mon rendez-vous</h5>
                    <span class="badge status-badge badge-<?= $appointment['status'] ?>"><?= $appointment['status'] ?></span>
                </div>
                <div class="card-body">
                    <table class="table mb-0">
                        <tr><td class="text-muted">Prestation</td><td class="fw-bold"><?= ab_escape($appointment['service_name']) ?></td></tr>
                        <tr><td class="text-muted">Date</td><td><?= ab_format_date($appointment['start_datetime']) ?></td></tr>
                        <tr><td class="text-muted">Heure</td><td><?= ab_format_time($appointment['start_datetime']) ?> - <?= ab_format_time($appointment['end_datetime']) ?></td></tr>
                        <tr><td class="text-muted">Durée</td><td><?= $appointment['service_duration'] ?> min</td></tr>
                        <tr><td class="text-muted">Prix</td><td><?= ab_format_price($appointment['service_price']) ?></td></tr>
                        <tr><td class="text-muted">Prestataire</td><td><?= ab_escape($appointment['provider_first_name'] . ' ' . $appointment['provider_last_name']) ?></td></tr>
                    </table>

                    <?php if ($appointment['deposit']): ?>
                    <div class="mt-3 p-3 rounded" style="background: <?= $appointment['deposit']['status'] === 'paid' ? '#d4edda' : '#fff3cd' ?>">
                        <h6><i class="bi bi-cash-coin"></i> Acompte</h6>
                        <p class="mb-1">Montant : <strong><?= ab_format_price($appointment['deposit']['amount']) ?></strong></p>
                        <p class="mb-1">Statut : <span class="badge badge-<?= $appointment['deposit']['status'] ?>"><?= $appointment['deposit']['status'] ?></span></p>
                        <?php if ($appointment['deposit']['status'] === 'pending'): ?>
                        <p class="mb-0">Date limite : <?= $appointment['deposit']['due_date'] ? ab_format_date($appointment['deposit']['due_date']) : '-' ?></p>
                        <hr>
                        <p class="mb-0 small"><?= nl2br(ab_escape(ab_setting('deposit_instructions'))) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if ($canCancel): ?>
                <div class="card-footer bg-white">
                    <form method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir annuler ce rendez-vous ?')">
                        <input type="hidden" name="action" value="cancel">
                        <button class="btn btn-outline-danger w-100"><i class="bi bi-x-circle"></i> Annuler le rendez-vous</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <style>.badge-pending{background:#ffc107;color:#000}.badge-confirmed{background:#28a745}.badge-cancelled{background:#dc3545}.badge-completed{background:#6c757d}.badge-paid{background:#28a745}.badge-no_show{background:#fd7e14}</style>
</body>
</html>
