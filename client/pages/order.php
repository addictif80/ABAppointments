<?php
$pageTitle = 'Commander';
$db = Database::getInstance();
$userId = $_SESSION['user_id'];

$productId = (int)($_GET['product_id'] ?? $_POST['product_id'] ?? 0);
$product = $db->fetchOne("SELECT * FROM wp_products WHERE id = ? AND is_active = 1", [$productId]);
if (!$product) {
    wp_flash('error', 'Produit introuvable.');
    wp_redirect(wp_url('client/?page=services'));
}

// OS templates for VPS
$osTemplates = [];
if ($product['type'] === 'vps') {
    $osTemplates = $db->fetchAll("SELECT * FROM wp_os_templates WHERE is_active = 1 ORDER BY sort_order");
}

// User credit balance
$user = $db->fetchOne("SELECT credit_balance FROM wp_users WHERE id = ?", [$userId]);
$creditBalance = (float)($user['credit_balance'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $billingCycle = in_array($_POST['billing_cycle'] ?? '', ['monthly', 'yearly']) ? $_POST['billing_cycle'] : 'monthly';
    $price = $billingCycle === 'yearly' && $product['price_yearly'] ? $product['price_yearly'] : $product['price_monthly'];
    $promoCode = strtoupper(trim($_POST['promo_code'] ?? ''));
    $useCredit = isset($_POST['use_credit']) ? 1 : 0;

    $db->beginTransaction();
    try {
        // Validate Navidrome password early
        if ($product['type'] === 'navidrome') {
            $ndPassword = $_POST['navidrome_password'] ?? '';
            if (strlen($ndPassword) < 8) {
                throw new Exception('Le mot de passe Navidrome doit faire au moins 8 caracteres.');
            }
            if ($ndPassword !== ($_POST['navidrome_password_confirm'] ?? '')) {
                throw new Exception('Les mots de passe ne correspondent pas.');
            }
        }

        // Validate promo code
        $discount = 0;
        $promoCodeId = null;
        if (!empty($promoCode)) {
            $promo = $db->fetchOne("SELECT * FROM wp_promo_codes WHERE code = ? AND is_active = 1", [$promoCode]);
            if (!$promo) {
                throw new Exception('Code promo invalide.');
            }
            if ($promo['valid_from'] && strtotime($promo['valid_from']) > time()) {
                throw new Exception('Ce code promo n\'est pas encore actif.');
            }
            if ($promo['valid_to'] && strtotime($promo['valid_to']) < time()) {
                throw new Exception('Ce code promo a expire.');
            }
            if ($promo['usage_limit'] && $promo['used_count'] >= $promo['usage_limit']) {
                throw new Exception('Ce code promo a atteint sa limite d\'utilisation.');
            }
            $userUsage = $db->count('wp_promo_code_usage', 'promo_code_id = ? AND user_id = ?', [$promo['id'], $userId]);
            if ($promo['usage_limit_per_user'] && $userUsage >= $promo['usage_limit_per_user']) {
                throw new Exception('Vous avez deja utilise ce code promo.');
            }
            if ($promo['applicable_products']) {
                $applicableProducts = json_decode($promo['applicable_products'], true);
                if (!empty($applicableProducts) && !in_array($product['id'], $applicableProducts)) {
                    throw new Exception('Ce code promo ne s\'applique pas a ce produit.');
                }
            }

            $subtotalForDiscount = $price + ($product['setup_fee'] > 0 ? $product['setup_fee'] : 0);
            if ($promo['min_order_amount'] && $subtotalForDiscount < $promo['min_order_amount']) {
                throw new Exception('Le montant minimum de commande pour ce code est de ' . wp_format_price($promo['min_order_amount']) . '.');
            }

            if ($promo['type'] === 'percentage') {
                $discount = round($subtotalForDiscount * $promo['value'] / 100, 2);
                if ($promo['max_discount'] && $discount > $promo['max_discount']) {
                    $discount = $promo['max_discount'];
                }
            } else {
                $discount = min($promo['value'], $subtotalForDiscount);
            }
            $promoCodeId = $promo['id'];
        }

        // Create subscription
        $subId = $db->insert('wp_subscriptions', [
            'user_id' => $userId,
            'product_id' => $product['id'],
            'status' => 'pending',
            'billing_cycle' => $billingCycle,
            'price' => $price,
            'next_due_date' => date('Y-m-d')
        ]);

        // Create initial invoice items
        $items = [['description' => $product['name'] . ' - ' . ($billingCycle === 'yearly' ? 'Annuel' : 'Mensuel'), 'unit_price' => $price, 'quantity' => 1]];
        if ($product['setup_fee'] > 0) {
            $items[] = ['description' => "Frais d'installation - " . $product['name'], 'unit_price' => $product['setup_fee'], 'quantity' => 1];
        }
        if ($discount > 0) {
            $items[] = ['description' => "Remise code promo ($promoCode)", 'unit_price' => -$discount, 'quantity' => 1];
        }

        $invoiceId = InvoiceManager::create($userId, $subId, $items, null, $promoCodeId, $discount);

        // Apply credit balance if requested
        if ($useCredit && $creditBalance > 0) {
            $invoice = $db->fetchOne("SELECT * FROM wp_invoices WHERE id = ?", [$invoiceId]);
            $creditToApply = min($creditBalance, $invoice['total']);
            if ($creditToApply > 0) {
                $newTotal = round($invoice['total'] - $creditToApply, 2);
                $db->update('wp_invoices', [
                    'credit_applied' => $creditToApply,
                    'total' => $newTotal,
                ], 'id = ?', [$invoiceId]);

                // Debit user credit
                $db->query("UPDATE wp_users SET credit_balance = credit_balance - ? WHERE id = ?", [$creditToApply, $userId]);
                $db->insert('wp_credit_transactions', [
                    'user_id' => $userId,
                    'amount' => $creditToApply,
                    'type' => 'debit',
                    'source' => 'manual',
                    'reference_id' => $invoiceId,
                    'description' => 'Credit applique a la facture',
                ]);

                // If total is 0, mark as paid directly
                if ($newTotal <= 0) {
                    InvoiceManager::markPaid($invoiceId, 'credit');
                    $db->commit();

                    // Record promo usage
                    if ($promoCodeId) {
                        $db->query("UPDATE wp_promo_codes SET used_count = used_count + 1 WHERE id = ?", [$promoCodeId]);
                        $db->insert('wp_promo_code_usage', [
                            'promo_code_id' => $promoCodeId,
                            'user_id' => $userId,
                            'invoice_id' => $invoiceId,
                            'discount_amount' => $discount,
                        ]);
                    }

                    wp_flash('success', 'Commande payee avec votre credit ! Votre service est en cours d\'activation.');
                    wp_redirect(wp_url('client/?page=subscriptions'));
                    exit;
                }
            }
        }

        // Record promo code usage
        if ($promoCodeId) {
            $db->query("UPDATE wp_promo_codes SET used_count = used_count + 1 WHERE id = ?", [$promoCodeId]);
            $db->insert('wp_promo_code_usage', [
                'promo_code_id' => $promoCodeId,
                'user_id' => $userId,
                'invoice_id' => $invoiceId,
                'discount_amount' => $discount,
            ]);
        }

        // Store extra data for provisioning
        if ($product['type'] === 'vps') {
            $_SESSION['order_data_' . $subId] = [
                'os_template_id' => (int)($_POST['os_template_id'] ?? 1),
                'hostname' => trim($_POST['hostname'] ?? 'vps-' . $subId)
            ];
        } elseif ($product['type'] === 'hosting') {
            $_SESSION['order_data_' . $subId] = [
                'domain' => trim($_POST['domain'] ?? '')
            ];
        } elseif ($product['type'] === 'navidrome') {
            $_SESSION['order_data_' . $subId] = ['navidrome_password' => $_POST['navidrome_password']];
        }

        $db->commit();
        wp_redirect(wp_url("client/?page=invoice-pay&id=$invoiceId"));
    } catch (Exception $e) {
        $db->rollback();
        wp_flash('error', 'Erreur lors de la commande: ' . $e->getMessage());
    }
}

$typeLabels = ['vps' => 'VPS', 'hosting' => 'Hebergement', 'navidrome' => 'Navidrome'];
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Commander : <?= wp_escape($product['name']) ?></h5>
                <span class="badge bg-primary"><?= $typeLabels[$product['type']] ?? $product['type'] ?></span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= wp_csrf_field() ?>
                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">

                    <!-- Billing cycle -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Cycle de facturation</label>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-check card p-3">
                                    <input class="form-check-input" type="radio" name="billing_cycle" value="monthly" id="monthly" checked>
                                    <label class="form-check-label w-100" for="monthly">
                                        <strong>Mensuel</strong>
                                        <div class="fs-4 fw-bold mt-1"><?= wp_format_price($product['price_monthly']) ?><small class="text-muted fw-normal">/mois</small></div>
                                    </label>
                                </div>
                            </div>
                            <?php if ($product['price_yearly']): ?>
                            <div class="col-md-6">
                                <div class="form-check card p-3">
                                    <input class="form-check-input" type="radio" name="billing_cycle" value="yearly" id="yearly">
                                    <label class="form-check-label w-100" for="yearly">
                                        <strong>Annuel</strong>
                                        <?php $savings = round(($product['price_monthly'] * 12 - $product['price_yearly']) / ($product['price_monthly'] * 12) * 100); ?>
                                        <?php if ($savings > 0): ?><span class="badge bg-success ms-2">-<?= $savings ?>%</span><?php endif; ?>
                                        <div class="fs-4 fw-bold mt-1"><?= wp_format_price($product['price_yearly']) ?><small class="text-muted fw-normal">/an</small></div>
                                    </label>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($product['type'] === 'vps'): ?>
                    <!-- VPS Config -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Systeme d'exploitation</label>
                        <div class="row g-2">
                            <?php foreach ($osTemplates as $i => $os): ?>
                            <div class="col-md-4">
                                <div class="form-check card p-2">
                                    <input class="form-check-input" type="radio" name="os_template_id" value="<?= $os['id'] ?>" id="os_<?= $os['id'] ?>" <?= $i === 0 ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="os_<?= $os['id'] ?>">
                                        <i class="bi bi-ubuntu"></i> <?= wp_escape($os['name']) ?>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nom d'hote</label>
                        <input type="text" name="hostname" class="form-control" placeholder="mon-serveur" pattern="[a-zA-Z0-9\-]+" value="vps-<?= $userId ?>">
                        <div class="form-text">Lettres, chiffres et tirets uniquement</div>
                    </div>
                    <?php endif; ?>

                    <?php if ($product['type'] === 'hosting'): ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nom de domaine</label>
                        <input type="text" name="domain" class="form-control" placeholder="monsite.fr" required>
                        <div class="form-text">Le domaine doit pointer vers nos serveurs (NS seront fournis apres commande)</div>
                    </div>
                    <?php endif; ?>

                    <?php if ($product['type'] === 'navidrome'): ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Mot de passe Navidrome</label>
                        <input type="password" name="navidrome_password" class="form-control" minlength="8" required placeholder="Minimum 8 caracteres">
                        <div class="form-text">Ce mot de passe sera utilise pour vous connecter a Navidrome</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Confirmer le mot de passe</label>
                        <input type="password" name="navidrome_password_confirm" class="form-control" minlength="8" required>
                    </div>
                    <?php endif; ?>

                    <!-- Promo Code -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Code promo</label>
                        <div class="input-group">
                            <input type="text" name="promo_code" id="promoCodeInput" class="form-control font-monospace text-uppercase" placeholder="Entrez un code promo">
                            <button type="button" class="btn btn-outline-secondary" onclick="validatePromo()">Appliquer</button>
                        </div>
                        <div id="promoFeedback" class="form-text"></div>
                    </div>

                    <!-- Credit Balance -->
                    <?php if ($creditBalance > 0): ?>
                    <div class="mb-3">
                        <div class="form-check card p-3 border-success">
                            <input class="form-check-input" type="checkbox" name="use_credit" id="useCredit" value="1">
                            <label class="form-check-label" for="useCredit">
                                Utiliser mon credit : <strong class="text-success"><?= wp_format_price($creditBalance) ?></strong>
                            </label>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Summary -->
                    <div class="card bg-light mb-3">
                        <div class="card-body">
                            <h6>Recapitulatif</h6>
                            <div class="d-flex justify-content-between">
                                <span><?= wp_escape($product['name']) ?></span>
                                <span id="price-display"><?= wp_format_price($product['price_monthly']) ?></span>
                            </div>
                            <?php if ($product['setup_fee'] > 0): ?>
                            <div class="d-flex justify-content-between">
                                <span>Frais d'installation</span>
                                <span><?= wp_format_price($product['setup_fee']) ?></span>
                            </div>
                            <?php endif; ?>
                            <div id="discountRow" class="d-flex justify-content-between text-success" style="display:none !important">
                                <span id="discountLabel">Remise</span>
                                <span id="discountDisplay"></span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between fw-bold">
                                <span>Total TTC</span>
                                <span id="total-display"><?= wp_format_price($product['price_monthly'] + $product['setup_fee']) ?></span>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i class="bi bi-credit-card me-2"></i> Proceder au paiement
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const prices = {
    monthly: <?= (float)$product['price_monthly'] ?>,
    yearly: <?= (float)($product['price_yearly'] ?: $product['price_monthly']) ?>,
    setup: <?= (float)$product['setup_fee'] ?>,
    taxRate: <?= (float)wp_setting('tax_rate', 20) ?>
};
let currentDiscount = 0;

function formatPrice(amount) {
    return amount.toFixed(2).replace('.', ',') + ' €';
}

function getSelectedPrice() {
    const cycle = document.querySelector('input[name="billing_cycle"]:checked');
    return cycle && cycle.value === 'yearly' ? prices.yearly : prices.monthly;
}

function updateTotal() {
    const price = getSelectedPrice();
    const subtotal = price + prices.setup;
    const discount = currentDiscount;
    const afterDiscount = Math.max(0, subtotal - discount);
    const tax = afterDiscount * prices.taxRate / 100;
    const total = afterDiscount + tax;

    document.getElementById('price-display').textContent = formatPrice(price);
    document.getElementById('total-display').textContent = formatPrice(total);
}

function validatePromo() {
    const code = document.getElementById('promoCodeInput').value.trim();
    const feedback = document.getElementById('promoFeedback');
    const discountRow = document.getElementById('discountRow');
    if (!code) {
        feedback.innerHTML = '';
        discountRow.style.cssText = 'display:none !important';
        currentDiscount = 0;
        updateTotal();
        return;
    }

    fetch('<?= wp_url('api/') ?>?action=validate-promo&code=' + encodeURIComponent(code) + '&product_id=<?= $product['id'] ?>')
        .then(r => r.json())
        .then(data => {
            if (data.valid) {
                const price = getSelectedPrice();
                const subtotal = price + prices.setup;
                if (data.type === 'percentage') {
                    currentDiscount = Math.round(subtotal * data.value) / 100;
                } else {
                    currentDiscount = Math.min(data.value, subtotal);
                }
                feedback.innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> ' + data.message + '</span>';
                discountRow.style.cssText = '';
                document.getElementById('discountLabel').textContent = 'Remise (' + code.toUpperCase() + ')';
                document.getElementById('discountDisplay').textContent = '-' + formatPrice(currentDiscount);
                updateTotal();
            } else {
                feedback.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle"></i> ' + data.message + '</span>';
                discountRow.style.cssText = 'display:none !important';
                currentDiscount = 0;
                updateTotal();
            }
        })
        .catch(() => { feedback.innerHTML = '<span class="text-danger">Erreur de verification.</span>'; });
}

// Recalculate when billing cycle changes
document.querySelectorAll('input[name="billing_cycle"]').forEach(r => r.addEventListener('change', updateTotal));
</script>
