<?php
$pageTitle = 'Detail Client';
$db = Database::getInstance();
$clientId = (int)($_GET['id'] ?? 0);

$client = $db->fetchOne("SELECT * FROM wp_users WHERE id = ? AND role = 'client'", [$clientId]);
if (!$client) { wp_flash('error', 'Client introuvable.'); wp_redirect(wp_url('admin/?page=clients')); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update';
    if ($action === 'update') {
        $db->update('wp_users', [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'company' => trim($_POST['company'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'city' => trim($_POST['city'] ?? ''),
            'postal_code' => trim($_POST['postal_code'] ?? ''),
            'country' => trim($_POST['country'] ?? 'France'),
            'notes' => trim($_POST['notes'] ?? '')
        ], 'id = ?', [$clientId]);
        wp_flash('success', 'Client mis a jour.');
    } elseif ($action === 'add_note') {
        $db->update('wp_users', ['notes' => trim($_POST['notes'] ?? '')], 'id = ?', [$clientId]);
        wp_flash('success', 'Notes mises a jour.');
    } elseif ($action === 'add_credit') {
        $creditAmount = (float)($_POST['credit_amount'] ?? 0);
        $creditDescription = trim($_POST['credit_description'] ?? '');
        $creditType = $_POST['credit_type'] ?? 'credit';
        if ($creditAmount <= 0) {
            wp_flash('error', 'Le montant doit etre positif.');
        } else {
            if ($creditType === 'credit') {
                $db->query("UPDATE wp_users SET credit_balance = credit_balance + ? WHERE id = ?", [$creditAmount, $clientId]);
            } else {
                $currentBalance = (float)$db->fetchColumn("SELECT credit_balance FROM wp_users WHERE id = ?", [$clientId]);
                if ($creditAmount > $currentBalance) {
                    wp_flash('error', 'Le montant depasse le solde actuel (' . wp_format_price($currentBalance) . ').');
                    wp_redirect(wp_url("admin/?page=client-detail&id=$clientId"));
                    return;
                }
                $db->query("UPDATE wp_users SET credit_balance = credit_balance - ? WHERE id = ?", [$creditAmount, $clientId]);
            }
            $db->insert('wp_credit_transactions', [
                'user_id' => $clientId,
                'amount' => $creditAmount,
                'type' => $creditType,
                'source' => 'manual',
                'description' => $creditDescription ?: ($creditType === 'credit' ? 'Avoir admin' : 'Debit admin'),
            ]);
            wp_flash('success', ($creditType === 'credit' ? 'Avoir' : 'Debit') . ' de ' . wp_format_price($creditAmount) . ' applique.');
            wp_log_activity('admin_credit_' . $creditType, 'user', $clientId, ['amount' => $creditAmount]);
        }
        wp_redirect(wp_url("admin/?page=client-detail&id=$clientId"));
    }
    wp_redirect(wp_url("admin/?page=client-detail&id=$clientId"));
}

$subscriptions = $db->fetchAll(
    "SELECT s.*, p.name as product_name, p.type FROM wp_subscriptions s JOIN wp_products p ON s.product_id = p.id WHERE s.user_id = ? ORDER BY s.created_at DESC",
    [$clientId]
);
$invoices = $db->fetchAll("SELECT * FROM wp_invoices WHERE user_id = ? ORDER BY created_at DESC LIMIT 10", [$clientId]);
$tickets = $db->fetchAll("SELECT * FROM wp_tickets WHERE user_id = ? ORDER BY created_at DESC LIMIT 10", [$clientId]);
$activities = $db->fetchAll("SELECT * FROM wp_activity_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 20", [$clientId]);
$totalPaid = $db->fetchColumn("SELECT COALESCE(SUM(amount),0) FROM wp_payments WHERE user_id = ? AND status = 'completed'", [$clientId]);
$pageTitle = $client['first_name'] . ' ' . $client['last_name'];
?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-body text-center">
                <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3" style="width:64px;height:64px">
                    <span class="fs-4 fw-bold text-primary"><?= strtoupper(substr($client['first_name'], 0, 1) . substr($client['last_name'], 0, 1)) ?></span>
                </div>
                <h5><?= wp_escape($client['first_name'] . ' ' . $client['last_name']) ?></h5>
                <p class="text-muted"><?= wp_escape($client['email']) ?></p>
                <span class="badge bg-<?= $client['status'] === 'active' ? 'success' : 'warning' ?>"><?= $client['status'] ?></span>
            </div>
            <ul class="list-group list-group-flush">
                <?php if ($client['company']): ?><li class="list-group-item"><i class="bi bi-building me-2"></i> <?= wp_escape($client['company']) ?></li><?php endif; ?>
                <?php if ($client['phone']): ?><li class="list-group-item"><i class="bi bi-phone me-2"></i> <?= wp_escape($client['phone']) ?></li><?php endif; ?>
                <li class="list-group-item"><i class="bi bi-calendar me-2"></i> Inscrit le <?= wp_format_date($client['created_at']) ?></li>
                <li class="list-group-item"><i class="bi bi-wallet2 me-2"></i> Total depense : <?= wp_format_price($totalPaid) ?></li>
                <?php if ($client['last_login']): ?><li class="list-group-item"><i class="bi bi-clock me-2"></i> Derniere co. <?= wp_format_datetime($client['last_login']) ?></li><?php endif; ?>
            </ul>
        </div>

        <!-- Notes -->
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Notes internes</h6></div>
            <div class="card-body">
                <form method="POST">
                    <?= wp_csrf_field() ?>
                    <input type="hidden" name="action" value="add_note">
                    <textarea name="notes" class="form-control mb-2" rows="3"><?= wp_escape($client['notes']) ?></textarea>
                    <button type="submit" class="btn btn-sm btn-primary">Sauvegarder</button>
                </form>
            </div>
        </div>

        <!-- Credit Management -->
        <div class="card mt-3">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-wallet2 me-2"></i>Porte-monnaie</h6></div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <div class="fs-4 fw-bold text-success"><?= wp_format_price($client['credit_balance'] ?? 0) ?></div>
                    <small class="text-muted">Solde actuel</small>
                </div>
                <form method="POST">
                    <?= wp_csrf_field() ?>
                    <input type="hidden" name="action" value="add_credit">
                    <div class="mb-2">
                        <select name="credit_type" class="form-select form-select-sm">
                            <option value="credit">Avoir (crediter)</option>
                            <option value="debit">Debit (debiter)</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <div class="input-group input-group-sm">
                            <input type="number" name="credit_amount" class="form-control" min="0.01" step="0.01" placeholder="Montant" required>
                            <span class="input-group-text">&euro;</span>
                        </div>
                    </div>
                    <div class="mb-2">
                        <input type="text" name="credit_description" class="form-control form-control-sm" placeholder="Description (optionnel)">
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary w-100">Appliquer</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-info">Infos</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-services">Services (<?= count($subscriptions) ?>)</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-invoices">Factures (<?= count($invoices) ?>)</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-tickets">Tickets (<?= count($tickets) ?>)</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-activity">Activite</a></li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab-info">
                <div class="card">
                    <div class="card-body">
                        <form method="POST">
                            <?= wp_csrf_field() ?>
                            <input type="hidden" name="action" value="update">
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">Prenom</label><input type="text" name="first_name" class="form-control" value="<?= wp_escape($client['first_name']) ?>"></div>
                                <div class="col-md-6"><label class="form-label">Nom</label><input type="text" name="last_name" class="form-control" value="<?= wp_escape($client['last_name']) ?>"></div>
                                <div class="col-md-6"><label class="form-label">Societe</label><input type="text" name="company" class="form-control" value="<?= wp_escape($client['company']) ?>"></div>
                                <div class="col-md-6"><label class="form-label">Telephone</label><input type="tel" name="phone" class="form-control" value="<?= wp_escape($client['phone']) ?>"></div>
                                <div class="col-12"><label class="form-label">Adresse</label><textarea name="address" class="form-control" rows="2"><?= wp_escape($client['address']) ?></textarea></div>
                                <div class="col-md-4"><label class="form-label">Ville</label><input type="text" name="city" class="form-control" value="<?= wp_escape($client['city']) ?>"></div>
                                <div class="col-md-4"><label class="form-label">CP</label><input type="text" name="postal_code" class="form-control" value="<?= wp_escape($client['postal_code']) ?>"></div>
                                <div class="col-md-4"><label class="form-label">Pays</label><input type="text" name="country" class="form-control" value="<?= wp_escape($client['country']) ?>"></div>
                            </div>
                            <div class="mt-3"><button type="submit" class="btn btn-primary">Enregistrer</button></div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-services">
                <div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
                    <thead><tr><th>Service</th><th>Type</th><th>Prix</th><th>Statut</th><th>Echeance</th></tr></thead>
                    <tbody>
                    <?php foreach ($subscriptions as $s): ?>
                    <tr>
                        <td><a href="<?= wp_url("admin/?page=subscription-detail&id={$s['id']}") ?>"><?= wp_escape($s['product_name']) ?></a></td>
                        <td><span class="badge bg-<?= $s['type'] === 'vps' ? 'primary' : ($s['type'] === 'hosting' ? 'success' : 'info') ?>"><?= strtoupper($s['type']) ?></span></td>
                        <td><?= wp_format_price($s['price']) ?>/<?= $s['billing_cycle'] === 'yearly' ? 'an' : 'mois' ?></td>
                        <td><span class="badge bg-<?= $s['status'] === 'active' ? 'success' : ($s['status'] === 'suspended' ? 'danger' : 'secondary') ?>"><?= $s['status'] ?></span></td>
                        <td><?= wp_format_date($s['next_due_date']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div></div>
            </div>

            <div class="tab-pane fade" id="tab-invoices">
                <div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
                    <thead><tr><th>Facture</th><th>Montant</th><th>Statut</th><th>Echeance</th></tr></thead>
                    <tbody>
                    <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td><a href="<?= wp_url("admin/?page=invoice-detail&id={$inv['id']}") ?>"><?= wp_escape($inv['invoice_number']) ?></a></td>
                        <td><?= wp_format_price($inv['total']) ?></td>
                        <td><span class="badge bg-<?= $inv['status'] === 'paid' ? 'success' : ($inv['status'] === 'overdue' ? 'danger' : 'warning') ?>"><?= $inv['status'] ?></span></td>
                        <td><?= wp_format_date($inv['due_date']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div></div>
            </div>

            <div class="tab-pane fade" id="tab-tickets">
                <div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
                    <thead><tr><th>#</th><th>Sujet</th><th>Statut</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($tickets as $t): ?>
                    <tr>
                        <td><a href="<?= wp_url("admin/?page=ticket-detail&id={$t['id']}") ?>"><?= wp_escape($t['ticket_number']) ?></a></td>
                        <td><?= wp_escape($t['subject']) ?></td>
                        <td><span class="badge bg-<?= $t['status'] === 'open' ? 'primary' : ($t['status'] === 'closed' ? 'dark' : 'info') ?>"><?= $t['status'] ?></span></td>
                        <td><?= wp_format_datetime($t['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div></div>
            </div>

            <div class="tab-pane fade" id="tab-activity">
                <div class="card"><div class="card-body">
                    <?php foreach ($activities as $a): ?>
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <div><strong><?= wp_escape($a['action']) ?></strong> <?php if ($a['entity_type']): ?><span class="text-muted">- <?= wp_escape($a['entity_type']) ?> #<?= $a['entity_id'] ?></span><?php endif; ?></div>
                        <small class="text-muted"><?= wp_format_datetime($a['created_at']) ?></small>
                    </div>
                    <?php endforeach; ?>
                </div></div>
            </div>
        </div>
    </div>
</div>
