<?php
$siteName = wp_setting('site_name', 'WebPanel');
$primaryColor = wp_setting('primary_color', '#4F46E5');
$sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if ($email) {
        $result = Auth::requestPasswordReset($email);
        if (is_array($result) && isset($result['token'])) {
            $mailer = new Mailer();
            $mailer->sendTemplate($email, 'password_reset', [
                'first_name' => $result['user']['first_name'],
                'reset_url' => wp_url('client/?page=reset-password&token=' . $result['token'])
            ]);
        }
        $sent = true;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublie - <?= wp_escape($siteName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, <?= wp_escape($primaryColor) ?>, #1e1b4b); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { background: #fff; border-radius: 16px; padding: 40px; width: 100%; max-width: 420px; box-shadow: 0 20px 60px rgba(0,0,0,.3); }
    </style>
</head>
<body>
<div class="login-card">
    <h4 class="mb-3">Mot de passe oublie</h4>
    <?php if ($sent): ?>
        <div class="alert alert-success">Si un compte existe avec cet email, un lien de reinitialisation a ete envoye.</div>
        <a href="<?= wp_url('client/?page=login') ?>" class="btn btn-outline-primary w-100">Retour a la connexion</a>
    <?php else: ?>
        <p class="text-muted">Entrez votre email pour recevoir un lien de reinitialisation.</p>
        <form method="POST">
            <div class="mb-3">
                <input type="email" name="email" class="form-control" placeholder="Votre email" required autofocus>
            </div>
            <button type="submit" class="btn btn-primary w-100" style="background: <?= wp_escape($primaryColor) ?>; border-color: <?= wp_escape($primaryColor) ?>">Envoyer</button>
        </form>
        <div class="text-center mt-3"><a href="<?= wp_url('client/?page=login') ?>" class="small">Retour</a></div>
    <?php endif; ?>
</div>
</body>
</html>
