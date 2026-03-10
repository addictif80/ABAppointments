<?php
$siteName = wp_setting('site_name', 'WebPanel');
if (Auth::check() && Auth::isAdmin()) wp_redirect(wp_url('admin/?page=dashboard'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = Auth::login(trim($_POST['email'] ?? ''), $_POST['password'] ?? '');
    if ($user && $user['role'] === 'admin') {
        wp_redirect(wp_url('admin/?page=dashboard'));
    } else {
        if ($user) Auth::logout();
        $error = 'Identifiants incorrects ou acces non autorise.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - <?= wp_escape($siteName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #1e1b4b; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { background: #fff; border-radius: 16px; padding: 40px; width: 100%; max-width: 400px; }
    </style>
</head>
<body>
<div class="login-card">
    <div class="text-center mb-4"><h4><i class="bi bi-shield-lock"></i> Administration</h4></div>
    <?php if (!empty($error)): ?><div class="alert alert-danger"><?= wp_escape($error) ?></div><?php endif; ?>
    <form method="POST">
        <div class="mb-3"><input type="email" name="email" class="form-control" placeholder="Email" required autofocus></div>
        <div class="mb-3"><input type="password" name="password" class="form-control" placeholder="Mot de passe" required></div>
        <button type="submit" class="btn btn-dark w-100">Connexion</button>
    </form>
</div>
</body>
</html>
