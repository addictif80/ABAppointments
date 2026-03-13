<?php
/**
 * WebPanel - Admin Panel Router
 */
require_once __DIR__ . '/../core/App.php';

$page = $_GET['page'] ?? 'dashboard';

if ($page !== 'login') {
    Auth::requireAdmin();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $page !== 'login') {
    if (!wp_verify_csrf()) {
        wp_flash('error', 'Token de securite invalide.');
        wp_redirect($_SERVER['REQUEST_URI']);
    }
}

$allowedPages = [
    'login', 'dashboard', 'clients', 'client-detail',
    'products', 'subscriptions', 'subscription-detail',
    'invoices', 'invoice-detail', 'payments',
    'promo-codes', 'gift-cards',
    'tickets', 'ticket-detail',
    'monitoring', 'incidents',
    'ip-pool', 'os-templates', 'email-templates',
    'settings', 'activity-log', 'logout'
];

if ($page === 'logout') {
    Auth::logout();
    wp_redirect(wp_url('admin/?page=login'));
}

if (!in_array($page, $allowedPages)) $page = 'dashboard';

$pageFile = __DIR__ . "/pages/$page.php";
if (!file_exists($pageFile)) { $page = 'dashboard'; $pageFile = __DIR__ . '/pages/dashboard.php'; }

// Handle AJAX API test actions before any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $page === 'settings' && ($_POST['action'] ?? '') === 'test_cyberpanel') {
    header('Content-Type: application/json');

    $apiUrl = trim(wp_setting('cyberpanel_api_url') ?: wp_setting('cyberpanel_url'));
    $apiUrl = rtrim($apiUrl, '/');
    $adminUser = wp_setting('cyberpanel_admin_user', 'admin');
    $adminPass = wp_setting('cyberpanel_admin_pass');

    if (empty($apiUrl) || empty($adminPass)) {
        echo json_encode(['success' => false, 'message' => 'CyberPanel non configure (URL ou mot de passe manquant).']);
        exit;
    }

    $diag = [];
    $diag[] = "URL configuree: $apiUrl";
    $diag[] = "Utilisateur: $adminUser";
    $diag[] = "Mot de passe: " . str_repeat('*', max(0, strlen($adminPass) - 2)) . substr($adminPass, -2);

    // Test 1: raw TCP connection
    $parsed = parse_url($apiUrl);
    $host = $parsed['host'] ?? '';
    $port = $parsed['port'] ?? ($parsed['scheme'] === 'https' ? 443 : 80);
    $diag[] = "--- Test TCP vers $host:$port ---";
    $sock = @fsockopen($host, $port, $errno, $errstr, 5);
    if ($sock) {
        $diag[] = "TCP OK: connexion etablie";
        fclose($sock);
    } else {
        $diag[] = "TCP ECHEC: $errstr (code $errno)";
    }

    // Test 2: CURL to the verifyConn endpoint
    $url = "$apiUrl/api/verifyConn";
    $diag[] = "--- Test API: POST $url ---";
    $payload = json_encode(['adminUser' => $adminUser, 'adminPass' => $adminPass]);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $totalTime = round(curl_getinfo($ch, CURLINFO_TOTAL_TIME), 2);
    curl_close($ch);

    $diag[] = "HTTP Code: $httpCode";
    $diag[] = "Temps: {$totalTime}s";
    if ($effectiveUrl !== $url) {
        $diag[] = "Redirection vers: $effectiveUrl";
    }
    if ($curlError) {
        $diag[] = "CURL Erreur #$curlErrno: $curlError";
    }
    $diag[] = "Reponse (500 premiers chars): " . substr($response, 0, 500);

    $success = false;
    $decoded = json_decode($response, true);
    if ($decoded) {
        $diag[] = "JSON decode OK";
        $hasError = false;
        foreach ($decoded as $key => $value) {
            if (preg_match('/[Ss]tatus/', $key) && (int)$value === 0) {
                $hasError = true;
                $diag[] = "Champ $key = 0 (echec)";
            }
        }
        if (!$hasError && $httpCode >= 200 && $httpCode < 400) {
            $success = true;
        }
        if (isset($decoded['error_message'])) {
            $diag[] = "error_message: " . $decoded['error_message'];
        }
    } else {
        $diag[] = "JSON decode ECHEC - la reponse n'est pas du JSON valide";
        if (stripos($response, '<html') !== false || stripos($response, '<!DOCTYPE') !== false) {
            $diag[] = "La reponse semble etre une page HTML (page de login CyberPanel?)";
        }
    }

    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Connexion reussie !' : 'Echec - voir diagnostic',
        'diagnostic' => implode("\n", $diag)
    ]);
    exit;
}

if ($page === 'login') {
    require $pageFile;
} else {
    require __DIR__ . '/layout.php';
}
