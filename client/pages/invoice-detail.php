<?php
$pageTitle = 'Detail Facture';
$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$invoiceId = (int)($_GET['id'] ?? 0);

$invoice = $db->fetchOne("SELECT * FROM wp_invoices WHERE id = ? AND user_id = ?", [$invoiceId, $userId]);
if (!$invoice) { wp_flash('error', 'Facture introuvable.'); wp_redirect(wp_url('client/?page=invoices')); }

// Check payment status on return from Stripe
if (isset($_GET['payment']) && $_GET['payment'] === 'success' && in_array($invoice['status'], ['pending', 'overdue'])) {
    $stripe = new StripeManager();
    $sessionId = $invoice['stripe_checkout_session_id'] ?? null;
    if ($stripe->isConfigured() && !empty($sessionId)) {
        try {
            $session = $stripe->getCheckoutSession($sessionId);
            if ($session['payment_status'] === 'paid' && !empty($session['payment_intent'])) {
                InvoiceManager::markPaid($invoiceId, 'stripe', $session['payment_intent']);
                $invoice = $db->fetchOne("SELECT * FROM wp_invoices WHERE id = ? AND user_id = ?", [$invoiceId, $userId]);
            }
        } catch (Exception $e) {
            error_log("Stripe payment verify error for invoice $invoiceId: " . $e->getMessage());
        }
    } else {
        error_log("Stripe verify skipped for invoice $invoiceId: configured=" . ($stripe->isConfigured() ? 'yes' : 'no') . " sessionId=" . ($sessionId ?: 'null'));
    }
    if (in_array($invoice['status'], ['pending', 'overdue'])) {
        $paymentPending = true;
    }
}

$items = $db->fetchAll("SELECT * FROM wp_invoice_items WHERE invoice_id = ?", [$invoiceId]);
$user = Auth::user();

$statusLabels = ['draft' => 'Brouillon', 'pending' => 'En attente', 'paid' => 'Payee', 'overdue' => 'En retard', 'cancelled' => 'Annulee', 'refunded' => 'Remboursee'];
$statusColors = ['draft' => 'secondary', 'pending' => 'warning', 'paid' => 'success', 'overdue' => 'danger', 'cancelled' => 'dark', 'refunded' => 'info'];
$pageTitle = 'Facture ' . $invoice['invoice_number'];
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div>
                        <h4><?= wp_escape($invoice['invoice_number']) ?></h4>
                        <span class="badge bg-<?= $statusColors[$invoice['status']] ?? 'secondary' ?> fs-6"><?= $statusLabels[$invoice['status']] ?? $invoice['status'] ?></span>
                    </div>
                    <div class="text-end">
                        <strong><?= wp_escape(wp_setting('company_name')) ?></strong><br>
                        <small class="text-muted"><?= nl2br(wp_escape(wp_setting('company_address'))) ?></small>
                        <?php if (wp_setting('company_siret')): ?><br><small class="text-muted">SIRET: <?= wp_escape(wp_setting('company_siret')) ?></small><?php endif; ?>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-muted">Facture a</h6>
                        <strong><?= wp_escape($user['first_name'] . ' ' . $user['last_name']) ?></strong><br>
                        <?php if ($user['company']): ?><?= wp_escape($user['company']) ?><br><?php endif; ?>
                        <?= wp_escape($user['email']) ?>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <div class="mb-1"><span class="text-muted">Date :</span> <?= wp_format_date($invoice['created_at']) ?></div>
                        <div class="mb-1"><span class="text-muted">Echeance :</span> <?= wp_format_date($invoice['due_date']) ?></div>
                        <?php if ($invoice['paid_at']): ?><div><span class="text-muted">Paye le :</span> <?= wp_format_datetime($invoice['paid_at']) ?></div><?php endif; ?>
                    </div>
                </div>

                <table class="table">
                    <thead><tr><th>Description</th><th class="text-center">Qte</th><th class="text-end">Prix unitaire</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= wp_escape($item['description']) ?></td>
                            <td class="text-center"><?= $item['quantity'] ?></td>
                            <td class="text-end"><?= wp_format_price($item['unit_price']) ?></td>
                            <td class="text-end"><?= wp_format_price($item['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr><td colspan="3" class="text-end"><strong>Sous-total HT</strong></td><td class="text-end"><?= wp_format_price($invoice['subtotal']) ?></td></tr>
                        <tr><td colspan="3" class="text-end">TVA (<?= $invoice['tax_rate'] ?>%)</td><td class="text-end"><?= wp_format_price($invoice['tax_amount']) ?></td></tr>
                        <tr><td colspan="3" class="text-end"><strong class="fs-5">Total TTC</strong></td><td class="text-end"><strong class="fs-5"><?= wp_format_price($invoice['total']) ?></strong></td></tr>
                    </tfoot>
                </table>

                <?php if (!empty($paymentPending)): ?>
                <div class="alert alert-info text-center mt-4">
                    <i class="bi bi-hourglass-split me-2"></i>
                    <strong>Paiement en cours de traitement...</strong><br>
                    <small>Votre paiement a ete recu et sera confirme sous peu. Rafraichissez la page dans quelques instants.</small>
                </div>
                <?php elseif (in_array($invoice['status'], ['pending', 'overdue'])): ?>
                <div class="text-center mt-4">
                    <a href="<?= wp_url("client/?page=invoice-pay&id={$invoice['id']}") ?>" class="btn btn-primary btn-lg">
                        <i class="bi bi-credit-card me-2"></i> Payer maintenant
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
