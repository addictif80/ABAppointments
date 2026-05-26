<?php
/**
 * ABAppointments - Customer Account Page
 */
require_once __DIR__ . '/../core/App.php';

$db = Database::getInstance();
$primaryColor = ab_setting('primary_color', '#e91e63');
$secondaryColor = ab_setting('secondary_color', '#9c27b0');
$businessName = ab_setting('business_name', 'ABAppointments');

$customer = null;
$impersonating = false;
$loginError = '';

// 1. Admin impersonation: Auth::check() && Auth::isAdmin() && $_GET['impersonate']
if (Auth::check() && Auth::isAdmin() && isset($_GET['impersonate'])) {
    $impersonateId = (int)$_GET['impersonate'];
    if ($impersonateId > 0) {
        $customer = $db->fetchOne("SELECT * FROM ab_customers WHERE id = ?", [$impersonateId]);
        $impersonating = ($customer !== null);
    }
}

// 2. Handle POST actions (logout, login) — only if not impersonating
if (!$impersonating) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $postAction = $_POST['action'] ?? '';

        if ($postAction === 'logout') {
            unset($_SESSION['customer_id'], $_SESSION['customer_email'], $_SESSION['customer_name']);
            ab_redirect(ab_url('public/account.php'));
        }

        if ($postAction === 'login') {
            $loginEmail = trim($_POST['email'] ?? '');
            $loginDobRaw = trim($_POST['date_of_birth'] ?? '');
            $loginDob = ab_parse_dob($loginDobRaw);

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
                    ab_redirect(ab_url('public/account.php'));
                } else {
                    $loginError = 'Identifiants incorrects. Vérifiez votre email et date de naissance.';
                }
            }
        }
    }

    // 3. Check existing customer session
    if (!$customer && isset($_SESSION['customer_id'])) {
        $customer = $db->fetchOne("SELECT * FROM ab_customers WHERE id = ?", [(int)$_SESSION['customer_id']]);
        if (!$customer) {
            // Session stale — clear it
            unset($_SESSION['customer_id'], $_SESSION['customer_email'], $_SESSION['customer_name']);
        }
    }
}

