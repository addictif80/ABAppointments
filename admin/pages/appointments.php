<?php
$db = Database::getInstance();
$manager = new AppointmentManager();
$action = $_GET['action'] ?? 'list';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'update_status' && isset($_POST['id'], $_POST['status'])) {
        $appointmentId = (int)$_POST['id'];
        $manager->updateStatus($appointmentId, $_POST['status']);

        if ($_POST['status'] === 'confirmed') {
            // Sync to Google Calendar
            $gcal = new GoogleCalendar();
            if ($gcal->isConfigured()) {
                $gcal->syncAppointment($appointmentId);
            }
        }
        ab_flash('success', 'Statut mis à jour.');
        ab_redirect(ab_url('admin/index.php?page=appointments&action=view&id=' . $_POST['id']));
    }

    if ($postAction === 'create') {
        $startDatetime = $_POST['date'] . ' ' . $_POST['time'] . ':00';
        $result = $manager->create([
            'service_id' => (int)$_POST['service_id'],
            'provider_id' => (int)$_POST['provider_id'],
            'start_datetime' => $startDatetime,
            'first_name' => trim($_POST['first_name']),
            'last_name' => trim($_POST['last_name']),
            'email' => trim($_POST['email']),
            'phone' => trim($_POST['phone'] ?? ''),
            'notes' => trim($_POST['notes'] ?? ''),
        ]);
        if ($result) {
            ab_flash('success', 'Rendez-vous créé avec succès.');
        } else {
            ab_flash('error', 'Erreur lors de la création.');
        }
        ab_redirect(ab_url('admin/index.php?page=appointments'));
    }

    if ($postAction === 'delete' && isset($_POST['id'])) {
        $db->delete('ab_appointments', 'id = ?', [(int)$_POST['id']]);
        ab_flash('success', 'Rendez-vous supprimé.');
        ab_redirect(ab_url('admin/index.php?page=appointments'));
    }
}

if ($action === 'view' && isset($_GET['id'])):
    $appointment = $manager->getAppointment((int)$_GET['id']);
    if (!$appointment) { ab_flash('error', 'Rendez-vous non trouvé.'); ab_redirect(ab_url('admin/index.php?page=appointments')); }
    $deposit = $db->fetchOne("SELECT * FROM ab_deposits WHERE appointment_id = ?", [$appointment['id']]);
