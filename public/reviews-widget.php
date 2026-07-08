<?php
/**
 * ABAppointments - Embeddable Reviews Widget (for external websites, iframe)
 */
require_once __DIR__ . '/../core/App.php';

header('X-Frame-Options: ALLOWALL');
header('Content-Security-Policy: frame-ancestors *');

$reviews = new Reviews();
$stats = $reviews->getStats();
$list = $reviews->getApproved((int)($_GET['limit'] ?? 10));
$primaryColor = ab_setting('primary_color', '#e91e63');
$businessName = ab_setting('business_name', 'ABAppointments');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ab_escape($businessName) ?> - Avis clients</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; background: transparent; color: #333; }
        .widget { padding: 12px; }
        .summary { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
        .summary .score { font-size: 1.6rem; font-weight: 700; }
        .stars { color: #ffc107; letter-spacing: 1px; }
        .summary .count { color: #888; font-size: 0.85rem; }
        .cards { display: flex; gap: 12px; overflow-x: auto; padding-bottom: 6px; }
        .card { flex: 0 0 240px; background: #fff; border: 1px solid #eee; border-radius: 12px; padding: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .card .stars { font-size: 0.9rem; }
        .card p { font-size: 0.85rem; color: #555; margin: 8px 0; }
        .card .name { font-size: 0.8rem; color: #999; }
        .empty { color: #999; font-size: 0.9rem; text-align: center; padding: 20px 0; }
    </style>
</head>
<body>
<div class="widget">
    <?php if ((int)$stats['total'] > 0): ?>
    <div class="summary">
        <div class="score"><?= $stats['avg_rating'] ?></div>
        <div>
            <div class="stars"><?= str_repeat('★', (int)round($stats['avg_rating'])) . str_repeat('☆', 5 - (int)round($stats['avg_rating'])) ?></div>
            <div class="count"><?= (int)$stats['total'] ?> avis</div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($list)): ?>
    <div class="empty">Aucun avis pour le moment.</div>
    <?php else: ?>
    <div class="cards">
        <?php foreach ($list as $rv): ?>
        <div class="card">
            <div class="stars"><?= str_repeat('★', (int)$rv['rating']) . str_repeat('☆', 5 - (int)$rv['rating']) ?></div>
            <?php if (!empty($rv['comment'])): ?>
            <p><?= nl2br(ab_escape($rv['comment'])) ?></p>
            <?php endif; ?>
            <div class="name">— <?= ab_escape($rv['customer_first_name']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
