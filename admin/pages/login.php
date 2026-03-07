<?php
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    Auth::logout();
    ab_redirect(ab_url('admin/index.php?page=login'));
}

if (Auth::check()) {
    ab_redirect(ab_url('admin/index.php?page=dashboard'));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $user = Auth::login($email, $password);
    if ($user) {
        ab_redirect(ab_url('admin/index.php?page=dashboard'));
    } else {
        $error = 'Email ou mot de passe incorrect.';
    }
}

$primaryColor = '#e91e63';
try { $primaryColor = ab_setting('primary_color', '#e91e63'); } catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - ABAppointments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, <?= $primaryColor ?> 100%); min-height: 100vh; display: flex; align-items: center; }
        .login-card { max-width: 400px; margin: auto; border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
        .login-header { background: <?= $primaryColor ?>; color: #fff; padding: 30px; text-align: center; border-radius: 15px 15px 0 0; }
        .btn-login { background: <?= $primaryColor ?>; border: none; }
        .btn-login:hover { background: color-mix(in srgb, <?= $primaryColor ?> 80%, black); }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-card card">
            <div class="login-header">
                <i class="bi bi-calendar-heart" style="font-size: 2.5rem;"></i>
                <h4 class="mt-2 mb-0">ABAppointments</h4>
                <small>Système de rendez-vous</small>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                <div class="alert alert-danger"><?= ab_escape($error) ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" name="email" class="form-control" required value="<?= ab_escape($_POST['email'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mot de passe</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-login text-white w-100">
                        <i class="bi bi-box-arrow-in-right"></i> Se connecter
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
