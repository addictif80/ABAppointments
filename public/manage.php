<?php
/**
 * ABAppointments - Customer Appointment Management
 */
require_once __DIR__ . '/../core/App.php';

$db = Database::getInstance();
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

// The customer must be logged into their account (email + date of birth) and that
// account must own this appointment before they can view or change it.
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $loginEmail = trim($_POST['email'] ?? '');
    $loginDob = ab_parse_dob(trim($_POST['date_of_birth'] ?? ''));

    if (!$loginEmail || !filter_var($loginEmail, FILTER_VALIDATE_EMAIL)) {
        $loginError = 'Veuillez entrer une adresse email valide.';
    } elseif (!$loginDob) {
        $loginError = 'Date de naissance invalide. Utilisez le format JJ.MM.AAAA.';
    } else {
        $found = $db->fetchOne(
            "SELECT * FROM ab_customers WHERE email = ? AND date_of_birth = ?",
            [$loginEmail, $loginDob]
        );
        if ($found) {
            $_SESSION['customer_id'] = $found['id'];
            $_SESSION['customer_email'] = $found['email'];
            $_SESSION['customer_name'] = $found['first_name'] . ' ' . $found['last_name'];
            ab_redirect(ab_url('manage/' . $hash));
        } else {
            $loginError = 'Identifiants incorrects. Vérifiez votre email et date de naissance.';
        }
    }
}

$isOwner = isset($_SESSION['customer_id']) && (int)$_SESSION['customer_id'] === (int)$appointment['customer_id'];

// Handle cancellation / reschedule (owner only)
$message = '';
$error = '';
if ($isOwner && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'cancel') {
        if ($manager->cancelByHash($hash)) {
            $message = 'Votre rendez-vous a été annulé. Un email de confirmation vous a été envoyé.';
            $appointment = $manager->getByHash($hash);
        } else {
            $error = 'Impossible d\'annuler ce rendez-vous. Le délai d\'annulation est peut-être dépassé.';
        }
    } elseif ($_POST['action'] === 'reschedule' && isset($_POST['date'], $_POST['time'])) {
        $newStartDatetime = $_POST['date'] . ' ' . $_POST['time'] . ':00';
        $result = $manager->rescheduleByHash($hash, $newStartDatetime);
        if ($result['success']) {
            $message = 'Votre rendez-vous a été déplacé. Un email de confirmation vous a été envoyé.';
            $appointment = $manager->getByHash($hash);
        } else {
            $errors = [
                'not_allowed' => 'La modification en ligne n\'est pas autorisée pour ce rendez-vous.',
                'too_late' => 'Le délai de modification est dépassé.',
                'slot_unavailable' => 'Ce créneau n\'est plus disponible. Merci d\'en choisir un autre.',
                'conflict' => 'Ce créneau est déjà occupé.',
                'not_found' => 'Rendez-vous introuvable.',
                'service_not_found' => 'Prestation introuvable.',
            ];
            $error = $errors[$result['error']] ?? 'Erreur lors de la modification.';
        }
    }
}

$primaryColor = ab_setting('primary_color', '#e91e63');
$businessName = ab_setting('business_name', 'ABAppointments');
$canCancel = ab_setting('allow_customer_cancel', '1') === '1'
    && in_array($appointment['status'], ['pending', 'confirmed'])
    && strtotime($appointment['start_datetime']) - time() > (int)ab_setting('cancellation_limit', '1440') * 60;
