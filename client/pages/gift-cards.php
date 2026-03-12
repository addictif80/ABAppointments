<?php
$pageTitle = 'Mon Porte-monnaie';
$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$user = Auth::user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'redeem') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        if (empty($code)) {
            wp_flash('error', 'Veuillez entrer un code.');
        } else {
            $card = $db->fetchOne("SELECT * FROM wp_gift_cards WHERE code = ?", [$code]);
            if (!$card) {
                wp_flash('error', 'Code de carte cadeau invalide.');
            } elseif ($card['status'] !== 'active') {
                wp_flash('error', 'Cette carte cadeau n\'est plus valide.');
            } elseif ($card['balance'] <= 0) {
                wp_flash('error', 'Cette carte cadeau a deja ete entierement utilisee.');
            } elseif ($card['expires_at'] && strtotime($card['expires_at']) < time()) {
                wp_flash('error', 'Cette carte cadeau a expire.');
            } elseif ($card['redeemed_by_user_id'] && $card['redeemed_by_user_id'] != $userId) {
                wp_flash('error', 'Cette carte cadeau a deja ete utilisee par un autre compte.');
            } else {
                $db->beginTransaction();
                try {
                    // Credit the user
                    $db->query("UPDATE wp_users SET credit_balance = credit_balance + ? WHERE id = ?", [$card['balance'], $userId]);

                    // Record transaction
                    $db->insert('wp_credit_transactions', [
                        'user_id' => $userId,
                        'amount' => $card['balance'],
                        'type' => 'credit',
                        'source' => 'gift_card',
                        'reference_id' => $card['id'],
                        'description' => 'Carte cadeau ' . $card['code'],
                    ]);

                    // Mark card as used
                    $db->update('wp_gift_cards', [
                        'balance' => 0,
                        'status' => 'used',
                        'redeemed_by_user_id' => $userId,
                        'redeemed_at' => date('Y-m-d H:i:s'),
                    ], 'id = ?', [$card['id']]);

                    $db->commit();
                    wp_flash('success', 'Carte cadeau de ' . wp_format_price($card['balance']) . ' ajoutee a votre credit !');
                    wp_log_activity('gift_card_redeemed', 'gift_card', $card['id'], ['amount' => $card['balance']]);
                } catch (Exception $e) {
                    $db->rollback();
                    wp_flash('error', 'Erreur lors de l\'utilisation de la carte cadeau.');
                }
            }
        }
        wp_redirect(wp_url('client/?page=gift-cards'));
    }

    if ($action === 'topup') {
        $amount = (float)($_POST['amount'] ?? 0);
        if ($amount < 5 || $amount > 1000) {
            wp_flash('error', 'Le montant doit etre entre 5€ et 1000€.');
        } else {
            $db->beginTransaction();
            try {
                $invoiceId = InvoiceManager::create($userId, null, [
                    ['description' => 'Rechargement porte-monnaie - ' . wp_format_price($amount), 'unit_price' => $amount, 'quantity' => 1]
                ], null, null, 0, true);
                // Store that this invoice is a wallet topup
                $db->update('wp_invoices', ['notes' => 'wallet_topup:' . $amount], 'id = ?', [$invoiceId]);
                $db->commit();
                wp_redirect(wp_url("client/?page=invoice-pay&id=$invoiceId"));
                exit;
            } catch (Exception $e) {
                $db->rollback();
                wp_flash('error', 'Erreur: ' . $e->getMessage());
            }
        }
        wp_redirect(wp_url('client/?page=gift-cards'));
    }

    if ($action === 'buy') {
        $amount = (float)($_POST['amount'] ?? 0);
        $recipientEmail = trim($_POST['recipient_email'] ?? '');
        $recipientName = trim($_POST['recipient_name'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if ($amount < 5 || $amount > 500) {
            wp_flash('error', 'Le montant doit etre entre 5€ et 500€.');
        } elseif (empty($recipientEmail) || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            wp_flash('error', 'Veuillez entrer un email destinataire valide.');
        } else {
            $db->beginTransaction();
            try {
                $code = 'GC-' . strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(4)));
                $cardId = $db->insert('wp_gift_cards', [
                    'code' => $code,
                    'initial_amount' => $amount,
                    'balance' => $amount,
                    'purchaser_user_id' => $userId,
                    'recipient_email' => $recipientEmail,
                    'recipient_name' => $recipientName ?: null,
                    'message' => $message ?: null,
                    'expires_at' => date('Y-m-d', strtotime('+1 year')),
                ]);

                // Create invoice for the gift card purchase
                $invoiceId = InvoiceManager::create($userId, null, [
                    ['description' => 'Carte cadeau - ' . wp_format_price($amount), 'unit_price' => $amount, 'quantity' => 1]
                ]);

                $db->commit();
                wp_flash('success', 'Carte cadeau creee ! Procedez au paiement.');
                wp_redirect(wp_url("client/?page=invoice-pay&id=$invoiceId"));
                exit;
            } catch (Exception $e) {
                $db->rollback();
                wp_flash('error', 'Erreur lors de la creation de la carte cadeau.');
            }
        }
        wp_redirect(wp_url('client/?page=gift-cards'));
    }
}

