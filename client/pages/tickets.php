<?php
$pageTitle = 'Support';
$db = Database::getInstance();
$userId = $_SESSION['user_id'];

$status = $_GET['status'] ?? 'all';
$where = "t.user_id = ?";
$params = [$userId];
if ($status === 'open') { $where .= " AND t.status NOT IN ('resolved','closed')"; }
elseif ($status === 'closed') { $where .= " AND t.status IN ('resolved','closed')"; }

$total = $db->fetchColumn("SELECT COUNT(*) FROM wp_tickets t WHERE $where", $params);
$pagination = wp_paginate($total);
$tickets = $db->fetchAll(
    "SELECT t.*, (SELECT COUNT(*) FROM wp_ticket_messages WHERE ticket_id = t.id) as msg_count
     FROM wp_tickets t WHERE $where ORDER BY t.updated_at DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}",
    $params
);

$statusLabels = ['open' => 'Ouvert', 'in_progress' => 'En cours', 'waiting_client' => 'Reponse attendue', 'waiting_admin' => 'En attente support', 'resolved' => 'Resolu', 'closed' => 'Ferme'];
$statusColors = ['open' => 'primary', 'in_progress' => 'info', 'waiting_client' => 'warning', 'waiting_admin' => 'secondary', 'resolved' => 'success', 'closed' => 'dark'];
$priorityColors = ['low' => 'secondary', 'medium' => 'primary', 'high' => 'warning', 'critical' => 'danger'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <ul class="nav nav-pills">
        <li class="nav-item"><a class="nav-link <?= $status === 'all' ? 'active' : '' ?>" href="<?= wp_url('client/?page=tickets') ?>">Tous</a></li>
        <li class="nav-item"><a class="nav-link <?= $status === 'open' ? 'active' : '' ?>" href="<?= wp_url('client/?page=tickets&status=open') ?>">Ouverts</a></li>
        <li class="nav-item"><a class="nav-link <?= $status === 'closed' ? 'active' : '' ?>" href="<?= wp_url('client/?page=tickets&status=closed') ?>">Fermes</a></li>
    </ul>
    <a href="<?= wp_url('client/?page=ticket-new') ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Nouveau ticket</a>
</div>

<?php if (empty($tickets)): ?>
    <div class="text-center py-5 text-muted"><i class="bi bi-chat-left-text fs-1"></i><p class="mt-2">Aucun ticket</p></div>
<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Sujet</th><th>Departement</th><th>Priorite</th><th>Statut</th><th>Messages</th><th>Derniere MAJ</th></tr></thead>
            <tbody>
            <?php foreach ($tickets as $t): ?>
                <tr class="cursor-pointer" onclick="location.href='<?= wp_url("client/?page=ticket-detail&id={$t['id']}") ?>'">
                    <td class="fw-semibold"><?= wp_escape($t['ticket_number']) ?></td>
                    <td><?= wp_escape($t['subject']) ?></td>
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
<?= wp_pagination_html($pagination, wp_url("client/?page=tickets&status=$status")) ?>
<?php endif; ?>