?>
<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-calendar-check"></i> Rendez-vous #<?= $appointment['id'] ?></h5>
                <span class="badge badge-<?= $appointment['status'] ?> fs-6"><?= $appointment['status'] ?></span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <h6 class="text-muted">Client</h6>
                        <p class="mb-1"><strong><?= ab_escape($appointment['customer_first_name'] . ' ' . $appointment['customer_last_name']) ?></strong></p>
                        <p class="mb-1"><i class="bi bi-envelope"></i> <?= ab_escape($appointment['customer_email']) ?></p>
                        <p class="mb-0"><i class="bi bi-phone"></i> <?= ab_escape($appointment['customer_phone']) ?></p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted">Prestation</h6>
                        <p class="mb-1"><span class="badge" style="background:<?= ab_escape($appointment['service_color']) ?>"><?= ab_escape($appointment['service_name']) ?></span></p>
                        <p class="mb-1"><i class="bi bi-clock"></i> <?= $appointment['service_duration'] ?> min</p>
                        <p class="mb-0"><i class="bi bi-currency-euro"></i> <?= ab_format_price($appointment['service_price']) ?></p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted">Date & Heure</h6>
                        <p class="mb-1"><i class="bi bi-calendar"></i> <?= ab_format_date($appointment['start_datetime']) ?></p>
                        <p class="mb-0"><i class="bi bi-clock"></i> <?= ab_format_time($appointment['start_datetime']) ?> - <?= ab_format_time($appointment['end_datetime']) ?></p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted">Prestataire</h6>
                        <p class="mb-0"><?= ab_escape($appointment['provider_first_name'] . ' ' . $appointment['provider_last_name']) ?></p>
                    </div>
                </div>
                <?php if ($appointment['notes']): ?>
                <hr>
                <h6 class="text-muted">Notes client</h6>
                <p><?= nl2br(ab_escape($appointment['notes'])) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($deposit): ?>
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-cash-coin"></i> Acompte</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <strong>Montant :</strong> <?= ab_format_price($deposit['amount']) ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Statut :</strong> <span class="badge badge-<?= $deposit['status'] ?>"><?= $deposit['status'] ?></span>
                    </div>
                    <div class="col-md-4">
                        <strong>Date limite :</strong> <?= $deposit['due_date'] ? ab_format_date($deposit['due_date']) : '-' ?>
                    </div>
                </div>
                <?php if ($deposit['status'] === 'pending'): ?>
                <hr>
                <form method="POST" class="row g-2 align-items-end">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="action" value="confirm_deposit_inline">
                    <input type="hidden" name="deposit_id" value="<?= $deposit['id'] ?>">
                    <input type="hidden" name="id" value="<?= $appointment['id'] ?>">
                    <div class="col-md-4">
                        <label class="form-label">Mode de paiement</label>
                        <select name="payment_method" class="form-select form-select-sm">
                            <option value="bank_transfer">Virement</option>
                            <option value="card">Carte</option>
                            <option value="cash">Espèces</option>
                            <option value="paypal">PayPal</option>
                            <option value="other">Autre</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Référence</label>
                        <input type="text" name="reference" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check-circle"></i> Confirmer le paiement</button>
                    </div>
                </form>
                <?php endif; ?>
                <?php if ($deposit['paid_at']): ?>
                <p class="mt-2 mb-0 text-success"><i class="bi bi-check-circle"></i> Payé le <?= ab_format_date($deposit['paid_at']) ?> par <?= $deposit['payment_method'] ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white"><h6 class="mb-0">Actions</h6></div>
            <div class="card-body d-grid gap-2">
                <?php if ($appointment['status'] === 'pending'): ?>
                <form method="POST">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="id" value="<?= $appointment['id'] ?>">
                    <input type="hidden" name="status" value="confirmed">
                    <button class="btn btn-success w-100"><i class="bi bi-check-circle"></i> Confirmer</button>
                </form>
                <?php endif; ?>
                <?php if (in_array($appointment['status'], ['pending', 'confirmed'])): ?>
                <form method="POST">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="id" value="<?= $appointment['id'] ?>">
                    <input type="hidden" name="status" value="cancelled">
                    <button class="btn btn-danger w-100" onclick="return confirm('Annuler ce rendez-vous ?')"><i class="bi bi-x-circle"></i> Annuler</button>
                </form>
                <?php endif; ?>
                <?php if ($appointment['status'] === 'confirmed'): ?>
                <form method="POST">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="id" value="<?= $appointment['id'] ?>">
                    <input type="hidden" name="status" value="completed">
                    <button class="btn btn-secondary w-100"><i class="bi bi-check-all"></i> Marquer terminé</button>
                </form>
                <form method="POST">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="id" value="<?= $appointment['id'] ?>">
                    <input type="hidden" name="status" value="no_show">
                    <button class="btn btn-warning w-100"><i class="bi bi-person-x"></i> Absent</button>
                </form>
                <?php endif; ?>
                <hr>
                <form method="POST" onsubmit="return confirm('Supprimer définitivement ce rendez-vous ?')">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $appointment['id'] ?>">
                    <button class="btn btn-outline-danger w-100"><i class="bi bi-trash"></i> Supprimer</button>
                </form>
                <a href="<?= ab_url('admin/index.php?page=appointments') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Retour</a>
            </div>
        </div>
    </div>
</div>

<?php
// Handle inline deposit confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm_deposit_inline') {
    $manager->confirmDeposit((int)$_POST['deposit_id'], $_POST['payment_method'], $_POST['reference'] ?? '');
    ab_flash('success', 'Acompte confirmé et rendez-vous validé.');
    ab_redirect(ab_url('admin/index.php?page=appointments&action=view&id=' . $_POST['id']));
}
?>

