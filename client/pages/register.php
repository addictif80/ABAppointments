<?php
$siteName = wp_setting('site_name', 'WebPanel');
$primaryColor = wp_setting('primary_color', '#4F46E5');

if (Auth::check()) wp_redirect(wp_url('client/?page=dashboard'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name' => trim($_POST['last_name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'company' => trim($_POST['company'] ?? ''),
        'phone' => trim($_POST['phone'] ?? '')
    ];

    $errors = [];
    if (empty($data['first_name'])) $errors[] = 'Prenom requis';
    if (empty($data['last_name'])) $errors[] = 'Nom requis';
    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide';
    if (strlen($data['password']) < 8) $errors[] = 'Le mot de passe doit contenir au moins 8 caracteres';
    if ($data['password'] !== ($_POST['password_confirm'] ?? '')) $errors[] = 'Les mots de passe ne correspondent pas';

    if (empty($errors)) {
        $result = Auth::register($data);
        if (isset($result['error'])) {
            $errors[] = $result['error'];
        } else {
            // Send welcome email
            $mailer = new Mailer();
            $mailer->sendTemplate($data['email'], 'welcome', [
                'first_name' => $data['first_name'],
                'login_url' => wp_url('client/?page=login')
            ]);
            Auth::login($data['email'], $data['password']);
            wp_redirect(wp_url('client/?page=dashboard'));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - <?= wp_escape($siteName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, <?= wp_escape($primaryColor) ?>, #1e1b4b); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .login-card { background: #fff; border-radius: 16px; padding: 40px; width: 100%; max-width: 500px; box-shadow: 0 20px 60px rgba(0,0,0,.3); }
    </style>
</head>
<body>
<div class="login-card">
    <div class="text-center mb-4">
        <h3><i class="bi bi-hdd-rack" style="color: <?= wp_escape($primaryColor) ?>"></i> <?= wp_escape($siteName) ?></h3>
        <p class="text-muted">Creer votre compte</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?php foreach ($errors as $e) echo wp_escape($e) . '<br>'; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Prenom *</label>
                <input type="text" name="first_name" class="form-control" required value="<?= wp_escape($data['first_name'] ?? '') ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Nom *</label>
                <input type="text" name="last_name" class="form-control" required value="<?= wp_escape($data['last_name'] ?? '') ?>">
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Email *</label>
            <input type="email" name="email" class="form-control" required value="<?= wp_escape($data['email'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Societe</label>
            <input type="text" name="company" class="form-control" value="<?= wp_escape($data['company'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Telephone</label>
            <input type="tel" name="phone" class="form-control" value="<?= wp_escape($data['phone'] ?? '') ?>">
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Mot de passe *</label>
                <input type="password" name="password" class="form-control" required minlength="8">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Confirmer *</label>
                <input type="password" name="password_confirm" class="form-control" required>
            </div>
        </div>
        <button type="submit" class="btn btn-primary w-100" style="background: <?= wp_escape($primaryColor) ?>; border-color: <?= wp_escape($primaryColor) ?>">Creer mon compte</button>
    </form>

    <div class="text-center mt-3">
        <a href="<?= wp_url('client/?page=login') ?>" class="small">Deja un compte ? Se connecter</a>
    </div>
</div>
</body>
</html>
