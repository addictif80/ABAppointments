<?php
/**
 * ABAppointments - URSSAF declaration PDF download
 * Standalone endpoint (outside the admin layout) so the binary PDF stream
 * is never mixed with the sidebar/layout HTML.
 */
require_once __DIR__ . '/../core/App.php';

Auth::requireAdmin();

$db = Database::getInstance();
$declaration = $db->fetchOne("SELECT * FROM ab_urssaf_declarations WHERE id = ?", [(int) ($_GET['id'] ?? 0)]);
$path = $declaration ? __DIR__ . '/../storage/urssaf_declarations/' . $declaration['pdf_filename'] : null;

if (!$declaration || !$path || !is_file($path)) {
    http_response_code(404);
    exit('Fichier non trouvé.');
}

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . basename($path) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
