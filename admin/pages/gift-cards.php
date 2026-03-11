<?php
$pageTitle = 'Cartes Cadeau';
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $cardId = (int)($_POST['card_id'] ?? 0);

    if ($action === 'create') {
        $amount = (float)($_POST['amount'] ?? 0);
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));
        $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
        $recipientEmail = trim($_POST['recipient_email'] ?? '');
        $recipientName = trim($_POST['recipient_name'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if ($amount < 1) {
            wp_flash('error', 'Le montant minimum est de 1€.');
        } else {
            for ($i = 0; $i < $quantity; $i++) {
                $code = 'GC-' . strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(4)));
                $db->insert('wp_gift_cards', [
                    'code' => $code,
                    'initial_amount' => $amount,
                    'balance' => $amount,
                    'recipient_email' => $recipientEmail ?: null,
                    'recipient_name' => $recipientName ?: null,
                    'message' => $message ?: null,
                    'expires_at' => $expiresAt,
                ]);
            }
            wp_flash('success', $quantity > 1 ? "$quantity cartes cadeau creees." : "Carte cadeau creee.");
            wp_log_activity('gift_card_created', 'gift_card', null, ['amount' => $amount, 'quantity' => $quantity]);
        }
    } elseif ($action === 'cancel' && $cardId) {
        $db->update('wp_gift_cards', ['status' => 'cancelled'], 'id = ? AND status = ?', [$cardId, 'active']);
        wp_flash('success', 'Carte cadeau annulee.');
        wp_log_activity('gift_card_cancelled', 'gift_card', $cardId);
    } elseif ($action === 'adjust' && $cardId) {
        $newBalance = (float)($_POST['new_balance'] ?? 0);
        if ($newBalance >= 0) {
            $db->update('wp_gift_cards', ['balance' => $newBalance], 'id = ?', [$cardId]);
            wp_flash('success', 'Solde mis a jour.');
        }
    }
    wp_redirect(wp_url('admin/?page=gift-cards'));
}

$status = $_GET['status'] ?? 'all';
$where = '1';
$params = [];
if ($status !== 'all') { $where = 'gc.status = ?'; $params[] = $status; }

$cards = $db->fetchAll("SELECT gc.*,
    pu.email as purchaser_email, pu.first_name as purchaser_name,
    ru.email as redeemer_email, ru.first_name as redeemer_name
    FROM wp_gift_cards gc
    LEFT JOIN wp_users pu ON gc.purchaser_user_id = pu.id
    LEFT JOIN wp_users ru ON gc.redeemed_by_user_id = ru.id
    WHERE $where ORDER BY gc.created_at DESC", $params);

$totalActive = $db->fetchColumn("SELECT COALESCE(SUM(balance), 0) FROM wp_gift_cards WHERE status = 'active'");
$totalCards = $db->count('wp_gift_cards');
?>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="text-muted small">Total cartes</div>
            <div class="fs-4 fw-bold"><?= $totalCards ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="text-muted small">Solde actif total</div>
            <div class="fs-4 fw-bold text-success"><?= wp_format_price($totalActive) ?></div>
        </div>
    </div>
    <div class="col-md-4 d-flex align-items-center justify-content-end">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#giftCardModal">
            <i class="bi bi-plus-lg me-1"></i> Creer des cartes cadeau
        </button>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <ul class="nav nav-pills">
        <li class="nav-item"><a class="nav-link <?= $status === 'all' ? 'active' : '' ?>" href="<?= wp_url('admin/?page=gift-cards') ?>">Toutes</a></li>
        <li class="nav-item"><a class="nav-link <?= $status === 'active' ? 'active' : '' ?>" href="<?= wp_url('admin/?page=gift-cards&status=active') ?>">Actives</a></li>
        <li class="nav-item"><a class="nav-link <?= $status === 'used' ? 'active' : '' ?>" href="<?= wp_url('admin/?page=gift-cards&status=used') ?>">Utilisees</a></li>
        <li class="nav-item"><a class="nav-link <?= $status === 'expired' ? 'active' : '' ?>" href="<?= wp_url('admin/?page=gift-cards&status=expired') ?>">Expirees</a></li>
        <li class="nav-item"><a class="nav-link <?= $status === 'cancelled' ? 'active' : '' ?>" href="<?= wp_url('admin/?page=gift-cards&status=cancelled') ?>">Annulees</a></li>
    </ul>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Montant</th>
                    <th>Solde</th>
                    <th>Acheteur</th>
                    <th>Destinataire</th>
                    <th>Statut</th>
                    <th>Expiration</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($cards as $gc): ?>
                <tr>
                    <td><code class="font-monospace"><?= wp_escape($gc['code']) ?></code></td>
                    <td><?= wp_format_price($gc['initial_amount']) ?></td>
                    <td>
                        <?php if ($gc['balance'] > 0): ?>
                            <span class="text-success fw-bold"><?= wp_format_price($gc['balance']) ?></span>
                        <?php else: ?>
                            <span class="text-muted">0,00 €</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($gc['purchaser_email']): ?>
                            <?= wp_escape($gc['purchaser_name']) ?><br>
                            <small class="text-muted"><?= wp_escape($gc['purchaser_email']) ?></small>
                        <?php else: ?>
                            <span class="text-muted">Admin</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($gc['redeemer_email']): ?>
                            <?= wp_escape($gc['redeemer_name']) ?><br>
                            <small class="text-muted"><?= wp_escape($gc['redeemer_email']) ?></small>
                        <?php elseif ($gc['recipient_email']): ?>
                            <?= wp_escape($gc['recipient_name'] ?: $gc['recipient_email']) ?>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $statusColors = ['active' => 'success', 'used' => 'secondary', 'expired' => 'warning', 'cancelled' => 'danger'];
                        $statusLabels = ['active' => 'Active', 'used' => 'Utilisee', 'expired' => 'Expiree', 'cancelled' => 'Annulee'];
                        ?>
                        <span class="badge bg-<?= $statusColors[$gc['status']] ?? 'secondary' ?>"><?= $statusLabels[$gc['status']] ?? $gc['status'] ?></span>
                    </td>
                    <td>
                        <?= $gc['expires_at'] ? wp_format_date($gc['expires_at']) : '<span class="text-muted">Jamais</span>' ?>
                    </td>
                    <td>
                        <?php if ($gc['status'] === 'active'): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Annuler cette carte cadeau ?')">
                                <?= wp_csrf_field() ?>
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="card_id" value="<?= $gc['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" title="Annuler"><i class="bi bi-x-lg"></i></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($cards)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Aucune carte cadeau</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Gift Card Modal -->
<div class="modal fade" id="giftCardModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?= wp_csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-header">
                <h5 class="modal-title">Creer des cartes cadeau</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Montant (€) *</label>
                        <input type="number" name="amount" class="form-control" step="0.01" min="1" required value="25">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Quantite</label>
                        <input type="number" name="quantity" class="form-control" min="1" max="100" value="1">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Date d'expiration</label>
                        <input type="date" name="expires_at" class="form-control">
                        <div class="form-text">Laisser vide = pas d'expiration</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email destinataire</label>
                        <input type="email" name="recipient_email" class="form-control" placeholder="Optionnel">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nom destinataire</label>
                        <input type="text" name="recipient_name" class="form-control" placeholder="Optionnel">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Message personnalise</label>
                        <textarea name="message" class="form-control" rows="2" placeholder="Optionnel"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary">Creer</button>
            </div>
        </form>
    </div>
</div>
