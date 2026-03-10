<?php
$pageTitle = 'Tickets Support';
$db = Database::getInstance();

$status = $_GET['status'] ?? 'all';
$where = '1';
$params = [];
if ($status === 'open') { $where = "t.status NOT IN ('resolved','closed')"; }
elseif ($status === 'closed') { $where = "t.status IN ('resolved','closed')"; }
elseif ($status !== 'all') { $where = "t.status = ?"; $params = [$status]; }

$total = $db->fetchColumn("SELECT COUNT(*) FROM wp_tickets t WHERE $where", $params);
$pagination = wp_paginate($total);
$tickets = $db->fetchAll(
    "SELECT t.*, u.first_name, u.last_name, a.first_name as assigned_first, a.last_name as assigned_last,
     (SELECT COUNT(*) FROM wp_ticket_messages WHERE ticket_id = t.id) as msg_count
     FROM wp_tickets t JOIN wp_users u ON t.user_id = u.id LEFT JOIN wp_users a ON t.assigned_to = a.id
     WHERE $where ORDER BY FIELD(t.priority,'critical','high','medium','low'), t.updated_at DESC
     LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}",
    $params
);

$statusLabels = ['open' => 'Ouvert', 'in_progress' => 'En cours', 'waiting_client' => 'Attente client', 'waiting_admin' => 'Attente support', 'resolved' => 'Resolu', 'closed' => 'Ferme'];
$statusColors = ['open' => 'primary', 'in_progress' => 'info', 'waiting_client' => 'warning', 'waiting_admin' => 'secondary', 'resolved' => 'success', 'closed' => 'dark'];
$priorityColors = ['low' => 'secondary', 'medium' => 'primary', 'high' => 'warning', 'critical' => 'danger'];
?>

<ul class="nav nav-pills mb-4">
    <li class="nav-item"><a class="nav-link <?= $status === 'all' ? 'active' : '' ?>" href="<?= wp_url('admin/?page=tickets') ?>">Tous</a></li>
    <li class="nav-item"><a class="nav-link <?= $status === 'open' ? 'active' : '' ?>" href="<?= wp_url('admin/?page=tickets&status=open') ?>">Ouverts</a></li>
    <li class="nav-item"><a class="nav-link <?= $status === 'waiting_admin' ? 'active' : '' ?>" href="<?= wp_url('admin/?page=tickets&status=waiting_admin') ?>">A traiter</a></li>
    <li class="nav-item"><a class="nav-link <?= $status === 'closed' ? 'active' : '' ?>" href="<?= wp_url('admin/?page=tickets&status=closed') ?>">Fermes</a></li>
</ul>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Client</th><th>Sujet</th><th>Dept.</th><th>Priorite</th><th>Statut</th><th>Msgs</th><th>MAJ</th></tr></thead>
            <tbody>
            <?php foreach ($tickets as $t): ?>
            <tr onclick="location.href='<?= wp_url("admin/?page=ticket-detail&id={$t['id']}") ?>'" style="cursor:pointer">
                <td class="fw-semibold"><?= wp_escape($t['ticket_number']) ?></td>
                <td><?= wp_escape($t['first_name'] . ' ' . $t['last_name']) ?></td>
                <td><?= wp_escape(mb_substr($t['subject'], 0, 50)) ?></td>
                <td><?= ucfirst($t['department']) ?></td>
                <td><span class="badge bg-<?= $priorityColors[$t['priority']] ?? 'secondary' ?>"><?= ucfirst($t['priority']) ?></span></td>
                <td><span class="badge bg-<?= $statusColors[$t['status']] ?? 'secondary' ?>"><?= $statusLabels[$t['status']] ?? $t['status'] ?></span></td>
                <td><?= $t['msg_count'] ?></td>
                <td><?= wp_format_datetime($t['updated_at']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?= wp_pagination_html($pagination, wp_url("admin/?page=tickets&status=$status")) ?>
