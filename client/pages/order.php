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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $billingCycle = in_array($_POST['billing_cycle'] ?? '', ['monthly', 'yearly']) ? $_POST['billing_cycle'] : 'monthly';
    $price = $billingCycle === 'yearly' && $product['price_yearly'] ? $product['price_yearly'] : $product['price_monthly'];

    $db->beginTransaction();
    try {
        // Create subscription
        $subId = $db->insert('wp_subscriptions', [
            'user_id' => $userId,
            'product_id' => $product['id'],
            'status' => 'pending',
            'billing_cycle' => $billingCycle,
            'price' => $price,
            'next_due_date' => date('Y-m-d')
        ]);

        // Create initial invoice
        $items = [['description' => $product['name'] . ' - ' . ($billingCycle === 'yearly' ? 'Annuel' : 'Mensuel'), 'unit_price' => $price, 'quantity' => 1]];
        if ($product['setup_fee'] > 0) {
            $items[] = ['description' => "Frais d'installation - " . $product['name'], 'unit_price' => $product['setup_fee'], 'quantity' => 1];
        }

        $invoiceId = InvoiceManager::create($userId, $subId, $items);

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
