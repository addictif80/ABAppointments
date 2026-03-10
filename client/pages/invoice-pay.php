<?php
$pageTitle = 'Paiement';
$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$invoiceId = (int)($_GET['id'] ?? 0);

$invoice = $db->fetchOne("SELECT * FROM wp_invoices WHERE id = ? AND user_id = ? AND status IN ('pending','overdue')", [$invoiceId, $userId]);
if (!$invoice) { wp_flash('error', 'Facture introuvable ou deja payee.'); wp_redirect(wp_url('client/?page=invoices')); }

$stripe = new StripeManager();
$user = Auth::user();

// Create or get Stripe customer
if (empty($user['stripe_customer_id'])) {
    try {
        $customer = $stripe->createCustomer($user['email'], $user['first_name'] . ' ' . $user['last_name']);
        $db->update('wp_users', ['stripe_customer_id' => $customer['id']], 'id = ?', [$userId]);
        $user['stripe_customer_id'] = $customer['id'];
    } catch (Exception $e) {
        wp_flash('error', 'Erreur Stripe: ' . $e->getMessage());
        wp_redirect(wp_url("client/?page=invoice-detail&id=$invoiceId"));
    }
}

// Create checkout session
try {
    $session = $stripe->createCheckoutSession(
        [['name' => "Facture {$invoice['invoice_number']}", 'amount' => $invoice['total'], 'currency' => strtolower($invoice['currency'])]],
        $user['stripe_customer_id'],
        wp_url("client/?page=invoice-detail&id=$invoiceId&payment=success"),
        wp_url("client/?page=invoice-detail&id=$invoiceId&payment=cancel"),
        ['invoice_id' => $invoiceId, 'user_id' => $userId]
    );

    // Redirect to Stripe Checkout
    header('Location: ' . $session['url']);
    exit;
} catch (Exception $e) {
    wp_flash('error', 'Erreur lors de la creation du paiement: ' . $e->getMessage());
    wp_redirect(wp_url("client/?page=invoice-detail&id=$invoiceId"));
}