// If no customer at this point, show login form
$showLogin = ($customer === null);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon espace client - <?= ab_escape($businessName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --ab-primary: <?= $primaryColor ?>; --ab-secondary: <?= $secondaryColor ?>; }
        html, body { overflow-x: hidden; max-width: 100%; }
        body { background: #f8f9fa; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
        .booking-header { background: linear-gradient(135deg, var(--ab-primary), var(--ab-secondary)); color: #fff; padding: 40px 20px; text-align: center; position: relative; }
        .booking-header h1 { font-size: 1.8rem; margin-bottom: 5px; }
        .account-container { max-width: 860px; margin: -30px auto 40px; padding: 0 15px; position: relative; }
        .card { border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.08); border-radius: 12px; }
        .btn-ab { background: var(--ab-primary); border: none; color: #fff; padding: 12px 30px; border-radius: 8px; font-weight: 600; }
        .btn-ab:hover { background: color-mix(in srgb, var(--ab-primary) 85%, black); color: #fff; }
        .stat-card { text-align: center; padding: 20px; }
        .stat-card .stat-value { font-size: 1.8rem; font-weight: 700; color: var(--ab-primary); }
        .stat-card .stat-label { font-size: 0.85rem; color: #666; margin-top: 4px; }
        .badge-pending { background: #ffc107; color: #000; }
        .badge-confirmed { background: #198754; color: #fff; }
        .badge-cancelled { background: #dc3545; color: #fff; }
        .badge-completed { background: #0d6efd; color: #fff; }
        .impersonation-banner { background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 12px 20px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        /* Prestataire link */
        .prestataire-link { position: absolute; top: 12px; right: 15px; color: rgba(255,255,255,0.7); font-size: 0.75rem; text-decoration: none; background: rgba(0,0,0,0.18); padding: 5px 12px; border-radius: 20px; transition: all 0.2s; white-space: nowrap; }
        .prestataire-link:hover { color: #fff; background: rgba(0,0,0,0.35); }
        /* Mobile responsive */
        @media (max-width: 480px) {
            .booking-header { padding: 30px 15px 35px; }
            .booking-header h1 { font-size: 1.4rem; }
            .account-container { padding: 0 10px; }
            .card.p-4 { padding: 1rem !important; }
            .stat-card { padding: 12px 8px; }
            .stat-card .stat-value { font-size: 1.4rem; }
            .table td, .table th { font-size: 0.82rem; padding: 6px 6px; }
            .d-flex.justify-content-between { flex-wrap: wrap; gap: 8px; }
            .btn-ab { padding: 10px 18px; font-size: 0.9rem; }
        }
    </style>
</head>
<body>
    <div class="booking-header">
        <a href="<?= ab_url('admin/index.php') ?>" class="prestataire-link"><i class="bi bi-grid-3x3-gap-fill"></i> Prestataire</a>
        <h1><i class="bi bi-person-circle"></i> <?= $showLogin ? 'Connexion espace client' : 'Mon espace client' ?></h1>
        <p class="mb-0"><?= ab_escape($businessName) ?></p>
    </div>

    <div class="account-container">

<?php if ($showLogin): ?>
        <!-- Login form -->
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card p-4 mt-4">
                    <h5 class="mb-3"><i class="bi bi-lock"></i> Accéder à mon compte</h5>
                    <?php if ($loginError): ?>
                    <div class="alert alert-danger"><?= ab_escape($loginError) ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="login">
                        <div class="mb-3">
                            <label class="form-label">Adresse email *</label>
                            <input type="email" name="email" class="form-control" required
                                   value="<?= ab_escape($_POST['email'] ?? '') ?>" autocomplete="email">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date de naissance *</label>
                            <input type="text" name="date_of_birth" class="form-control" required
                                   placeholder="JJ.MM.AAAA" maxlength="10" id="login-dob" autocomplete="bday">
                        </div>
                        <button type="submit" class="btn btn-ab w-100"><i class="bi bi-box-arrow-in-right"></i> Se connecter</button>
                    </form>
                    <p class="text-muted small mt-3 mb-0">
                        <i class="bi bi-info-circle"></i> Utilisez l'email et la date de naissance que vous avez fournis lors de votre réservation.
                    </p>
                </div>
            </div>
        </div>

<?php else: ?>

<?php if ($impersonating): ?>
        <!-- Admin impersonation banner -->
        <div class="impersonation-banner">
            <span style="font-size:1.3rem;">👁</span>
            <div>
                <strong>Vue admin</strong> – <?= ab_escape($customer['first_name'] . ' ' . $customer['last_name']) ?>
                &nbsp;
                <a href="<?= ab_url('admin/index.php?page=customers&action=view&id=' . $customer['id']) ?>" class="btn btn-sm btn-outline-warning ms-2">
                    <i class="bi bi-arrow-left"></i> Retour fiche admin
                </a>
            </div>
        </div>
<?php endif; ?>

<?php
// ---- Account data ----
$appointments = $db->fetchAll(
    "SELECT a.*, s.name as service_name, s.price as service_price, s.color as service_color,
            u.first_name as provider_first_name, u.last_name as provider_last_name
     FROM ab_appointments a
     JOIN ab_services s ON a.service_id = s.id
     JOIN ab_users u ON a.provider_id = u.id
     WHERE a.customer_id = ?
     ORDER BY a.start_datetime DESC",
    [$customer['id']]
);

// Stats
$doneCount = 0;
$totalSpent = 0.0;
$nonCancelledDates = [];
foreach ($appointments as $a) {
    if ($a['status'] !== 'cancelled') {
        $doneCount++;
        $totalSpent += (float)$a['service_price'];
        $nonCancelledDates[] = strtotime($a['start_datetime']);
    }
}

// Average frequency
$avgFreq = null;
if (count($nonCancelledDates) >= 2) {
    sort($nonCancelledDates);
    $gaps = [];
    for ($i = 1; $i < count($nonCancelledDates); $i++) {
        $gaps[] = ($nonCancelledDates[$i] - $nonCancelledDates[$i - 1]) / 86400;
    }
    $avgFreq = array_sum($gaps) / count($gaps);
}

$memberSince = !empty($customer['created_at']) ? ab_format_date($customer['created_at']) : '-';
?>

        <!-- Header card: customer info + logout -->
        <div class="card p-4 mb-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <h4 class="mb-1"><i class="bi bi-person-circle me-2" style="color:var(--ab-primary)"></i><?= ab_escape($customer['first_name'] . ' ' . $customer['last_name']) ?></h4>
                    <p class="text-muted mb-1"><i class="bi bi-envelope me-1"></i><?= ab_escape($customer['email']) ?></p>
                    <?php if (!empty($customer['date_of_birth'])): ?>
                    <p class="text-muted mb-1"><i class="bi bi-cake me-1"></i>Né(e) le <?= ab_escape(ab_format_dob($customer['date_of_birth'])) ?></p>
                    <?php endif; ?>
                    <p class="text-muted mb-0 small"><i class="bi bi-calendar-check me-1"></i>Client depuis le <?= ab_escape($memberSince) ?></p>
                </div>
                <?php if (!$impersonating): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-box-arrow-right"></i> Se déconnecter
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card">
                    <div class="stat-value"><?= $doneCount ?></div>
                    <div class="stat-label">Rendez-vous effectués</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card">
                    <div class="stat-value"><?= ab_escape(ab_format_price($totalSpent)) ?></div>
                    <div class="stat-label">Total dépensé</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card">
                    <div class="stat-value"><?= $avgFreq !== null ? round($avgFreq) : '-' ?></div>
                    <div class="stat-label">
                        <?php if ($avgFreq !== null): ?>
                            tous les <?= round($avgFreq) ?> jours en moyenne
                        <?php else: ?>
                            Fréquence moyenne
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Appointment history -->
        <div class="card">
            <div class="card-header bg-white d-flex align-items-center justify-content-between">
                <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Historique des rendez-vous</h5>
                <span class="badge bg-secondary"><?= count($appointments) ?></span>
            </div>
            <?php if (empty($appointments)): ?>
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-calendar-x" style="font-size:2rem;"></i>
                <p class="mt-2 mb-0">Aucun rendez-vous enregistré.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Heure</th>
                            <th>Prestation</th>
                            <th>Prestataire</th>
                            <th>Statut</th>
                            <th class="text-end">Prix</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($appointments as $a): ?>
                    <tr>
                        <td><?= ab_escape(ab_format_date($a['start_datetime'])) ?></td>
                        <td><?= ab_escape(ab_format_time($a['start_datetime'])) ?></td>
                        <td>
                            <span class="badge" style="background:<?= ab_escape($a['service_color'] ?? '#999') ?>">
                                <?= ab_escape($a['service_name']) ?>
                            </span>
                        </td>
                        <td><?= ab_escape($a['provider_first_name'] . ' ' . $a['provider_last_name']) ?></td>
                        <td>
                            <span class="badge badge-<?= ab_escape($a['status']) ?>">
                                <?php
                                $statusLabels = [
                                    'pending'   => 'En attente',
                                    'confirmed' => 'Confirmé',
                                    'cancelled' => 'Annulé',
                                    'completed' => 'Effectué',
                                ];
                                echo ab_escape($statusLabels[$a['status']] ?? $a['status']);
                                ?>
                            </span>
                        </td>
                        <td class="text-end"><?= ab_escape(ab_format_price((float)$a['service_price'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

<?php endif; // end showLogin / account ?>

    </div><!-- /.account-container -->

    <footer class="text-center py-3 text-muted small">
        <?= ab_escape($businessName) ?> &middot; <a href="<?= ab_url('public/') ?>" class="text-muted">Prendre un rendez-vous</a>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // DOB auto-format on login form
    const loginDob = document.getElementById('login-dob');
    if (loginDob) {
        loginDob.addEventListener('input', function() {
            let v = this.value.replace(/\D/g, '');
            if (v.length > 2) v = v.substring(0,2) + '.' + v.substring(2);
            if (v.length > 5) v = v.substring(0,5) + '.' + v.substring(5);
            this.value = v.substring(0, 10);
        });
    }
    </script>
</body>
</html>
