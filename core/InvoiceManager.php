<?php
/**
 * WebPanel - Invoice Manager
 */
class InvoiceManager {

    public static function create($userId, $subscriptionId, $items, $dueDate = null, $promoCodeId = null, $discountAmount = 0) {
        $db = Database::getInstance();
        $settings = new Settings();

        $prefix = $settings->get('invoice_prefix', 'INV-');
        $nextNum = (int)$settings->get('invoice_next_number', 1);
        $invoiceNumber = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
        $taxRate = (float)$settings->get('tax_rate', 20);

        if (!$dueDate) $dueDate = date('Y-m-d', strtotime('+15 days'));

        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += ($item['unit_price'] * ($item['quantity'] ?? 1));
        }

        $taxAmount = round($subtotal * $taxRate / 100, 2);
        $total = round($subtotal + $taxAmount, 2);
        $total = max(0, $total);

        $db->beginTransaction();
        try {
            $invoiceData = [
                'invoice_number' => $invoiceNumber,
                'user_id' => $userId,
                'subscription_id' => $subscriptionId,
                'status' => 'pending',
                'subtotal' => $subtotal,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discountAmount,
                'total' => $total,
                'due_date' => $dueDate,
            ];
            if ($promoCodeId) {
                $invoiceData['promo_code_id'] = $promoCodeId;
            }
            $invoiceId = $db->insert('wp_invoices', $invoiceData);

            foreach ($items as $item) {
                $qty = $item['quantity'] ?? 1;
                $db->insert('wp_invoice_items', [
                    'invoice_id' => $invoiceId,
                    'description' => $item['description'],
                    'quantity' => $qty,
                    'unit_price' => $item['unit_price'],
                    'total' => round($item['unit_price'] * $qty, 2)
                ]);
            }

            $settings->set('invoice_next_number', $nextNum + 1);
            $db->commit();

            // Send email
            $user = $db->fetchOne("SELECT * FROM wp_users WHERE id = ?", [$userId]);
            if ($user) {
                $mailer = new Mailer();
                $mailer->sendTemplate($user['email'], 'invoice_created', [
                    'first_name' => $user['first_name'],
                    'invoice_number' => $invoiceNumber,
                    'total' => wp_format_price($total),
                    'currency' => $settings->get('currency', 'EUR'),
                    'due_date' => wp_format_date($dueDate),
                    'invoice_url' => wp_url("client/?page=invoice-detail&id=$invoiceId")
                ]);
            }

            wp_log_activity('invoice_created', 'invoice', $invoiceId, ['number' => $invoiceNumber, 'total' => $total]);
            return $invoiceId;

        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }

    public static function markPaid($invoiceId, $paymentMethod = 'stripe', $stripePaymentIntentId = null) {
        $db = Database::getInstance();
        $invoice = $db->fetchOne("SELECT * FROM wp_invoices WHERE id = ?", [$invoiceId]);
        if (!$invoice) return false;

        $db->update('wp_invoices', [
            'status' => 'paid',
            'paid_at' => date('Y-m-d H:i:s'),
            'payment_method' => $paymentMethod,
            'stripe_payment_intent_id' => $stripePaymentIntentId
        ], 'id = ?', [$invoiceId]);

        $db->insert('wp_payments', [
            'invoice_id' => $invoiceId,
            'user_id' => $invoice['user_id'],
            'amount' => $invoice['total'],
            'method' => $paymentMethod,
            'status' => 'completed',
            'stripe_payment_intent_id' => $stripePaymentIntentId
        ]);

        // Activate subscription if pending
        if ($invoice['subscription_id']) {
            $sub = $db->fetchOne("SELECT * FROM wp_subscriptions WHERE id = ?", [$invoice['subscription_id']]);
            if ($sub && in_array($sub['status'], ['pending', 'suspended'])) {
                $nextDue = date('Y-m-d', strtotime(
                    $sub['billing_cycle'] === 'yearly' ? '+1 year' : '+1 month'
                ));
                $db->update('wp_subscriptions', [
                    'status' => 'active',
                    'started_at' => $sub['started_at'] ?: date('Y-m-d H:i:s'),
                    'suspended_at' => null,
                    'suspension_reason' => null,
                    'next_due_date' => $nextDue
                ], 'id = ?', [$sub['id']]);

                // Unsuspend service if it was suspended
                if ($sub['status'] === 'suspended') {
                    ServiceManager::unsuspendService($sub['id']);
                }

                // Provision if new
                if ($sub['status'] === 'pending') {
                    ServiceManager::provisionService($sub['id']);
                }
            }
        }

        // Send confirmation email
        $user = $db->fetchOne("SELECT * FROM wp_users WHERE id = ?", [$invoice['user_id']]);
        if ($user) {
            $mailer = new Mailer();
            $mailer->sendTemplate($user['email'], 'invoice_paid', [
                'first_name' => $user['first_name'],
                'invoice_number' => $invoice['invoice_number'],
                'total' => wp_format_price($invoice['total']),
                'currency' => wp_setting('currency', 'EUR')
            ]);
        }

        wp_log_activity('invoice_paid', 'invoice', $invoiceId);
        return true;
    }

    public static function generateRenewalInvoice($subscriptionId) {
        $db = Database::getInstance();
        $sub = $db->fetchOne(
            "SELECT s.*, p.name as product_name FROM wp_subscriptions s JOIN wp_products p ON s.product_id = p.id WHERE s.id = ?",
            [$subscriptionId]
        );
        if (!$sub) return false;

        $period = $sub['billing_cycle'] === 'yearly' ? 'annuel' : 'mensuel';
        return self::create($sub['user_id'], $subscriptionId, [
            ['description' => "Renouvellement $period - {$sub['product_name']}", 'unit_price' => $sub['price'], 'quantity' => 1]
        ], $sub['next_due_date']);
    }

    public static function checkOverdue() {
        $db = Database::getInstance();
        $db->query(
            "UPDATE wp_invoices SET status = 'overdue' WHERE status = 'pending' AND due_date < CURDATE()"
        );
    }
}
