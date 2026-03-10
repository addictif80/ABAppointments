<?php
$pageTitle = 'Detail Facture';
$db = Database::getInstance();
$invoiceId = (int)($_GET['id'] ?? 0);

$invoice = $db->fetchOne("SELECT i.*, u.first_name, u.last_name, u.email, u.company, u.address, u.city, u.postal_code, u.country FROM wp_invoices i JOIN wp_users u ON i.user_id = u.id WHERE i.id = ?", [$invoiceId]);
if (!$invoice) { wp_flash('error', 'Facture introuvable.'); wp_redirect(wp_url('admin/?page=invoices')); }

$items = $db->fetchAll("SELECT * FROM wp_invoice_items WHERE invoice_id = ?", [$invoiceId]);
$payments = $db->fetchAll("SELECT * FROM wp_payments WHERE invoice_id = ? ORDER BY created_at DESC", [$invoiceId]);
$pageTitle = 'Facture ' . $invoice['invoice_number'];
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between mb-4">
                    <div>
                        <h4><?= wp_escape($invoice['invoice_number']) ?></h4>
                        <?php $sc = ['paid' => 'success', 'pending' => 'warning', 'overdue' => 'danger', 'cancelled' => 'dark']; ?>
                        <span class="badge bg-<?= $sc[$invoice['status']] ?? 'secondary' ?> fs-6"><?= ucfirst($invoice['status']) ?></span>
                    </div>
                    <div class="text-end">
                        <strong><?= wp_escape(wp_setting('company_name')) ?></strong>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-muted">Client</h6>
                        <strong><?= wp_escape($invoice['first_name'] . ' ' . $invoice['last_name']) ?></strong><br>
                        <?php if ($invoice['company']): ?><?= wp_escape($invoice['company']) ?><br><?php endif; ?>
                        <?= wp_escape($invoice['email']) ?>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <div><span class="text-muted">Date :</span> <?= wp_format_date($invoice['created_at']) ?></div>
                        <div><span class="text-muted">Echeance :</span> <?= wp_format_date($invoice['due_date']) ?></div>
                        <?php if ($invoice['paid_at']): ?><div><span class="text-muted">Paye :</span> <?= wp_format_datetime($invoice['paid_at']) ?></div><?php endif; ?>
                        <div><span class="text-muted">Relances :</span> <?= $invoice['reminder_sent'] ?></div>
                    </div>
                </div>

                <table class="table">
                    <thead><tr><th>Description</th><th class="text-center">Qte</th><th class="text-end">PU</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr><td><?= wp_escape($item['description']) ?></td><td class="text-center"><?= $item['quantity'] ?></td><td class="text-end"><?= wp_format_price($item['unit_price']) ?></td><td class="text-end"><?= wp_format_price($item['total']) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr><td colspan="3" class="text-end"><strong>Sous-total HT</strong></td><td class="text-end"><?= wp_format_price($invoice['subtotal']) ?></td></tr>
                        <tr><td colspan="3" class="text-end">TVA (<?= $invoice['tax_rate'] ?>%)</td><td class="text-end"><?= wp_format_price($invoice['tax_amount']) ?></td></tr>
                        <tr><td colspan="3" class="text-end"><strong class="fs-5">Total TTC</strong></td><td class="text-end"><strong class="fs-5"><?= wp_format_price($invoice['total']) ?></strong></td></tr>
                    </tfoot>
                </table>

                <?php if (in_array($invoice['status'], ['pending', 'overdue'])): ?>
                <div class="d-flex gap-2 mt-3">
                    <form method="POST" action="<?= wp_url('admin/?page=invoices') ?>">
                        <?= wp_csrf_field() ?><input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
                        <button name="action" value="mark_paid" class="btn btn-success"><i class="bi bi-check me-1"></i>Marquer payee</button>
                        <button name="action" value="send_reminder" class="btn btn-warning"><i class="bi bi-envelope me-1"></i>Envoyer relance</button>
                    </form>
                </div>
                <?php endif; ?>

                <?php if (!empty($payments)): ?>
                <h6 class="mt-4">Paiements</h6>
                <table class="table table-sm">
                    <thead><tr><th>Date</th><th>Montant</th><th>Methode</th><th>Statut</th></tr></thead>
                    <tbody>
                    <?php foreach ($payments as $p): ?>
                    <tr><td><?= wp_format_datetime($p['created_at']) ?></td><td><?= wp_format_price($p['amount']) ?></td><td><?= ucfirst($p['method']) ?></td>
                        <td><span class="badge bg-<?= $p['status'] === 'completed' ? 'success' : 'warning' ?>"><?= $p['status'] ?></span></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
