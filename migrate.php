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

$dryRun    = in_array('--dry-run',    $argv ?? [], true);
$markForce = in_array('--mark-applied', $argv ?? [], true);

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

// Codes SQLSTATE correspondant à « l'objet existe déjà »
// → la migration est déjà passée manuellement, on la marque comme faite.
const ALREADY_EXISTS_STATES = [
    '42S01', // Table already exists
    '42S11', // Index already exists
    '42S21', // Column already exists
];

function isAlreadyExists(PDOException $e): bool {
    $state = $e->getCode();
    if (in_array($state, ALREADY_EXISTS_STATES, true)) return true;
    // MySQL errno 1060 (dup col), 1061 (dup key), 1050 (table exists)
    $msg = $e->getMessage();
    return (bool) preg_match('/Duplicate (column|key|index)|already exists/i', $msg);
}

echo "\n" . c('ABAppointments – Migration runner', 'bold') . ($dryRun ? c(' [DRY-RUN]', 'yellow') : '') . ($markForce ? c(' [MARK-APPLIED]', 'yellow') : '') . "\n";
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

    // --mark-applied : enregistrer sans exécuter (DB déjà à jour)
    if ($markForce || $dryRun) {
        if (!$dryRun) {
            $pdo->exec("INSERT IGNORE INTO ab_migrations (filename) VALUES (" . $pdo->quote($name) . ")");
            echo c('marquée comme appliquée', 'yellow') . "\n";
            $done++;
        } else {
            echo c('[dry-run, ignorée]', 'yellow') . "\n";
        }
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false || trim($sql) === '') {
        echo c('VIDE, ignorée', 'yellow') . "\n";
        continue;
    }

    try {
        $pdo->beginTransaction();
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            if ($stmt !== '') $pdo->exec($stmt);
        }
        $pdo->exec("INSERT INTO ab_migrations (filename) VALUES (" . $pdo->quote($name) . ")");
        $pdo->commit();
        echo c('OK', 'green') . "\n";
        $done++;
    } catch (PDOException $e) {
        // Annuler la transaction uniquement si elle est toujours active
        if ($pdo->inTransaction()) $pdo->rollBack();

        if (isAlreadyExists($e)) {
            // Schéma déjà dans l'état souhaité → marquer comme appliquée
            $pdo->exec("INSERT IGNORE INTO ab_migrations (filename) VALUES (" . $pdo->quote($name) . ")");
            echo c('déjà appliquée (schéma existant)', 'yellow') . "\n";
            $done++;
        } else {
            echo c('ERREUR', 'red') . "\n";
            echo c('     ' . $e->getMessage(), 'red') . "\n";
            $errors++;
        }
    }
}

echo str_repeat('─', 55) . "\n";

if ($pending === 0) {
    echo c('  Rien à faire — toutes les migrations sont à jour.', 'green') . "\n\n";
} elseif ($dryRun) {
    echo c("  {$pending} migration(s) en attente (dry-run, rien n'a été exécuté).", 'yellow') . "\n\n";
} else {
    $summary = "  {$done} migration(s) traitée(s)";
    if ($errors) $summary .= ", {$errors} erreur(s)";
    echo c($summary . '.', $errors ? 'red' : 'green') . "\n\n";
    if ($errors === 0 && $pending > 0) {
        echo c('  Conseil : relancez php migrate.php pour confirmer.', 'cyan') . "\n\n";
    }
}

exit($errors ? 1 : 0);
