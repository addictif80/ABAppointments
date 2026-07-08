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

// Splits a .sql file into individual statements on top-level ';' only —
// unlike a naive explode(';', ...), this ignores semicolons that appear
// inside quoted strings (e.g. inline CSS in HTML email templates) or
// backtick identifiers, and skips '--' line comments.
function splitSqlStatements(string $sql): array {
    $statements = [];
    $current = '';
    $len = strlen($sql);
    $inString = false;
    $inBacktick = false;

    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];

        if ($inString) {
            $current .= $ch;
            if ($ch === '\\') {
                if ($i + 1 < $len) { $current .= $sql[++$i]; }
                continue;
            }
            if ($ch === "'") {
                if ($i + 1 < $len && $sql[$i + 1] === "'") {
                    $current .= $sql[++$i]; // escaped '' quote, string continues
                } else {
                    $inString = false;
                }
            }
            continue;
        }

        if ($inBacktick) {
            $current .= $ch;
            if ($ch === '`') { $inBacktick = false; }
            continue;
        }

        if ($ch === '-' && $i + 1 < $len && $sql[$i + 1] === '-') {
            $nl = strpos($sql, "\n", $i);
            if ($nl === false) { break; }
            $current .= substr($sql, $i, $nl - $i + 1);
            $i = $nl;
            continue;
        }

        if ($ch === "'") { $inString = true; $current .= $ch; continue; }
        if ($ch === '`') { $inBacktick = true; $current .= $ch; continue; }

        if ($ch === ';') {
            $statements[] = $current;
            $current = '';
            continue;
        }

        $current .= $ch;
    }
    if (trim($current) !== '') { $statements[] = $current; }

    return array_values(array_filter(array_map('trim', $statements), fn($s) => $s !== ''));
}

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

    // Pas de transaction : les DDL (ALTER/CREATE TABLE) font un commit
    // implicite dans MySQL et ne peuvent pas être rollbackés de toute façon.
    try {
        foreach (splitSqlStatements($sql) as $stmt) {
            $pdo->exec($stmt);
        }
        $pdo->exec("INSERT IGNORE INTO ab_migrations (filename) VALUES (" . $pdo->quote($name) . ")");
        echo c('OK', 'green') . "\n";
        $done++;
    } catch (PDOException $e) {
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
