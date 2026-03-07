<?php
if (!isset($_GET['code']) || !isset($_GET['state'])) {
    ab_flash('error', 'Paramètres manquants pour la connexion Google.');
    ab_redirect(ab_url('admin/index.php?page=settings&tab=google'));
}

$gcal = new GoogleCalendar();
$providerId = (int) $_GET['state'];

if ($gcal->handleCallback($_GET['code'], $providerId)) {
    ab_flash('success', 'Google Calendar connecté avec succès !');
} else {
    ab_flash('error', 'Erreur lors de la connexion à Google Calendar.');
}

ab_redirect(ab_url('admin/index.php?page=settings&tab=google'));