// Refresh user data
$user = $db->fetchOne("SELECT * FROM wp_users WHERE id = ?", [$userId]);
$creditBalance = (float)($user['credit_balance'] ?? 0);

// Transaction history
$transactions = $db->fetchAll("SELECT * FROM wp_credit_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20", [$userId]);

// Gift cards purchased by user
$purchasedCards = $db->fetchAll("SELECT * FROM wp_gift_cards WHERE purchaser_user_id = ? ORDER BY created_at DESC", [$userId]);
?>

<div class="row g-4">
    <!-- Credit Balance -->
    <div class="col-lg-4">
        <div class="card border-success">
            <div class="card-body text-center py-4">
                <i class="bi bi-wallet2 fs-1 text-success"></i>
                <h6 class="mt-2 text-muted">Mon credit</h6>
                <div class="fs-2 fw-bold text-success"><?= wp_format_price($creditBalance) ?></div>
                <p class="text-muted small mb-0">Utilisable sur vos prochaines commandes</p>
            </div>
        </div>

        <!-- Top up wallet -->
        <div class="card mt-3">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Recharger mon porte-monnaie</h6></div>
            <div class="card-body">
                <form method="POST" class="row g-2 align-items-end">
                    <?= wp_csrf_field() ?>
                    <input type="hidden" name="action" value="topup">
                    <div class="col-auto">
                        <label class="form-label">Montant</label>
                        <div class="input-group">
                            <input type="number" name="amount" class="form-control" min="5" max="1000" step="5" value="20" required>
                            <span class="input-group-text">&euro;</span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-success"><i class="bi bi-credit-card me-1"></i> Recharger par carte</button>
                    </div>
                </form>
                <div class="form-text mt-1">Minimum 5&euro;, maximum 1000&euro;. Le solde est utilisable sur vos prochaines commandes.</div>
            </div>
        </div>

        <!-- Redeem card -->
        <div class="card mt-3">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-gift me-2"></i>Utiliser une carte cadeau</h6></div>
            <div class="card-body">
                <form method="POST">
                    <?= wp_csrf_field() ?>
                    <input type="hidden" name="action" value="redeem">
                    <div class="mb-3">
                        <input type="text" name="code" class="form-control font-monospace text-uppercase" placeholder="GC-XXXXXXXX-XXXXXXXX" required>
                    </div>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-check-lg me-1"></i> Valider
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Buy & History -->
    <div class="col-lg-8">
        <!-- Buy gift card -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-gift-fill me-2"></i>Offrir une carte cadeau</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= wp_csrf_field() ?>
                    <input type="hidden" name="action" value="buy">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Montant *</label>
                            <div class="input-group">
                                <input type="number" name="amount" class="form-control" min="5" max="500" step="5" value="25" required>
                                <span class="input-group-text">€</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email destinataire *</label>
                            <input type="email" name="recipient_email" class="form-control" required placeholder="ami@email.com">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Nom destinataire</label>
                            <input type="text" name="recipient_name" class="form-control" placeholder="Optionnel">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Message</label>
                            <textarea name="message" class="form-control" rows="2" placeholder="Un petit message pour accompagner votre cadeau..."></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-credit-card me-1"></i> Acheter la carte cadeau
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Purchased cards -->
        <?php if (!empty($purchasedCards)): ?>
        <div class="card mb-3">
            <div class="card-header"><h6 class="mb-0">Mes cartes cadeau offertes</h6></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Code</th><th>Montant</th><th>Destinataire</th><th>Statut</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($purchasedCards as $pc): ?>
                        <tr>
                            <td><code><?= wp_escape($pc['code']) ?></code></td>
                            <td><?= wp_format_price($pc['initial_amount']) ?></td>
                            <td><?= wp_escape($pc['recipient_name'] ?: $pc['recipient_email']) ?></td>
                            <td>
                                <?php
                                $sColors = ['active' => 'success', 'used' => 'secondary', 'expired' => 'warning', 'cancelled' => 'danger'];
                                $sLabels = ['active' => 'Active', 'used' => 'Utilisee', 'expired' => 'Expiree', 'cancelled' => 'Annulee'];
                                ?>
                                <span class="badge bg-<?= $sColors[$pc['status']] ?? 'secondary' ?>"><?= $sLabels[$pc['status']] ?? $pc['status'] ?></span>
                            </td>
                            <td><?= wp_format_date($pc['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Credit history -->
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Historique des credits</h6></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Date</th><th>Description</th><th>Montant</th></tr></thead>
                    <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">Aucune transaction</td></tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $tx): ?>
                        <tr>
                            <td><?= wp_format_datetime($tx['created_at']) ?></td>
                            <td><?= wp_escape($tx['description']) ?></td>
                            <td>
                                <?php if ($tx['type'] === 'credit'): ?>
                                    <span class="text-success fw-bold">+<?= wp_format_price($tx['amount']) ?></span>
                                <?php else: ?>
                                    <span class="text-danger fw-bold">-<?= wp_format_price($tx['amount']) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
