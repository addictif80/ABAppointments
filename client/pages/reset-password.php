<?php
$siteName = wp_setting('site_name', 'WebPanel');
$primaryColor = wp_setting('primary_color', '#4F46E5');
$token = $_GET['token'] ?? '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Le mot de passe doit contenir au moins 8 caracteres.';
    } elseif ($password !== $confirm) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        if (Auth::resetPassword($token, $password)) {
            $success = true;
        } else {
            $error = 'Lien invalide ou expire.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reinitialiser le mot de passe - <?= wp_escape($siteName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, <?= wp_escape($primaryColor) ?>, #1e1b4b); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { background: #fff; border-radius: 16px; padding: 40px; width: 100%; max-width: 420px; box-shadow: 0 20px 60px rgba(0,0,0,.3); }
    </style>
</head>
<body>
<div class="login-card">
    <h4 class="mb-3">Nouveau mot de passe</h4>
    <?php if ($success): ?>
        <div class="alert alert-success">Mot de passe reinitialise avec succes !</div>
        <a href="<?= wp_url('client/?page=login') ?>" class="btn btn-primary w-100" style="background: <?= wp_escape($primaryColor) ?>; border-color: <?= wp_escape($primaryColor) ?>">Se connecter</a>
    <?php else: ?>
        <?php if (!empty($error)): ?><div class="alert alert-danger"><?= wp_escape($error) ?></div><?php endif; ?>
        <form method="POST">
            <input type="hidden" name="token" value="<?= wp_escape($token) ?>">
            <div class="mb-3">
                <label class="form-label">Nouveau mot de passe</label>
                <input type="password" name="password" class="form-control" required minlength="8">
            </div>
            <div class="mb-3">
                <label class="form-label">Confirmer</label>
                <input type="password" name="password_confirm" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100" style="background: <?= wp_escape($primaryColor) ?>; border-color: <?= wp_escape($primaryColor) ?>">Reinitialiser</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
