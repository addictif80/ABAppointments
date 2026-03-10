<?php
/**
 * WebPanel - Stripe Webhook Handler
 */
require_once __DIR__ . '/../core/App.php';

$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$webhookSecret = wp_setting('stripe_webhook_secret');

if (empty($payload) || empty($sigHeader)) {
    http_response_code(400);
    exit('Missing payload or signature');
}

$stripe = new StripeManager();

if ($webhookSecret && !$stripe->verifyWebhookSignature($payload, $sigHeader, $webhookSecret)) {
    http_response_code(400);
    exit('Invalid signature');
}

$event = json_decode($payload, true);
if (!$event || !isset($event['type'])) {
    http_response_code(400);
    exit('Invalid payload');
}

$db = Database::getInstance();

try {
    switch ($event['type']) {
        case 'checkout.session.completed':
            $session = $event['data']['object'];
            $invoiceId = $session['metadata']['invoice_id'] ?? null;
            if ($invoiceId) {
                $invoice = $db->fetchOne("SELECT * FROM wp_invoices WHERE id = ? AND status IN ('pending','overdue')", [$invoiceId]);
                if ($invoice) {
                    InvoiceManager::markPaid($invoiceId, 'stripe', $session['payment_intent'] ?? null);
                    wp_log_activity('stripe_payment_received', 'invoice', $invoiceId, [
                        'session_id' => $session['id'],
                        'amount' => ($session['amount_total'] ?? 0) / 100
                    ]);
                }
            }
            break;

        case 'payment_intent.succeeded':
            $pi = $event['data']['object'];
            $invoiceId = $pi['metadata']['invoice_id'] ?? null;
            if ($invoiceId) {
                $invoice = $db->fetchOne("SELECT * FROM wp_invoices WHERE id = ? AND status IN ('pending','overdue')", [$invoiceId]);
                if ($invoice) {
                    InvoiceManager::markPaid($invoiceId, 'stripe', $pi['id']);
                }
            }
            break;

        case 'payment_intent.payment_failed':
            $pi = $event['data']['object'];
            $invoiceId = $pi['metadata']['invoice_id'] ?? null;
            if ($invoiceId) {
                wp_log_activity('stripe_payment_failed', 'invoice', $invoiceId, [
                    'error' => $pi['last_payment_error']['message'] ?? 'Unknown'
                ]);
            }
            break;

        case 'charge.refunded':
            $charge = $event['data']['object'];
            $paymentIntentId = $charge['payment_intent'] ?? null;
            if ($paymentIntentId) {
                $payment = $db->fetchOne("SELECT * FROM wp_payments WHERE stripe_payment_intent_id = ?", [$paymentIntentId]);
                if ($payment) {
                    $db->update('wp_payments', ['status' => 'refunded'], 'id = ?', [$payment['id']]);
                    if ($payment['invoice_id']) {
                        $db->update('wp_invoices', ['status' => 'refunded'], 'id = ?', [$payment['invoice_id']]);
                    }
                    wp_log_activity('stripe_refund', 'payment', $payment['id']);
                }
            }
            break;
    }

    http_response_code(200);
    echo json_encode(['received' => true]);
} catch (Exception $e) {
    wp_log_activity('stripe_webhook_error', null, null, ['error' => $e->getMessage(), 'event_type' => $event['type']]);
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