<?php elseif ($action === 'create'): ?>
<?php
$services = $db->fetchAll("SELECT * FROM ab_services WHERE is_active = 1 ORDER BY sort_order, name");
$providers = $db->fetchAll("SELECT * FROM ab_users WHERE is_active = 1 ORDER BY first_name");
?>
<div class="card">
    <div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-plus-circle"></i> Nouveau rendez-vous</h5></div>
    <div class="card-body">
        <form method="POST">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="create">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Prestation *</label>
                    <select name="service_id" class="form-select" required>
                        <option value="">Choisir...</option>
                        <?php foreach ($services as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= ab_escape($s['name']) ?> (<?= ab_format_price($s['price']) ?> - <?= $s['duration'] ?>min)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Prestataire *</label>
                    <select name="provider_id" class="form-select" required>
                        <?php foreach ($providers as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= ab_escape($p['first_name'] . ' ' . $p['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Date *</label>
                    <input type="date" name="date" class="form-control" required min="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Heure *</label>
                    <input type="time" name="time" class="form-control" required step="900">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Prénom *</label>
                    <input type="text" name="first_name" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nom *</label>
                    <input type="text" name="last_name" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Téléphone</label>
                    <input type="tel" name="phone" class="form-control">
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check"></i> Créer le rendez-vous</button>
                    <a href="<?= ab_url('admin/index.php?page=appointments') ?>" class="btn btn-outline-secondary">Annuler</a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php else: // List view with calendar ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-calendar-check"></i> Rendez-vous</h4>
    <div>
        <a href="<?= ab_url('admin/index.php?page=appointments&action=create') ?>" class="btn btn-primary">
            <i class="bi bi-plus"></i> Nouveau
        </a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <div id="calendar"></div>
    </div>
</div>

<!-- Recent list -->
<?php
$filter = $_GET['status'] ?? '';
$sql = "SELECT a.*, c.first_name as cf, c.last_name as cl, s.name as sn, s.color as sc, u.first_name as pf, u.last_name as pl
        FROM ab_appointments a
        JOIN ab_customers c ON a.customer_id = c.id
        JOIN ab_services s ON a.service_id = s.id
        JOIN ab_users u ON a.provider_id = u.id";
$params = [];
if ($filter) {
    $sql .= " WHERE a.status = ?";
    $params[] = $filter;
}
if (!Auth::isAdmin()) {
    $sql .= ($filter ? " AND" : " WHERE") . " a.provider_id = ?";
    $params[] = Auth::userId();
}
$sql .= " ORDER BY a.start_datetime DESC LIMIT 50";
$appointments = $db->fetchAll($sql, $params);
?>
<div class="card">
    <div class="card-header bg-white">
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= ab_url('admin/index.php?page=appointments') ?>" class="btn btn-sm <?= !$filter ? 'btn-primary' : 'btn-outline-primary' ?>">Tous</a>
            <a href="<?= ab_url('admin/index.php?page=appointments&status=pending') ?>" class="btn btn-sm <?= $filter === 'pending' ? 'btn-warning' : 'btn-outline-warning' ?>">En attente</a>
            <a href="<?= ab_url('admin/index.php?page=appointments&status=confirmed') ?>" class="btn btn-sm <?= $filter === 'confirmed' ? 'btn-success' : 'btn-outline-success' ?>">Confirmés</a>
            <a href="<?= ab_url('admin/index.php?page=appointments&status=completed') ?>" class="btn btn-sm <?= $filter === 'completed' ? 'btn-secondary' : 'btn-outline-secondary' ?>">Terminés</a>
            <a href="<?= ab_url('admin/index.php?page=appointments&status=cancelled') ?>" class="btn btn-sm <?= $filter === 'cancelled' ? 'btn-danger' : 'btn-outline-danger' ?>">Annulés</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Date</th><th>Heure</th><th>Client</th><th>Prestation</th><th>Prestataire</th><th>Statut</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($appointments as $a): ?>
            <tr>
                <td><?= ab_format_date($a['start_datetime']) ?></td>
                <td><?= ab_format_time($a['start_datetime']) ?></td>
                <td><?= ab_escape($a['cf'] . ' ' . $a['cl']) ?></td>
                <td><span class="badge" style="background:<?= ab_escape($a['sc']) ?>"><?= ab_escape($a['sn']) ?></span></td>
                <td><?= ab_escape($a['pf'] . ' ' . $a['pl']) ?></td>
                <td><span class="badge badge-<?= $a['status'] ?>"><?= $a['status'] ?></span></td>
                <td><a href="<?= ab_url('admin/index.php?page=appointments&action=view&id=' . $a['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    if (calendarEl && typeof FullCalendar !== 'undefined') {
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'timeGridWeek',
            locale: 'fr',
            headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
            slotMinTime: '07:00:00',
            slotMaxTime: '21:00:00',
            allDaySlot: false,
            height: 'auto',
            events: '<?= ab_url('api/index.php?route=calendar-events') ?>',
            eventClick: function(info) {
                window.location.href = '<?= ab_url('admin/index.php?page=appointments&action=view&id=') ?>' + info.event.id;
            }
        });
        calendar.render();
    }
});
</script>
<?php endif; ?>
