<?php
$pageTitle = 'Paiement';
$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$invoiceId = (int)($_GET['id'] ?? 0);

$invoice = $db->fetchOne("SELECT * FROM wp_invoices WHERE id = ? AND user_id = ? AND status IN ('pending','overdue')", [$invoiceId, $userId]);
if (!$invoice) {
    echo '<div class="alert alert-danger">Facture introuvable ou deja payee.</div>';
    echo '<a href="' . wp_url('client/?page=invoices') . '" class="btn btn-primary">Retour aux factures</a>';
    return;
}

$stripe = new StripeManager();

if (!$stripe->isConfigured()) {
    echo '<div class="alert alert-danger">Le paiement en ligne n\'est pas encore configure. Contactez l\'administrateur.</div>';
    echo '<a href="' . wp_url("client/?page=invoice-detail&id=$invoiceId") . '" class="btn btn-primary">Retour a la facture</a>';
    return;
}

$user = Auth::user();
$error = null;
$redirectUrl = null;

// Create or get Stripe customer
if (empty($user['stripe_customer_id'])) {
    try {
        $customer = $stripe->createCustomer($user['email'], $user['first_name'] . ' ' . $user['last_name']);
        $db->update('wp_users', ['stripe_customer_id' => $customer['id']], 'id = ?', [$userId]);
        $user['stripe_customer_id'] = $customer['id'];
    } catch (Exception $e) {
        $error = 'Erreur Stripe: ' . $e->getMessage();
    }
}

// Create checkout session
if (!$error) {
    try {
        $session = $stripe->createCheckoutSession(
            [['name' => "Facture {$invoice['invoice_number']}", 'amount' => $invoice['total'], 'currency' => strtolower($invoice['currency'])]],
            $user['stripe_customer_id'],
            wp_url("client/?page=invoice-detail&id=$invoiceId&payment=success"),
            wp_url("client/?page=invoice-detail&id=$invoiceId&payment=cancel"),
            ['invoice_id' => $invoiceId, 'user_id' => $userId]
        );
        $redirectUrl = $session['url'];
        // Store checkout session ID on invoice for payment verification on return
        $db->update('wp_invoices', ['stripe_checkout_session_id' => $session['id']], 'id = ?', [$invoiceId]);
    } catch (Exception $e) {
        $error = 'Erreur lors de la creation du paiement: ' . $e->getMessage();
    }
}

if ($error): ?>
    <div class="alert alert-danger"><?= wp_escape($error) ?></div>
    <a href="<?= wp_url("client/?page=invoice-detail&id=$invoiceId") ?>" class="btn btn-primary">Retour a la facture</a>
<?php else: ?>
    <div class="text-center py-5">
        <div class="spinner-border text-primary mb-3" role="status"></div>
        <p>Redirection vers le paiement securise...</p>
    </div>
    <script>window.location.href = <?= json_encode($redirectUrl) ?>;</script>
<?php endif; ?>
