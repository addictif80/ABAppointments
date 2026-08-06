<?php
require_once __DIR__ . '/../core/App.php';

$businessName = ab_setting('business_name', 'ABAppointments');
$primaryColor = ab_setting('primary_color', '#e91e63');

header('Content-Type: application/manifest+json; charset=utf-8');

echo json_encode([
    'name' => $businessName . ' - Administration',
    'short_name' => $businessName,
    'description' => 'Gestion des rendez-vous pour ' . $businessName,
    'start_url' => ab_url('admin/index.php?page=dashboard'),
    'scope' => ab_url('admin/'),
    'display' => 'standalone',
    'background_color' => '#f4f6f9',
    'theme_color' => $primaryColor,
    'icons' => [
        ['src' => ab_url('admin/icon.php'), 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any'],
        ['src' => ab_url('admin/icon.php?maskable=1'), 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'maskable'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
