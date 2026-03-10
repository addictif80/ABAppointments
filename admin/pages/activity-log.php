<?php
$pageTitle = 'Journal d\'activite';
$db = Database::getInstance();

$total = $db->count('wp_activity_log', '1');
$pagination = wp_paginate($total, 50);
$logs = $db->fetchAll(
    "SELECT a.*, u.first_name, u.last_name, u.email
     FROM wp_activity_log a LEFT JOIN wp_users u ON a.user_id = u.id
     ORDER BY a.created_at DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}"
);
?>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead><tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Entite</th><th>IP</th><th>Details</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $l): ?>
            <tr>
                <td class="text-nowrap"><?= wp_format_datetime($l['created_at']) ?></td>
                <td><?= $l['first_name'] ? wp_escape($l['first_name'] . ' ' . $l['last_name']) : '<em class="text-muted">Systeme</em>' ?></td>
                <td><code><?= wp_escape($l['action']) ?></code></td>
                <td><?= $l['entity_type'] ? wp_escape($l['entity_type']) . ' #' . $l['entity_id'] : '-' ?></td>
                <td class="font-monospace small"><?= wp_escape($l['ip_address'] ?: '-') ?></td>
                <td><?php if ($l['details']): $d = json_decode($l['details'], true); if ($d) echo '<small>' . wp_escape(implode(', ', array_map(fn($k,$v) => "$k: $v", array_keys($d), $d))) . '</small>'; endif; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?= wp_pagination_html($pagination, wp_url('admin/?page=activity-log')) ?>
