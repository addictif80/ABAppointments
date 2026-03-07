<?php
$db = Database::getInstance();
$user = $db->fetchOne("SELECT * FROM ab_users WHERE id = ?", [Auth::userId()]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'first_name' => trim($_POST['first_name']),
        'last_name' => trim($_POST['last_name']),
        'email' => trim($_POST['email']),
        'phone' => trim($_POST['phone'] ?? ''),
    ];

    if (!empty($_POST['new_password'])) {
        if (!password_verify($_POST['current_password'], $user['password'])) {
            ab_flash('error', 'Mot de passe actuel incorrect.');
            ab_redirect(ab_url('admin/index.php?page=profile'));
        }
        $data['password'] = Auth::hashPassword($_POST['new_password']);
    }

    $db->update('ab_users', $data, 'id = ?', [Auth::userId()]);
    $_SESSION['user_name'] = $data['first_name'] . ' ' . $data['last_name'];
    ab_flash('success', 'Profil mis à jour.');
    ab_redirect(ab_url('admin/index.php?page=profile'));
}
?>

<h4 class="mb-3"><i class="bi bi-person"></i> Mon profil</h4>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <?= Auth::csrfField() ?>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Prénom</label><input type="text" name="first_name" class="form-control" required value="<?= ab_escape($user['first_name']) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Nom</label><input type="text" name="last_name" class="form-control" required value="<?= ab_escape($user['last_name']) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required value="<?= ab_escape($user['email']) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Téléphone</label><input type="tel" name="phone" class="form-control" value="<?= ab_escape($user['phone'] ?? '') ?>"></div>
                        <div class="col-12"><hr><h6>Changer le mot de passe</h6></div>
                        <div class="col-md-6"><label class="form-label">Mot de passe actuel</label><input type="password" name="current_password" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Nouveau mot de passe</label><input type="password" name="new_password" class="form-control" minlength="6"></div>
                        <div class="col-12"><button type="submit" class="btn btn-primary"><i class="bi bi-check"></i> Enregistrer</button></div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