$canReschedule = $canCancel; // same rules govern both self-service actions
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
        html, body { overflow-x: hidden; max-width: 100%; }
        body { background: #f8f9fa; }
        .manage-card { max-width: 600px; margin: 40px auto; padding: 0 10px; }
        .status-badge { font-size: 1rem; }
        .header-bar { background: <?= $primaryColor ?>; color: #fff; padding: 20px; text-align: center; position: relative; }
        .prestataire-link { position: absolute; top: 10px; right: 15px; color: rgba(255,255,255,0.7); font-size: 0.75rem; text-decoration: none; background: rgba(0,0,0,0.18); padding: 5px 12px; border-radius: 20px; transition: all 0.2s; white-space: nowrap; }
        .prestataire-link:hover { color: #fff; background: rgba(0,0,0,0.35); }
        .table td { word-break: break-word; }
        .time-slot-btn.selected { background: <?= $primaryColor ?>; color: #fff; border-color: <?= $primaryColor ?>; }
        @media (max-width: 480px) {
            .header-bar { padding: 16px 15px 20px; }
            .header-bar h4 { font-size: 1.1rem; }
            .manage-card { margin: 20px auto; }
            .card-body { padding: 1rem; }
            .table td { font-size: 0.9rem; padding: 6px 8px; }
            .btn { font-size: 0.9rem; }
        }
    </style>
</head>
<body>
    <div class="header-bar">
        <a href="<?= ab_url('admin/index.php') ?>" class="prestataire-link"><i class="bi bi-grid-3x3-gap-fill"></i> Prestataire</a>
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

                <?php if (!$isOwner): ?>
                <div class="card-footer bg-white">
                    <p class="text-muted small"><i class="bi bi-lock"></i> Connectez-vous à votre compte pour modifier ou annuler ce rendez-vous.</p>
                    <?php if ($loginError): ?>
                    <div class="alert alert-danger py-2"><?= ab_escape($loginError) ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="login">
                        <div class="mb-2">
                            <label class="form-label small">Adresse email</label>
                            <input type="email" name="email" class="form-control form-control-sm" required
                                   value="<?= ab_escape($_POST['email'] ?? $appointment['customer_email'] ?? '') ?>">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Date de naissance</label>
                            <input type="text" name="date_of_birth" class="form-control form-control-sm" required
                                   placeholder="JJ.MM.AAAA" maxlength="10" id="login-dob">
                        </div>
                        <button type="submit" class="btn btn-sm w-100" style="background:<?= $primaryColor ?>;color:#fff;">
                            <i class="bi bi-box-arrow-in-right"></i> Se connecter
                        </button>
                    </form>
                </div>
                <?php else: ?>

                <?php if ($canReschedule): ?>
                <div class="card-footer bg-white">
                    <button type="button" class="btn btn-outline-primary w-100 mb-2" data-bs-toggle="collapse" data-bs-target="#rescheduleBox">
                        <i class="bi bi-calendar-event"></i> Modifier la date/heure
                    </button>
                    <div class="collapse" id="rescheduleBox">
                        <div class="border rounded p-3">
                            <div class="mb-2">
                                <label class="form-label small">Nouvelle date</label>
                                <input type="date" id="reschedule-date" class="form-control form-control-sm" min="<?= date('Y-m-d') ?>">
                            </div>
                            <div id="reschedule-slots" class="d-flex flex-wrap gap-2 mb-2"></div>
                            <form method="POST" id="reschedule-form">
                                <input type="hidden" name="action" value="reschedule">
                                <input type="hidden" name="date" id="reschedule-date-value">
                                <input type="hidden" name="time" id="reschedule-time-value">
                                <button type="submit" class="btn btn-sm btn-primary w-100" id="reschedule-submit" disabled>
                                    <i class="bi bi-check"></i> Confirmer le nouveau créneau
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($canCancel): ?>
                <div class="card-footer bg-white">
                    <form method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir annuler ce rendez-vous ?')">
                        <input type="hidden" name="action" value="cancel">
                        <button class="btn btn-outline-danger w-100"><i class="bi bi-x-circle"></i> Annuler le rendez-vous</button>
                    </form>
                </div>
                <?php endif; ?>

                <div class="card-footer bg-white text-center">
                    <a href="<?= ab_url('public/account.php') ?>" class="small"><i class="bi bi-person-circle"></i> Voir tous mes rendez-vous</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <style>.badge-pending{background:#ffc107;color:#000}.badge-confirmed{background:#28a745}.badge-cancelled{background:#dc3545}.badge-completed{background:#6c757d}.badge-paid{background:#28a745}.badge-no_show{background:#fd7e14}</style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const loginDob = document.getElementById('login-dob');
    if (loginDob) {
        loginDob.addEventListener('input', function() {
            let v = this.value.replace(/\D/g, '');
            if (v.length > 2) v = v.substring(0,2) + '.' + v.substring(2);
            if (v.length > 5) v = v.substring(0,5) + '.' + v.substring(5);
            this.value = v.substring(0, 10);
        });
    }

    <?php if ($isOwner && $canReschedule): ?>
    const dateInput = document.getElementById('reschedule-date');
    const slotsBox = document.getElementById('reschedule-slots');
    const dateValue = document.getElementById('reschedule-date-value');
    const timeValue = document.getElementById('reschedule-time-value');
    const submitBtn = document.getElementById('reschedule-submit');
    const API_URL = '<?= ab_url('api/index.php') ?>';
    const SERVICE_ID = <?= (int)$appointment['service_id'] ?>;
    const PROVIDER_ID = <?= (int)$appointment['provider_id'] ?>;

    dateInput.addEventListener('change', function() {
        submitBtn.disabled = true;
        timeValue.value = '';
        slotsBox.innerHTML = '<div class="text-muted small">Chargement des créneaux...</div>';
        fetch(API_URL + '?route=available-slots&service_id=' + SERVICE_ID + '&provider_id=' + PROVIDER_ID + '&date=' + dateInput.value)
            .then(r => r.json())
            .then(data => {
                slotsBox.innerHTML = '';
                if (!data.slots || data.slots.length === 0) {
                    slotsBox.innerHTML = '<div class="text-muted small">Aucun créneau disponible ce jour-là.</div>';
                    return;
                }
                data.slots.forEach(time => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'btn btn-sm btn-outline-secondary time-slot-btn';
                    btn.textContent = time;
                    btn.addEventListener('click', function() {
                        document.querySelectorAll('.time-slot-btn').forEach(b => b.classList.remove('selected'));
                        btn.classList.add('selected');
                        dateValue.value = dateInput.value;
                        timeValue.value = time;
                        submitBtn.disabled = false;
                    });
                    slotsBox.appendChild(btn);
                });
            });
    });
    <?php endif; ?>
    </script>
</body>
</html>
