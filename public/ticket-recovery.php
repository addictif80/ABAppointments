<?php
/**
 * ABAppointments - "Lost my ticket link" recovery
 */
require_once __DIR__ . '/../core/App.php';

$sent = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Merci de saisir une adresse email valide.';
    } else {
        (new Tickets())->sendRecoveryLinks($email);
        $sent = true; // always show success, whether or not the email matched a customer
    }
}

$primaryColor = ab_setting('primary_color', '#e91e63');
$businessName = ab_setting('business_name', 'ABAppointments');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retrouver mes tickets - <?= ab_escape($businessName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .header-bar { background: <?= $primaryColor ?>; color: #fff; padding: 20px; text-align: center; }
        .recovery-card { max-width: 460px; margin: 40px auto; padding: 0 10px; }
    </style>
</head>
<body>
    <div class="header-bar">
        <h5 class="mb-0"><i class="bi bi-life-preserver"></i> <?= ab_escape($businessName) ?></h5>
    </div>
    <div class="container">
        <div class="recovery-card">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h5 class="mb-3"><i class="bi bi-envelope-paper"></i> Retrouver mes tickets</h5>
                    <?php if ($sent): ?>
                    <div class="alert alert-success">
                        Si un ticket existe pour cette adresse email, vous allez recevoir un message contenant tous les liens de vos tickets.
                    </div>
                    <?php else: ?>
                    <?php if ($error): ?>
                    <div class="alert alert-danger"><?= ab_escape($error) ?></div>
                    <?php endif; ?>
                    <p class="text-muted small">Saisissez l'adresse email utilisée lors de l'ouverture de votre ticket, nous vous renverrons les liens par email.</p>
                    <form method="POST">
                        <div class="mb-3">
                            <input type="email" name="email" class="form-control" placeholder="votre@email.com" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-send"></i> Recevoir mes liens</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
