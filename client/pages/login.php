<?php
$siteName = wp_setting('site_name', 'WebPanel');
$primaryColor = wp_setting('primary_color', '#4F46E5');

if (Auth::check()) {
    wp_redirect(wp_url('client/?page=dashboard'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        $user = Auth::login($email, $password);
        if ($user) {
            if ($user['role'] === 'admin') {
                wp_redirect(wp_url('admin/?page=dashboard'));
            }
            wp_redirect(wp_url('client/?page=dashboard'));
        } else {
            $error = 'Email ou mot de passe incorrect.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - <?= wp_escape($siteName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, <?= wp_escape($primaryColor) ?>, #1e1b4b); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { background: #fff; border-radius: 16px; padding: 40px; width: 100%; max-width: 420px; box-shadow: 0 20px 60px rgba(0,0,0,.3); }
    </style>
</head>
<body>
<div class="login-card">
    <div class="text-center mb-4">
        <h3><i class="bi bi-hdd-rack" style="color: <?= wp_escape($primaryColor) ?>"></i> <?= wp_escape($siteName) ?></h3>
        <p class="text-muted">Connexion a votre espace client</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= wp_escape($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required value="<?= wp_escape($_POST['email'] ?? '') ?>" autofocus>
        </div>
        <div class="mb-3">
            <label class="form-label">Mot de passe</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="<?= wp_url('client/?page=forgot-password') ?>" class="small">Mot de passe oublie ?</a>
        </div>
        <button type="submit" class="btn btn-primary w-100" style="background: <?= wp_escape($primaryColor) ?>; border-color: <?= wp_escape($primaryColor) ?>">Se connecter</button>
    </form>

    <div class="text-center mt-3">
        <span class="text-muted small">Pas encore de compte ?</span>
        <a href="<?= wp_url('client/?page=register') ?>" class="small">Creer un compte</a>
    </div>
</div>
</body>
</html>
