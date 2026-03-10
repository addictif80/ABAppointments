<?php
/**
 * WebPanel - Stripe Payment Manager (API directe sans SDK)
 */
class StripeManager {
    private $secretKey;
    private $publicKey;

    public function __construct() {
        $this->secretKey = wp_setting('stripe_secret_key');
        $this->publicKey = wp_setting('stripe_public_key');
    }

    private function request($method, $endpoint, $data = []) {
        $ch = curl_init();
        $url = "https://api.stripe.com/v1/$endpoint";

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$this->secretKey}",
                'Content-Type: application/x-www-form-urlencoded'
            ],
            CURLOPT_TIMEOUT => 30
        ];

        switch (strtoupper($method)) {
            case 'GET':
                if ($data) $url .= '?' . http_build_query($data);
                break;
            case 'POST':
                $opts[CURLOPT_POST] = true;
                $opts[CURLOPT_POSTFIELDS] = http_build_query($data);
                break;
            case 'DELETE':
                $opts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                break;
        }

        $opts[CURLOPT_URL] = $url;
        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) throw new Exception("Stripe API: $error");

        $decoded = json_decode($response, true);
        if (isset($decoded['error'])) {
            throw new Exception("Stripe: " . ($decoded['error']['message'] ?? 'Unknown error'));
        }

        return $decoded;
    }

    // Customers
    public function createCustomer($email, $name, $metadata = []) {
        return $this->request('POST', 'customers', [
            'email' => $email,
            'name' => $name,
            'metadata' => $metadata
        ]);
    }

    public function getCustomer($customerId) {
        return $this->request('GET', "customers/$customerId");
    }

    // Payment Intents
    public function createPaymentIntent($amount, $currency = 'eur', $customerId = null, $metadata = []) {
        $data = [
            'amount' => (int)($amount * 100), // cents
            'currency' => strtolower($currency),
            'payment_method_types' => ['card'],
            'metadata' => $metadata
        ];
        if ($customerId) $data['customer'] = $customerId;
        return $this->request('POST', 'payment_intents', $data);
    }

    public function getPaymentIntent($paymentIntentId) {
        return $this->request('GET', "payment_intents/$paymentIntentId");
    }

    // Checkout Sessions
    public function createCheckoutSession($lineItems, $customerId, $successUrl, $cancelUrl, $metadata = []) {
        $data = [
            'mode' => 'payment',
            'customer' => $customerId,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => $metadata
        ];

        foreach ($lineItems as $i => $item) {
            $data["line_items[$i][price_data][currency]"] = strtolower($item['currency'] ?? 'eur');
            $data["line_items[$i][price_data][product_data][name]"] = $item['name'];
            $data["line_items[$i][price_data][unit_amount]"] = (int)($item['amount'] * 100);
            $data["line_items[$i][quantity]"] = $item['quantity'] ?? 1;
        }

        return $this->request('POST', 'checkout/sessions', $data);
    }

    public function getCheckoutSession($sessionId) {
        return $this->request('GET', "checkout/sessions/$sessionId");
    }

    // Refunds
    public function createRefund($paymentIntentId, $amount = null) {
        $data = ['payment_intent' => $paymentIntentId];
        if ($amount) $data['amount'] = (int)($amount * 100);
        return $this->request('POST', 'refunds', $data);
    }

    // Webhook signature verification
    public function verifyWebhookSignature($payload, $sigHeader, $secret) {
        $parts = [];
        foreach (explode(',', $sigHeader) as $item) {
            [$key, $value] = explode('=', $item, 2);
            $parts[trim($key)] = trim($value);
        }

        $timestamp = $parts['t'] ?? '';
        $signature = $parts['v1'] ?? '';

        if (abs(time() - $timestamp) > 300) return false; // 5 min tolerance

        $signedPayload = "$timestamp.$payload";
        $expectedSig = hash_hmac('sha256', $signedPayload, $secret);

        return hash_equals($expectedSig, $signature);
    }

    public function getPublicKey() {
        return $this->publicKey;
    }

    public function isConfigured() {
        return !empty($this->secretKey) && !empty($this->publicKey);
    }
}
