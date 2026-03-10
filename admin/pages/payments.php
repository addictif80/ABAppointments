<?php
$pageTitle = 'Paiements';
$db = Database::getInstance();

$total = $db->count('wp_payments', '1');
$pagination = wp_paginate($total);
$payments = $db->fetchAll(
    "SELECT p.*, u.first_name, u.last_name, i.invoice_number
     FROM wp_payments p JOIN wp_users u ON p.user_id = u.id LEFT JOIN wp_invoices i ON p.invoice_id = i.id
     ORDER BY p.created_at DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}"
);

$totalCompleted = $db->fetchColumn("SELECT COALESCE(SUM(amount),0) FROM wp_payments WHERE status = 'completed'");
$monthTotal = $db->fetchColumn("SELECT COALESCE(SUM(amount),0) FROM wp_payments WHERE status = 'completed' AND created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')");
?>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="stat-card text-center"><div class="text-muted small">Total encaisse</div><div class="fw-bold fs-4 text-success"><?= wp_format_price($totalCompleted) ?></div></div></div>
    <div class="col-md-4"><div class="stat-card text-center"><div class="text-muted small">Ce mois</div><div class="fw-bold fs-4 text-primary"><?= wp_format_price($monthTotal) ?></div></div></div>
    <div class="col-md-4"><div class="stat-card text-center"><div class="text-muted small">Transactions</div><div class="fw-bold fs-4"><?= $total ?></div></div></div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Date</th><th>Client</th><th>Facture</th><th>Montant</th><th>Methode</th><th>Statut</th></tr></thead>
            <tbody>
            <?php foreach ($payments as $p): ?>
            <tr>
                <td><?= wp_format_datetime($p['created_at']) ?></td>
                <td><a href="<?= wp_url("admin/?page=client-detail&id={$p['user_id']}") ?>"><?= wp_escape($p['first_name'] . ' ' . $p['last_name']) ?></a></td>
                <td><?= $p['invoice_number'] ? '<a href="' . wp_url("admin/?page=invoice-detail&id={$p['invoice_id']}") . '">' . wp_escape($p['invoice_number']) . '</a>' : '-' ?></td>
                <td class="fw-bold"><?= wp_format_price($p['amount']) ?></td>
                <td><?= ucfirst($p['method']) ?></td>
                <td><span class="badge bg-<?= $p['status'] === 'completed' ? 'success' : ($p['status'] === 'failed' ? 'danger' : 'warning') ?>"><?= $p['status'] ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?= wp_pagination_html($pagination, wp_url('admin/?page=payments')) ?>
