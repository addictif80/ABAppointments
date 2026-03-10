<?php
$pageTitle = 'Mes Factures';
$db = Database::getInstance();
$userId = $_SESSION['user_id'];

$status = $_GET['status'] ?? 'all';
$where = "user_id = ?";
$params = [$userId];
if ($status !== 'all') { $where .= " AND status = ?"; $params[] = $status; }

$total = $db->count('wp_invoices', $where, $params);
$pagination = wp_paginate($total);
$invoices = $db->fetchAll(
    "SELECT * FROM wp_invoices WHERE $where ORDER BY created_at DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}",
    $params
);

$statusLabels = ['draft' => 'Brouillon', 'pending' => 'En attente', 'paid' => 'Payee', 'overdue' => 'En retard', 'cancelled' => 'Annulee', 'refunded' => 'Remboursee'];
$statusColors = ['draft' => 'secondary', 'pending' => 'warning', 'paid' => 'success', 'overdue' => 'danger', 'cancelled' => 'dark', 'refunded' => 'info'];
?>

<ul class="nav nav-pills mb-4">
    <li class="nav-item"><a class="nav-link <?= $status === 'all' ? 'active' : '' ?>" href="<?= wp_url('client/?page=invoices') ?>">Toutes</a></li>
    <li class="nav-item"><a class="nav-link <?= $status === 'pending' ? 'active' : '' ?>" href="<?= wp_url('client/?page=invoices&status=pending') ?>">En attente</a></li>
    <li class="nav-item"><a class="nav-link <?= $status === 'paid' ? 'active' : '' ?>" href="<?= wp_url('client/?page=invoices&status=paid') ?>">Payees</a></li>
    <li class="nav-item"><a class="nav-link <?= $status === 'overdue' ? 'active' : '' ?>" href="<?= wp_url('client/?page=invoices&status=overdue') ?>">En retard</a></li>
</ul>

<?php if (empty($invoices)): ?>
    <div class="text-center py-5 text-muted"><i class="bi bi-receipt fs-1"></i><p class="mt-2">Aucune facture</p></div>
<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>N</th><th>Date</th><th>Echeance</th><th>Montant</th><th>Statut</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($invoices as $inv): ?>
                <tr>
                    <td class="fw-semibold"><?= wp_escape($inv['invoice_number']) ?></td>
                    <td><?= wp_format_date($inv['created_at']) ?></td>
                    <td><?= wp_format_date($inv['due_date']) ?></td>
                    <td class="fw-bold"><?= wp_format_price($inv['total']) ?></td>
                    <td><span class="badge bg-<?= $statusColors[$inv['status']] ?? 'secondary' ?>"><?= $statusLabels[$inv['status']] ?? $inv['status'] ?></span></td>
                    <td>
                        <a href="<?= wp_url("client/?page=invoice-detail&id={$inv['id']}") ?>" class="btn btn-sm btn-outline-primary">Voir</a>
                        <?php if (in_array($inv['status'], ['pending', 'overdue'])): ?>
                            <a href="<?= wp_url("client/?page=invoice-pay&id={$inv['id']}") ?>" class="btn btn-sm btn-primary">Payer</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?= wp_pagination_html($pagination, wp_url("client/?page=invoices&status=$status")) ?>
<?php endif; ?>
