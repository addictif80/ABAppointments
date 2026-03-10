<?php
$pageTitle = 'Tableau de bord';
$db = Database::getInstance();
$userId = $_SESSION['user_id'];

// Stats
$activeServices = $db->count('wp_subscriptions', "user_id = ? AND status = 'active'", [$userId]);
$pendingInvoicesCount = $db->count('wp_invoices', "user_id = ? AND status IN ('pending','overdue')", [$userId]);
$openTicketsCount = $db->count('wp_tickets', "user_id = ? AND status NOT IN ('resolved','closed')", [$userId]);
$totalSpent = $db->fetchColumn("SELECT COALESCE(SUM(amount),0) FROM wp_payments WHERE user_id = ? AND status = 'completed'", [$userId]);

// Recent services
$services = $db->fetchAll(
    "SELECT s.*, p.name as product_name, p.type as product_type
     FROM wp_subscriptions s JOIN wp_products p ON s.product_id = p.id
     WHERE s.user_id = ? ORDER BY s.created_at DESC LIMIT 5",
    [$userId]
);

// Recent invoices
$invoices = $db->fetchAll(
    "SELECT * FROM wp_invoices WHERE user_id = ? ORDER BY created_at DESC LIMIT 5",
    [$userId]
);
?>

<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex align-items-center">
                <div class="icon bg-primary bg-opacity-10 text-primary me-3"><i class="bi bi-hdd-stack"></i></div>
                <div>
                    <div class="text-muted small">Services actifs</div>
                    <div class="fw-bold fs-4"><?= $activeServices ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex align-items-center">
                <div class="icon bg-warning bg-opacity-10 text-warning me-3"><i class="bi bi-receipt"></i></div>
                <div>
                    <div class="text-muted small">Factures en attente</div>
                    <div class="fw-bold fs-4"><?= $pendingInvoicesCount ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex align-items-center">
                <div class="icon bg-info bg-opacity-10 text-info me-3"><i class="bi bi-chat-left-text"></i></div>
                <div>
                    <div class="text-muted small">Tickets ouverts</div>
                    <div class="fw-bold fs-4"><?= $openTicketsCount ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex align-items-center">
                <div class="icon bg-success bg-opacity-10 text-success me-3"><i class="bi bi-wallet2"></i></div>
                <div>
                    <div class="text-muted small">Total depense</div>
                    <div class="fw-bold fs-4"><?= wp_format_price($totalSpent) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Mes Services</h6>
                <a href="<?= wp_url('client/?page=services') ?>" class="btn btn-sm btn-primary">Commander</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($services)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-box fs-1"></i>
                        <p class="mt-2">Aucun service actif</p>
                        <a href="<?= wp_url('client/?page=services') ?>" class="btn btn-primary btn-sm">Decouvrir nos offres</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Service</th><th>Type</th><th>Statut</th><th>Prochaine echeance</th></tr></thead>
                            <tbody>
                            <?php foreach ($services as $s): ?>
                                <?php
                                $detailPage = match($s['product_type']) {
                                    'vps' => 'vps-detail',
                                    'hosting' => 'hosting-detail',
                                    'navidrome' => 'navidrome-detail',
                                    default => 'subscriptions'
                                };
                                $typeLabels = ['vps' => 'VPS', 'hosting' => 'Hebergement', 'navidrome' => 'Navidrome'];
                                $statusBadges = ['active' => 'success', 'pending' => 'warning', 'suspended' => 'danger', 'cancelled' => 'secondary'];
                                $statusLabels = ['active' => 'Actif', 'pending' => 'En attente', 'suspended' => 'Suspendu', 'cancelled' => 'Annule', 'expired' => 'Expire'];
                                ?>
                                <tr>
                                    <td><a href="<?= wp_url("client/?page=$detailPage&id={$s['id']}") ?>"><?= wp_escape($s['product_name']) ?></a></td>
                                    <td><span class="badge bg-<?= $s['product_type'] === 'vps' ? 'primary' : ($s['product_type'] === 'hosting' ? 'success' : 'info') ?>"><?= $typeLabels[$s['product_type']] ?? $s['product_type'] ?></span></td>
                                    <td><span class="badge bg-<?= $statusBadges[$s['status']] ?? 'secondary' ?>"><?= $statusLabels[$s['status']] ?? $s['status'] ?></span></td>
                                    <td><?= wp_format_date($s['next_due_date']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Dernieres factures</h6></div>
            <div class="card-body p-0">
                <?php if (empty($invoices)): ?>
                    <div class="text-center py-4 text-muted">Aucune facture</div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($invoices as $inv): ?>
                            <?php $statusColors = ['paid' => 'success', 'pending' => 'warning', 'overdue' => 'danger', 'cancelled' => 'secondary', 'draft' => 'light']; ?>
                            <a href="<?= wp_url("client/?page=invoice-detail&id={$inv['id']}") ?>" class="list-group-item list-group-item-action d-flex justify-content-between">
                                <div>
                                    <div class="fw-semibold"><?= wp_escape($inv['invoice_number']) ?></div>
                                    <small class="text-muted"><?= wp_format_date($inv['created_at']) ?></small>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold"><?= wp_format_price($inv['total']) ?></div>
                                    <span class="badge bg-<?= $statusColors[$inv['status']] ?? 'secondary' ?>"><?= ucfirst($inv['status']) ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
