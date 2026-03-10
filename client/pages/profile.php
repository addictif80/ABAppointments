<?php
$pageTitle = 'Mon Profil';
$db = Database::getInstance();
$user = Auth::user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'profile';

    if ($action === 'profile') {
        $db->update('wp_users', [
            'first_name' => trim($_POST['first_name'] ?? $user['first_name']),
            'last_name' => trim($_POST['last_name'] ?? $user['last_name']),
            'company' => trim($_POST['company'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'city' => trim($_POST['city'] ?? ''),
            'postal_code' => trim($_POST['postal_code'] ?? ''),
            'country' => trim($_POST['country'] ?? 'France')
        ], 'id = ?', [$user['id']]);
        $_SESSION['user_name'] = trim($_POST['first_name']) . ' ' . trim($_POST['last_name']);
        wp_flash('success', 'Profil mis a jour.');
    } elseif ($action === 'password') {
        if (!password_verify($_POST['current_password'] ?? '', $user['password'])) {
            wp_flash('error', 'Mot de passe actuel incorrect.');
        } elseif (strlen($_POST['new_password'] ?? '') < 8) {
            wp_flash('error', 'Le nouveau mot de passe doit contenir au moins 8 caracteres.');
        } elseif ($_POST['new_password'] !== $_POST['confirm_password']) {
            wp_flash('error', 'Les mots de passe ne correspondent pas.');
        } else {
            $db->update('wp_users', [
                'password' => password_hash($_POST['new_password'], PASSWORD_BCRYPT, ['cost' => 12])
            ], 'id = ?', [$user['id']]);
            wp_flash('success', 'Mot de passe modifie.');
        }
    }
    wp_redirect(wp_url('client/?page=profile'));
}
?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0">Informations personnelles</h6></div>
            <div class="card-body">
                <form method="POST">
                    <?= wp_csrf_field() ?>
                    <input type="hidden" name="action" value="profile">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Prenom</label>
                            <input type="text" name="first_name" class="form-control" value="<?= wp_escape($user['first_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nom</label>
                            <input type="text" name="last_name" class="form-control" value="<?= wp_escape($user['last_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" value="<?= wp_escape($user['email']) ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Societe</label>
                            <input type="text" name="company" class="form-control" value="<?= wp_escape($user['company']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Telephone</label>
                            <input type="tel" name="phone" class="form-control" value="<?= wp_escape($user['phone']) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Adresse</label>
                            <textarea name="address" class="form-control" rows="2"><?= wp_escape($user['address']) ?></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ville</label>
                            <input type="text" name="city" class="form-control" value="<?= wp_escape($user['city']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Code postal</label>
                            <input type="text" name="postal_code" class="form-control" value="<?= wp_escape($user['postal_code']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Pays</label>
                            <input type="text" name="country" class="form-control" value="<?= wp_escape($user['country'] ?: 'France') ?>">
                        </div>
                    </div>
                    <div class="mt-3"><button type="submit" class="btn btn-primary">Enregistrer</button></div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h6 class="mb-0">Changer le mot de passe</h6></div>
            <div class="card-body">
                <form method="POST">
                    <?= wp_csrf_field() ?>
                    <input type="hidden" name="action" value="password">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Mot de passe actuel</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nouveau mot de passe</label>
                            <input type="password" name="new_password" class="form-control" required minlength="8">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirmer</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>
                    <div class="mt-3"><button type="submit" class="btn btn-warning">Changer le mot de passe</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3" style="width:80px;height:80px">
                    <span class="fs-2 fw-bold text-primary"><?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?></span>
                </div>
                <h5><?= wp_escape($user['first_name'] . ' ' . $user['last_name']) ?></h5>
                <p class="text-muted"><?= wp_escape($user['email']) ?></p>
                <small class="text-muted">Membre depuis <?= wp_format_date($user['created_at'], 'F Y') ?></small>
            </div>
        </div>
    </div>
</div>
