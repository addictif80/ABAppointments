<?php
/**
 * ABAppointments - Installer
 */
session_start();
$step = (int)($_GET['step'] ?? 1);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        // Check PHP requirements
        $checks = [
            'PHP >= 8.0' => version_compare(PHP_VERSION, '8.0.0', '>='),
            'PDO MySQL' => extension_loaded('pdo_mysql'),
            'JSON' => extension_loaded('json'),
            'mbstring' => extension_loaded('mbstring'),
            'openssl' => extension_loaded('openssl'),
        ];
        $allGood = !in_array(false, $checks);
        if ($allGood) {
            header('Location: ?step=2');
            exit;
        } else {
            $error = 'Certaines extensions PHP requises sont manquantes.';
        }
    }

    if ($step === 2) {
        $dbHost = trim($_POST['db_host']);
        $dbName = trim($_POST['db_name']);
        $dbUser = trim($_POST['db_user']);
        $dbPass = $_POST['db_pass'];
        $baseUrl = rtrim(trim($_POST['base_url']), '/');

        try {
            $pdo = new PDO("mysql:host=$dbHost;charset=utf8mb4", $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbName`");

            // Execute schema
            $schema = file_get_contents(__DIR__ . '/schema.sql');
            // Remove the placeholder admin insert, we'll do it properly
            $schema = preg_replace("/INSERT INTO `ab_users`.*?;/s", '', $schema);
            $pdo->exec($schema);

            $_SESSION['install'] = [
                'db_host' => $dbHost,
                'db_name' => $dbName,
                'db_user' => $dbUser,
                'db_pass' => $dbPass,
                'base_url' => $baseUrl,
            ];

            header('Location: ?step=3');
            exit;
        } catch (PDOException $e) {
            $error = 'Erreur de connexion à la base de données : ' . $e->getMessage();
        }
    }

    if ($step === 3) {
        $install = $_SESSION['install'] ?? null;
        if (!$install) { header('Location: ?step=2'); exit; }

        $businessName = trim($_POST['business_name']);
        $adminEmail = trim($_POST['admin_email']);
        $adminPassword = $_POST['admin_password'];
        $adminFirstName = trim($_POST['admin_first_name']);
        $adminLastName = trim($_POST['admin_last_name']);

        if (strlen($adminPassword) < 6) {
            $error = 'Le mot de passe doit faire au moins 6 caractères.';
        } else {
            try {
                $pdo = new PDO("mysql:host={$install['db_host']};dbname={$install['db_name']};charset=utf8mb4", $install['db_user'], $install['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

                // Create admin user
                $hashedPassword = password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $pdo->prepare("INSERT INTO ab_users (first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, 'admin')");
                $stmt->execute([$adminFirstName, $adminLastName, $adminEmail, $hashedPassword]);

                // Update settings
                $pdo->prepare("UPDATE ab_settings SET setting_value = ? WHERE setting_key = 'business_name'")->execute([$businessName]);
                $pdo->prepare("UPDATE ab_settings SET setting_value = ? WHERE setting_key = 'business_email'")->execute([$adminEmail]);

                // Generate config file
                $secretKey = bin2hex(random_bytes(32));
                $configContent = "<?php\n"
                    . "define('AB_DEBUG', false);\n"
                    . "define('AB_BASE_URL', " . var_export($install['base_url'], true) . ");\n"
                    . "define('AB_DB_HOST', " . var_export($install['db_host'], true) . ");\n"
                    . "define('AB_DB_NAME', " . var_export($install['db_name'], true) . ");\n"
                    . "define('AB_DB_USER', " . var_export($install['db_user'], true) . ");\n"
                    . "define('AB_DB_PASS', " . var_export($install['db_pass'], true) . ");\n"
                    . "define('AB_DB_PREFIX', 'ab_');\n"
                    . "define('AB_DB_CHARSET', 'utf8mb4');\n"
                    . "define('AB_SECRET_KEY', '$secretKey');\n"
                    . "define('AB_TIMEZONE', 'Europe/Paris');\n"
                    . "define('AB_SESSION_NAME', 'ab_session');\n"
                    . "define('AB_SESSION_LIFETIME', 7200);\n"
                    . "define('AB_UPLOAD_DIR', __DIR__ . '/../assets/uploads/');\n"
                    . "define('AB_MAX_UPLOAD_SIZE', 5 * 1024 * 1024);\n";

                $configPath = __DIR__ . '/../config/config.php';
                if (is_writable(dirname($configPath))) {
                    file_put_contents($configPath, $configContent);
                    $configWritten = true;
                } else {
                    $configWritten = false;
                    $_SESSION['install']['config_content'] = $configContent;
                }

                $_SESSION['install']['done'] = true;
                $_SESSION['install']['config_written'] = $configWritten;

                header('Location: ?step=4');
                exit;
            } catch (PDOException $e) {
                $error = 'Erreur : ' . $e->getMessage();
            }
        }
    }
}

// Check requirements for step 1
$checks = [
    'PHP >= 8.0' => version_compare(PHP_VERSION, '8.0.0', '>='),
    'PDO MySQL' => extension_loaded('pdo_mysql'),
    'JSON' => extension_loaded('json'),
    'mbstring' => extension_loaded('mbstring'),
    'openssl' => extension_loaded('openssl'),
    'curl' => extension_loaded('curl'),
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - ABAppointments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #1a1a2e 0%, #e91e63 100%); min-height: 100vh; display: flex; align-items: center; }
        .install-card { max-width: 600px; margin: auto; border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
        .install-header { background: #e91e63; color: #fff; padding: 25px; text-align: center; border-radius: 15px 15px 0 0; }
        .step-bar { display: flex; gap: 5px; justify-content: center; margin-top: 10px; }
        .step-bar .dot { width: 30px; height: 4px; background: rgba(255,255,255,0.3); border-radius: 2px; }
        .step-bar .dot.active { background: #fff; }
        .btn-install { background: #e91e63; border: none; }
        .btn-install:hover { background: #c2185b; }
    </style>
</head>
<body>
<div class="container">
    <div class="install-card card">
        <div class="install-header">
            <h3><i class="bi bi-calendar-heart"></i> ABAppointments</h3>
            <small>Assistant d'installation</small>
            <div class="step-bar">
                <div class="dot <?= $step >= 1 ? 'active' : '' ?>"></div>
                <div class="dot <?= $step >= 2 ? 'active' : '' ?>"></div>
                <div class="dot <?= $step >= 3 ? 'active' : '' ?>"></div>
                <div class="dot <?= $step >= 4 ? 'active' : '' ?>"></div>
            </div>
        </div>
        <div class="card-body p-4">
            <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
            <h5>Étape 1 : Vérification du système</h5>
            <table class="table">
                <?php foreach ($checks as $name => $ok): ?>
                <tr>
                    <td><?= $name ?></td>
                    <td class="text-end"><?= $ok ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle-fill text-danger"></i>' ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <form method="POST">
                <button type="submit" class="btn btn-install text-white w-100" <?= in_array(false, $checks) ? 'disabled' : '' ?>>
                    Continuer <i class="bi bi-arrow-right"></i>
                </button>
            </form>

            <?php elseif ($step === 2): ?>
            <h5>Étape 2 : Base de données</h5>
            <form method="POST">
                <div class="mb-3"><label class="form-label">Serveur MySQL</label><input type="text" name="db_host" class="form-control" value="localhost" required></div>
                <div class="mb-3"><label class="form-label">Nom de la base</label><input type="text" name="db_name" class="form-control" value="ab_appointments" required></div>
                <div class="mb-3"><label class="form-label">Utilisateur MySQL</label><input type="text" name="db_user" class="form-control" value="root" required></div>
                <div class="mb-3"><label class="form-label">Mot de passe MySQL</label><input type="password" name="db_pass" class="form-control"></div>
                <div class="mb-3"><label class="form-label">URL du site (sans / final)</label><input type="url" name="base_url" class="form-control" value="<?= 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname(dirname($_SERVER['SCRIPT_NAME'])) ?>" required></div>
                <button type="submit" class="btn btn-install text-white w-100">Installer la base <i class="bi bi-arrow-right"></i></button>
            </form>

            <?php elseif ($step === 3): ?>
            <h5>Étape 3 : Configuration</h5>
            <form method="POST">
                <div class="mb-3"><label class="form-label">Nom de votre entreprise</label><input type="text" name="business_name" class="form-control" value="Mon Salon d'Ongles" required></div>
                <hr>
                <h6>Compte administrateur</h6>
                <div class="mb-3"><label class="form-label">Prénom</label><input type="text" name="admin_first_name" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Nom</label><input type="text" name="admin_last_name" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Email</label><input type="email" name="admin_email" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Mot de passe (min. 6 caractères)</label><input type="password" name="admin_password" class="form-control" required minlength="6"></div>
                <button type="submit" class="btn btn-install text-white w-100">Finaliser <i class="bi bi-arrow-right"></i></button>
            </form>

            <?php elseif ($step === 4): ?>
            <div class="text-center">
                <div style="font-size: 3rem; color: #28a745;"><i class="bi bi-check-circle-fill"></i></div>
                <h4 class="mt-2">Installation terminée !</h4>
                <p class="text-muted">ABAppointments est prêt à l'emploi.</p>

                <?php if (!($_SESSION['install']['config_written'] ?? true)): ?>
                <div class="alert alert-warning text-start">
                    <strong>Action requise :</strong> Le fichier de configuration n'a pas pu être écrit automatiquement.
                    Copiez le contenu suivant dans <code>config/config.php</code> :
                    <textarea class="form-control mt-2" rows="8" readonly><?= htmlspecialchars($_SESSION['install']['config_content'] ?? '') ?></textarea>
                </div>
                <?php endif; ?>

                <div class="d-grid gap-2 mt-3">
                    <a href="../admin/index.php?page=login" class="btn btn-install text-white"><i class="bi bi-box-arrow-in-right"></i> Accéder à l'administration</a>
                    <a href="../public/" class="btn btn-outline-secondary"><i class="bi bi-calendar"></i> Voir la page de réservation</a>
                </div>
                <p class="mt-3 text-muted small"><i class="bi bi-shield-exclamation"></i> Pensez à protéger le dossier <code>/install/</code> en production.</p>
            </div>
            <?php session_destroy(); ?>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
