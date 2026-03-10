<?php
$pageTitle = 'Dashboard';
$db = Database::getInstance();

$totalClients = $db->count('wp_users', "role = 'client'");
$activeSubscriptions = $db->count('wp_subscriptions', "status = 'active'");
$monthlyRevenue = $db->fetchColumn("SELECT COALESCE(SUM(amount),0) FROM wp_payments WHERE status = 'completed' AND created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')");
$overdueInvoices = $db->count('wp_invoices', "status = 'overdue'");
$openTickets = $db->count('wp_tickets', "status NOT IN ('resolved','closed')");
$suspendedServices = $db->count('wp_subscriptions', "status = 'suspended'");

// Service breakdown
$vpsSubs = $db->fetchColumn("SELECT COUNT(*) FROM wp_subscriptions s JOIN wp_products p ON s.product_id = p.id WHERE p.type = 'vps' AND s.status = 'active'");
$hostingSubs = $db->fetchColumn("SELECT COUNT(*) FROM wp_subscriptions s JOIN wp_products p ON s.product_id = p.id WHERE p.type = 'hosting' AND s.status = 'active'");
$navSubs = $db->fetchColumn("SELECT COUNT(*) FROM wp_subscriptions s JOIN wp_products p ON s.product_id = p.id WHERE p.type = 'navidrome' AND s.status = 'active'");

// Recent tickets
$recentTickets = $db->fetchAll(
    "SELECT t.*, u.first_name, u.last_name FROM wp_tickets t JOIN wp_users u ON t.user_id = u.id ORDER BY t.created_at DESC LIMIT 5"
);

// Recent payments
$recentPayments = $db->fetchAll(
    "SELECT p.*, u.first_name, u.last_name, i.invoice_number FROM wp_payments p JOIN wp_users u ON p.user_id = u.id LEFT JOIN wp_invoices i ON p.invoice_id = i.id ORDER BY p.created_at DESC LIMIT 5"
);

// Overdue invoices
$overdueList = $db->fetchAll(
    "SELECT i.*, u.first_name, u.last_name, u.email FROM wp_invoices i JOIN wp_users u ON i.user_id = u.id WHERE i.status = 'overdue' ORDER BY i.due_date ASC LIMIT 10"
);
?>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card text-center">
            <div class="text-muted small">Clients</div>
            <div class="fw-bold fs-4"><?= $totalClients ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card text-center">
            <div class="text-muted small">Services actifs</div>
            <div class="fw-bold fs-4 text-success"><?= $activeSubscriptions ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card text-center">
            <div class="text-muted small">CA du mois</div>
            <div class="fw-bold fs-4 text-primary"><?= wp_format_price($monthlyRevenue) ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card text-center">
            <div class="text-muted small">Factures en retard</div>
            <div class="fw-bold fs-4 text-danger"><?= $overdueInvoices ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card text-center">
            <div class="text-muted small">Tickets ouverts</div>
            <div class="fw-bold fs-4 text-warning"><?= $openTickets ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card text-center">
            <div class="text-muted small">Suspendus</div>
            <div class="fw-bold fs-4 text-secondary"><?= $suspendedServices ?></div>
        </div>
    </div>
</div>

<!-- Service breakdown -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="d-flex align-items-center">
                <div class="me-3"><i class="bi bi-hdd-rack fs-3 text-primary"></i></div>
                <div><div class="fw-bold fs-5"><?= $vpsSubs ?></div><div class="text-muted small">VPS actifs</div></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="d-flex align-items-center">
                <div class="me-3"><i class="bi bi-globe fs-3 text-success"></i></div>
                <div><div class="fw-bold fs-5"><?= $hostingSubs ?></div><div class="text-muted small">Hebergements actifs</div></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="d-flex align-items-center">
                <div class="me-3"><i class="bi bi-music-note-beamed fs-3 text-info"></i></div>
                <div><div class="fw-bold fs-5"><?= $navSubs ?></div><div class="text-muted small">Navidrome actifs</div></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Overdue invoices -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <h6 class="mb-0 text-danger"><i class="bi bi-exclamation-circle me-1"></i> Factures en retard</h6>
                <a href="<?= wp_url('admin/?page=invoices&status=overdue') ?>" class="small">Voir tout</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($overdueList)): ?>
                    <div class="text-center py-3 text-muted">Aucune facture en retard</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead><tr><th>Facture</th><th>Client</th><th>Montant</th><th>Echeance</th></tr></thead>
                        <tbody>
                        <?php foreach ($overdueList as $inv): ?>
                        <tr>
                            <td><a href="<?= wp_url("admin/?page=invoice-detail&id={$inv['id']}") ?>"><?= wp_escape($inv['invoice_number']) ?></a></td>
                            <td><?= wp_escape($inv['first_name'] . ' ' . $inv['last_name']) ?></td>
                            <td class="fw-bold"><?= wp_format_price($inv['total']) ?></td>
                            <td class="text-danger"><?= wp_format_date($inv['due_date']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent tickets -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <h6 class="mb-0"><i class="bi bi-chat-left-text me-1"></i> Derniers tickets</h6>
                <a href="<?= wp_url('admin/?page=tickets') ?>" class="small">Voir tout</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead><tr><th>#</th><th>Client</th><th>Sujet</th><th>Statut</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentTickets as $t): ?>
                        <?php $sc = ['open' => 'primary', 'in_progress' => 'info', 'waiting_client' => 'warning', 'waiting_admin' => 'secondary', 'resolved' => 'success', 'closed' => 'dark']; ?>
                        <tr>
                            <td><a href="<?= wp_url("admin/?page=ticket-detail&id={$t['id']}") ?>"><?= wp_escape($t['ticket_number']) ?></a></td>
                            <td><?= wp_escape($t['first_name'] . ' ' . $t['last_name']) ?></td>
                            <td><?= wp_escape(mb_substr($t['subject'], 0, 40)) ?></td>
                            <td><span class="badge bg-<?= $sc[$t['status']] ?? 'secondary' ?>"><?= $t['status'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent payments -->
    <div class="col-12">
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-credit-card me-1"></i> Derniers paiements</h6></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead><tr><th>Date</th><th>Client</th><th>Facture</th><th>Montant</th><th>Methode</th><th>Statut</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentPayments as $p): ?>
                        <tr>
                            <td><?= wp_format_datetime($p['created_at']) ?></td>
                            <td><?= wp_escape($p['first_name'] . ' ' . $p['last_name']) ?></td>
                            <td><?= wp_escape($p['invoice_number'] ?? '-') ?></td>
                            <td class="fw-bold"><?= wp_format_price($p['amount']) ?></td>
                            <td><?= ucfirst($p['method']) ?></td>
                            <td><span class="badge bg-<?= $p['status'] === 'completed' ? 'success' : ($p['status'] === 'failed' ? 'danger' : 'warning') ?>"><?= $p['status'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
