<?php
$db = Database::getInstance();
$providerId = Auth::isAdmin() ? null : Auth::userId();

// Stats
$todayWhere = $providerId ? "AND a.provider_id = $providerId" : "";
$todayCount = $db->fetchOne("SELECT COUNT(*) as cnt FROM ab_appointments a WHERE DATE(start_datetime) = CURDATE() AND status NOT IN ('cancelled') $todayWhere")['cnt'];
$weekCount = $db->fetchOne("SELECT COUNT(*) as cnt FROM ab_appointments a WHERE YEARWEEK(start_datetime, 1) = YEARWEEK(CURDATE(), 1) AND status NOT IN ('cancelled') $todayWhere")['cnt'];
$monthCount = $db->fetchOne("SELECT COUNT(*) as cnt FROM ab_appointments a WHERE MONTH(start_datetime) = MONTH(CURDATE()) AND YEAR(start_datetime) = YEAR(CURDATE()) AND status NOT IN ('cancelled') $todayWhere")['cnt'];
$pendingCount = $db->fetchOne("SELECT COUNT(*) as cnt FROM ab_appointments a WHERE status = 'pending' $todayWhere")['cnt'];
$pendingDeposits = $db->fetchOne("SELECT COUNT(*) as cnt FROM ab_deposits WHERE status = 'pending'")['cnt'];

// Today's appointments
$todaySql = "SELECT a.*, c.first_name as cf, c.last_name as cl, c.phone as cp, s.name as sn, s.color as sc, s.price as sp
             FROM ab_appointments a
             JOIN ab_customers c ON a.customer_id = c.id
             JOIN ab_services s ON a.service_id = s.id
             WHERE DATE(a.start_datetime) = CURDATE() AND a.status NOT IN ('cancelled')";
if ($providerId) $todaySql .= " AND a.provider_id = $providerId";
$todaySql .= " ORDER BY a.start_datetime";
$todayAppts = $db->fetchAll($todaySql);

// Upcoming appointments
$upcomingSql = "SELECT a.*, c.first_name as cf, c.last_name as cl, s.name as sn, s.color as sc
                FROM ab_appointments a
                JOIN ab_customers c ON a.customer_id = c.id
                JOIN ab_services s ON a.service_id = s.id
                WHERE a.start_datetime > NOW() AND a.status NOT IN ('cancelled')";
if ($providerId) $upcomingSql .= " AND a.provider_id = $providerId";
$upcomingSql .= " ORDER BY a.start_datetime LIMIT 10";
$upcoming = $db->fetchAll($upcomingSql);

// Monthly revenue
$revenueSql = "SELECT COALESCE(SUM(s.price), 0) as total FROM ab_appointments a JOIN ab_services s ON a.service_id = s.id
               WHERE MONTH(a.start_datetime) = MONTH(CURDATE()) AND YEAR(a.start_datetime) = YEAR(CURDATE()) AND a.status IN ('confirmed','completed')";
if ($providerId) $revenueSql .= " AND a.provider_id = $providerId";
$monthRevenue = $db->fetchOne($revenueSql)['total'];
?>

<?php $adminAnnouncement = ab_setting('admin_announcement'); ?>
<?php if (!empty($adminAnnouncement)): ?>
<div class="alert alert-info mb-4">
    <i class="bi bi-megaphone-fill"></i> <?= nl2br(ab_escape($adminAnnouncement)) ?>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card stat-card p-3">
            <div class="text-muted small">Aujourd'hui</div>
            <div class="h3 mb-0"><?= $todayCount ?></div>
            <div class="text-muted small">rendez-vous</div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card stat-card p-3">
            <div class="text-muted small">Cette semaine</div>
            <div class="h3 mb-0"><?= $weekCount ?></div>
            <div class="text-muted small">rendez-vous</div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card stat-card p-3">
            <div class="text-muted small">Ce mois</div>
            <div class="h3 mb-0"><?= $monthCount ?></div>
            <div class="text-muted small"><?= ab_format_price($monthRevenue) ?> CA</div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card stat-card p-3" style="border-left-color: #ffc107;">
            <div class="text-muted small">En attente</div>
            <div class="h3 mb-0"><?= $pendingCount ?></div>
            <div class="text-muted small"><?= $pendingDeposits ?> acompte(s)</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-calendar-day"></i> Rendez-vous du jour</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($todayAppts)): ?>
                    <p class="text-muted text-center py-4">Aucun rendez-vous aujourd'hui</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Heure</th><th>Client</th><th>Prestation</th><th>Statut</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($todayAppts as $a): ?>
                        <tr>
                            <td><strong><?= ab_format_time($a['start_datetime']) ?></strong></td>
                            <td><?= ab_escape($a['cf'] . ' ' . $a['cl']) ?><br><small class="text-muted"><?= ab_escape($a['cp']) ?></small></td>
                            <td><span class="badge" style="background:<?= ab_escape($a['sc']) ?>"><?= ab_escape($a['sn']) ?></span></td>
                            <td><span class="badge badge-<?= $a['status'] ?>"><?= $a['status'] ?></span></td>
                            <td><a href="<?= ab_url('admin/index.php?page=appointments&action=view&id=' . $a['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
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
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Prochains rendez-vous</h5>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($upcoming as $u): ?>
                <a href="<?= ab_url('admin/index.php?page=appointments&action=view&id=' . $u['id']) ?>" class="list-group-item list-group-item-action">
                    <div class="d-flex justify-content-between">
                        <strong><?= ab_escape($u['cf'] . ' ' . $u['cl']) ?></strong>
                        <span class="badge badge-<?= $u['status'] ?>"><?= $u['status'] ?></span>
                    </div>
                    <small class="text-muted">
                        <i class="bi bi-calendar"></i> <?= ab_format_date($u['start_datetime']) ?> à <?= ab_format_time($u['start_datetime']) ?>
                        - <span style="color:<?= ab_escape($u['sc']) ?>"><?= ab_escape($u['sn']) ?></span>
                    </small>
                </a>
                <?php endforeach; ?>
                <?php if (empty($upcoming)): ?>
                <div class="list-group-item text-muted text-center py-3">Aucun rendez-vous à venir</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
