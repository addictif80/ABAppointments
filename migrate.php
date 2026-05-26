#!/usr/bin/env php
<?php
/**
 * ABAppointments – Migration runner
 * Usage (SSH) : php migrate.php [--dry-run]
 */

// ── Bootstrap ─────────────────────────────────────────────────────────────────
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Ce script ne peut être exécuté qu'en ligne de commande.\n");
}

$root = __DIR__;
require_once $root . '/config/config.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);

// ── Couleurs ANSI ─────────────────────────────────────────────────────────────
function c(string $text, string $color): string {
    $codes = ['green' => '32', 'yellow' => '33', 'red' => '31', 'cyan' => '36', 'bold' => '1'];
    return "\033[" . ($codes[$color] ?? '0') . "m{$text}\033[0m";
}

// ── Connexion PDO ─────────────────────────────────────────────────────────────
try {
    $dsn = 'mysql:host=' . AB_DB_HOST . ';dbname=' . AB_DB_NAME . ';charset=' . AB_DB_CHARSET;
    $pdo = new PDO($dsn, AB_DB_USER, AB_DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    echo c('✖  Connexion impossible : ' . $e->getMessage(), 'red') . "\n";
    exit(1);
}

// ── Table de suivi des migrations ─────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `ab_migrations` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `filename`   VARCHAR(255) NOT NULL,
    `applied_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `filename` (`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$applied = $pdo->query("SELECT filename FROM ab_migrations")->fetchAll(PDO::FETCH_COLUMN);
$applied = array_flip($applied); // pour isset() rapide

// ── Découverte des fichiers SQL ───────────────────────────────────────────────
$files = glob($root . '/install/migrate_*.sql');
natsort($files); // ordre naturel : 003, 004, 005, 006 …

if (empty($files)) {
    echo c('  Aucun fichier de migration trouvé dans install/.', 'yellow') . "\n";
    exit(0);
}

echo "\n" . c('ABAppointments – Migration runner', 'bold') . ($dryRun ? c(' [DRY-RUN]', 'yellow') : '') . "\n";
echo str_repeat('─', 55) . "\n";

$pending = 0;
$done    = 0;
$errors  = 0;

foreach ($files as $file) {
    $name = basename($file);

    if (isset($applied[$name])) {
        echo c('  ✔  ', 'green') . $name . c('  (déjà appliquée)', 'cyan') . "\n";
        continue;
    }

    $pending++;
    echo c('  ➜  ', 'yellow') . $name . ' … ';

    if ($dryRun) {
        echo c('[dry-run, ignorée]', 'yellow') . "\n";
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false || trim($sql) === '') {
        echo c('VIDE, ignorée', 'yellow') . "\n";
        continue;
    }

    try {
        // Exécuter chaque instruction séparément
        $pdo->beginTransaction();
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            if ($stmt !== '') $pdo->exec($stmt);
        }
        $pdo->exec("INSERT INTO ab_migrations (filename) VALUES (" . $pdo->quote($name) . ")");
        $pdo->commit();
        echo c('OK', 'green') . "\n";
        $done++;
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo c('ERREUR', 'red') . "\n";
        echo c('     ' . $e->getMessage(), 'red') . "\n";
        $errors++;
    }
}

echo str_repeat('─', 55) . "\n";

if ($pending === 0) {
    echo c('  Rien à faire — toutes les migrations sont à jour.', 'green') . "\n\n";
} elseif ($dryRun) {
    echo c("  {$pending} migration(s) en attente (dry-run, rien n'a été exécuté).", 'yellow') . "\n\n";
} else {
    $summary = "  {$done} migration(s) appliquée(s)";
    if ($errors) $summary .= ", {$errors} erreur(s)";
    echo c($summary . '.', $errors ? 'red' : 'green') . "\n\n";
}

exit($errors ? 1 : 0);
