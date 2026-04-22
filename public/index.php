<?php
/**
 * ABAppointments - Public Booking Page
 */
require_once __DIR__ . '/../core/App.php';

$primaryColor = ab_setting('primary_color', '#e91e63');
$secondaryColor = ab_setting('secondary_color', '#9c27b0');
$businessName = ab_setting('business_name', 'ABAppointments');
$db = Database::getInstance();

// Load services grouped by category
$services = $db->fetchAll(
    "SELECT s.*, sc.name as category_name FROM ab_services s
     LEFT JOIN ab_service_categories sc ON s.category_id = sc.id
     WHERE s.is_active = 1 ORDER BY sc.sort_order, s.sort_order, s.name"
);

$providers = $db->fetchAll("SELECT id, first_name, last_name, welcome_message FROM ab_users WHERE is_active = 1 AND is_visible_booking = 1 AND role IN ('admin','provider') ORDER BY first_name");
$bookingAnnouncement = ab_setting('booking_announcement');
$modalEnabled = ab_setting('modal_enabled', '0') === '1';
$modalMessage = ab_setting('modal_message');
$modalMaxViews = (int) ab_setting('modal_max_views', '3');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prendre rendez-vous - <?= ab_escape($businessName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --ab-primary: <?= $primaryColor ?>; --ab-secondary: <?= $secondaryColor ?>; }
        body { background: #f8f9fa; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
        .booking-header { background: linear-gradient(135deg, var(--ab-primary), var(--ab-secondary)); color: #fff; padding: 40px 20px; text-align: center; }
        .booking-header h1 { font-size: 1.8rem; margin-bottom: 5px; }
        .booking-container { max-width: 800px; margin: -30px auto 40px; padding: 0 15px; position: relative; }
        .step-indicator { display: flex; justify-content: center; gap: 5px; margin-bottom: 25px; }
        .step-dot { width: 12px; height: 12px; border-radius: 50%; background: rgba(255,255,255,0.4); transition: all 0.3s; }
        .step-dot.active { background: #fff; transform: scale(1.3); }
        .step-dot.done { background: #fff; }
        .booking-step { display: none; }
        .booking-step.active { display: block; }
        .card { border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.08); border-radius: 12px; }
        .service-card { cursor: pointer; transition: all 0.2s; border: 2px solid transparent !important; }
        .service-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.12); }
        .service-card.selected { border-color: var(--ab-primary) !important; background: rgba(233,30,99,0.03); }
        .service-color { width: 6px; border-radius: 12px 0 0 12px; position: absolute; left: 0; top: 0; bottom: 0; }
        .provider-card { cursor: pointer; transition: all 0.2s; border: 2px solid transparent !important; text-align: center; padding: 20px; }
        .provider-card:hover { border-color: var(--ab-primary) !important; }
        .provider-card.selected { border-color: var(--ab-primary) !important; background: rgba(233,30,99,0.03); }
        .provider-avatar { width: 60px; height: 60px; border-radius: 50%; background: var(--ab-primary); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin: 0 auto 10px; }
        .date-picker { display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px; }
        .date-cell { text-align: center; padding: 10px 5px; border-radius: 8px; cursor: pointer; transition: all 0.2s; }
        .date-cell:hover { background: rgba(233,30,99,0.1); }
        .date-cell.selected { background: var(--ab-primary); color: #fff; }
        .date-cell.disabled { opacity: 0.3; pointer-events: none; }
        .date-cell.no-slots { color: #c0c0c0; pointer-events: none; }
        .date-cell.no-slots .day-num { text-decoration: line-through; }
        .date-picker.loading-days .date-cell[data-date]:not(.disabled) { opacity: 0.5; }
        .date-cell .day-name { font-size: 0.7rem; text-transform: uppercase; color: #999; }
        .date-cell.selected .day-name { color: rgba(255,255,255,0.8); }
        .date-cell .day-num { font-size: 1.1rem; font-weight: 600; }
        .time-slot { display: inline-block; padding: 10px 18px; margin: 4px; border: 2px solid #e0e0e0; border-radius: 8px; cursor: pointer; transition: all 0.2s; font-weight: 500; }
        .time-slot:hover { border-color: var(--ab-primary); color: var(--ab-primary); }
        .time-slot.selected { background: var(--ab-primary); border-color: var(--ab-primary); color: #fff; }
        .btn-ab { background: var(--ab-primary); border: none; color: #fff; padding: 12px 30px; border-radius: 8px; font-weight: 600; }
        .btn-ab:hover { background: color-mix(in srgb, var(--ab-primary) 85%, black); color: #fff; }
        .btn-ab-outline { border: 2px solid var(--ab-primary); color: var(--ab-primary); background: transparent; padding: 10px 25px; border-radius: 8px; }
        .btn-ab-outline:hover { background: var(--ab-primary); color: #fff; }
        .summary-table td { padding: 8px 12px; }
        .summary-table .label { color: #666; font-weight: 500; }
        .deposit-info { background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 15px; margin-top: 15px; }
        .month-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .month-nav h5 { margin: 0; }
        .loading-spinner { text-align: center; padding: 40px; color: #999; }
        .category-label { font-size: 0.85rem; color: var(--ab-primary); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin: 20px 0 10px; padding-left: 5px; }
        .category-label:first-child { margin-top: 0; }
    </style>
</head>
<body>
    <div class="booking-header">
        <h1><i class="bi bi-calendar-heart"></i> <?= ab_escape($businessName) ?></h1>
        <p class="mb-2">Prenez rendez-vous en ligne</p>
        <div class="step-indicator">
            <div class="step-dot active" data-step="1"></div>
            <div class="step-dot" data-step="2"></div>
            <div class="step-dot" data-step="3"></div>
            <div class="step-dot" data-step="4"></div>
            <div class="step-dot" data-step="5"></div>
        </div>
    </div>

    <div class="booking-container">
        <?php if (!empty($bookingAnnouncement)): ?>
        <div class="alert alert-info mb-3" style="border-radius:12px;">
            <i class="bi bi-megaphone-fill"></i> <?= ab_safe_html($bookingAnnouncement) ?>
        </div>
        <?php endif; ?>

        <!-- Step 1: Choose service -->
        <div class="booking-step active" id="step-1">
            <div class="card p-4">
                <h4 class="mb-3"><i class="bi bi-palette"></i> Choisissez votre prestation</h4>
                <?php
                $currentCategory = null;
                foreach ($services as $s):
                    if ($s['category_name'] !== $currentCategory):
                        $currentCategory = $s['category_name'];
                ?>
                <div class="category-label"><?= ab_escape($currentCategory ?: 'Autres') ?></div>
                <?php endif; ?>
                <div class="card service-card mb-2 position-relative overflow-hidden" data-service-id="<?= $s['id'] ?>" data-duration="<?= $s['duration'] ?>" data-price="<?= $s['price'] ?>" data-deposit="<?= $s['deposit_enabled'] ?>" data-deposit-type="<?= $s['deposit_type'] ?>" data-deposit-amount="<?= $s['deposit_amount'] ?>" data-name="<?= ab_escape($s['name']) ?>">
                    <div class="service-color" style="background:<?= ab_escape($s['color']) ?>"></div>
                    <div class="card-body ps-4">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1"><?= ab_escape($s['name']) ?></h6>
                                <?php if ($s['description']): ?>
                                <small class="text-muted"><?= ab_escape($s['description']) ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold" style="color: var(--ab-primary);"><?= ab_format_price($s['price']) ?></div>
                                <small class="text-muted"><i class="bi bi-clock"></i> <?= $s['duration'] ?> min</small>
                                <?php if ($s['deposit_enabled']): ?>
                                <br><small class="text-warning"><i class="bi bi-cash-coin"></i> Acompte requis</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Step 2: Choose provider -->
        <div class="booking-step" id="step-2">
            <div class="card p-4">
                <h4 class="mb-3"><i class="bi bi-person"></i> Choisissez votre prestataire</h4>
                <div class="row g-3" id="providers-list">
                    <?php foreach ($providers as $p): ?>
                    <div class="col-6 col-md-4">
                        <div class="card provider-card" data-provider-id="<?= $p['id'] ?>" data-name="<?= ab_escape($p['first_name'] . ' ' . $p['last_name']) ?>" data-message="<?= ab_escape($p['welcome_message'] ?? '') ?>">
                            <div class="provider-avatar"><?= strtoupper(substr($p['first_name'], 0, 1) . substr($p['last_name'], 0, 1)) ?></div>
                            <strong><?= ab_escape($p['first_name'] . ' ' . $p['last_name']) ?></strong>
                            <?php if (!empty($p['welcome_message'])): ?>
                            <small class="text-muted mt-1 d-block" style="font-size:0.8rem;"><?= ab_safe_html($p['welcome_message']) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3">
                    <button class="btn btn-ab-outline btn-sm" onclick="goToStep(1)"><i class="bi bi-arrow-left"></i> Retour</button>
                </div>
            </div>
        </div>

        <!-- Step 3: Choose date & time -->
        <div class="booking-step" id="step-3">
            <div class="card p-4">
                <h4 class="mb-3"><i class="bi bi-calendar3"></i> Choisissez la date et l'heure</h4>
                <div class="month-nav">
                    <button class="btn btn-sm btn-outline-secondary" id="prev-month"><i class="bi bi-chevron-left"></i></button>
                    <h5 id="month-title"></h5>
                    <button class="btn btn-sm btn-outline-secondary" id="next-month"><i class="bi bi-chevron-right"></i></button>
                </div>
                <div class="date-picker" id="date-picker"></div>
                <hr>
                <h5 class="mb-3">Créneaux disponibles</h5>
                <div id="time-slots">
                    <p class="text-muted text-center">Sélectionnez une date</p>
                </div>
                <div class="mt-3">
                    <button class="btn btn-ab-outline btn-sm" onclick="goToStep(2)"><i class="bi bi-arrow-left"></i> Retour</button>
                </div>
            </div>
        </div>

        <!-- Step 4: Customer info -->
        <div class="booking-step" id="step-4">
            <div class="card p-4">
                <h4 class="mb-3"><i class="bi bi-person-lines-fill"></i> Vos informations</h4>
                <form id="booking-form">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Prénom *</label>
                            <input type="text" name="first_name" class="form-control" required id="bf-firstname">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nom *</label>
                            <input type="text" name="last_name" class="form-control" required id="bf-lastname">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" required id="bf-email">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Téléphone <?= ab_setting('require_phone', '1') === '1' ? '*' : '' ?></label>
                            <input type="tel" name="phone" class="form-control" id="bf-phone" <?= ab_setting('require_phone', '1') === '1' ? 'required' : '' ?>>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes / Remarques</label>
                            <textarea name="notes" class="form-control" rows="2" id="bf-notes" placeholder="Précisez vos souhaits..."></textarea>
                        </div>
                    </div>
                    <div class="mt-3 d-flex justify-content-between">
                        <button type="button" class="btn btn-ab-outline btn-sm" onclick="goToStep(3)"><i class="bi bi-arrow-left"></i> Retour</button>
                        <button type="submit" class="btn btn-ab"><i class="bi bi-arrow-right"></i> Continuer</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Step 5: Summary & Confirm -->
        <div class="booking-step" id="step-5">
            <div class="card p-4">
                <h4 class="mb-3"><i class="bi bi-check-circle"></i> Récapitulatif</h4>
                <table class="table summary-table">
                    <tr><td class="label">Prestation</td><td id="sum-service" class="fw-bold"></td></tr>
                    <tr><td class="label">Prestataire</td><td id="sum-provider"></td></tr>
                    <tr><td class="label">Date</td><td id="sum-date"></td></tr>
                    <tr><td class="label">Heure</td><td id="sum-time"></td></tr>
                    <tr><td class="label">Durée</td><td id="sum-duration"></td></tr>
                    <tr><td class="label">Prix</td><td id="sum-price" class="fw-bold"></td></tr>
                </table>

                <div id="sum-deposit" style="display:none;">
                    <div class="deposit-info">
                        <h6><i class="bi bi-cash-coin"></i> Acompte requis</h6>
                        <p class="mb-1">Un acompte de <strong id="sum-deposit-amount"></strong> est requis pour confirmer votre rendez-vous.</p>
                        <p class="mb-0 text-muted small">Les instructions de paiement vous seront envoyées par email.</p>
                    </div>
                </div>

                <div id="sum-customer" class="mt-3 p-3 bg-light rounded">
                    <small class="text-muted d-block mb-1">Vos coordonnées</small>
                    <span id="sum-name"></span><br>
                    <span id="sum-email"></span><br>
                    <span id="sum-phone"></span>
                </div>

                <div class="mt-4 d-flex justify-content-between">
                    <button class="btn btn-ab-outline btn-sm" onclick="goToStep(4)"><i class="bi bi-arrow-left"></i> Modifier</button>
                    <button class="btn btn-ab btn-lg" id="confirm-btn" onclick="confirmBooking()">
                        <i class="bi bi-check-circle"></i> Confirmer le rendez-vous
                    </button>
                </div>
            </div>
        </div>

        <!-- Confirmation -->
        <div class="booking-step" id="step-success">
            <div class="card p-5 text-center">
                <div style="font-size: 4rem; color: #28a745;"><i class="bi bi-check-circle-fill"></i></div>
                <h3 class="mt-3">Rendez-vous enregistré !</h3>
                <p class="text-muted" id="success-message">Vous recevrez un email de confirmation.</p>
                <div id="success-deposit" style="display:none;" class="deposit-info text-start mx-auto" style="max-width:500px;">
                    <h6><i class="bi bi-cash-coin"></i> Acompte à régler</h6>
                    <p id="success-deposit-text"></p>
                </div>
                <div class="mt-4">
                    <a href="<?= ab_url('public/') ?>" class="btn btn-ab">Prendre un autre rendez-vous</a>
                </div>
            </div>
        </div>
    </div>

    <footer class="text-center py-3 text-muted small">
        <?= ab_escape($businessName) ?> &middot; <?= ab_escape(ab_setting('business_phone')) ?>
    </footer>

    <?php if ($modalEnabled && !empty($modalMessage)): ?>
    <div class="modal fade" id="importantModal" tabindex="-1" aria-labelledby="importantModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, var(--ab-primary), var(--ab-secondary)); color: #fff; border: none;">
                    <h5 class="modal-title" id="importantModalLabel"><i class="bi bi-exclamation-triangle-fill"></i> Information importante</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body" style="padding: 25px; font-size: 1.05rem; line-height: 1.6;">
                    <?= ab_safe_html($modalMessage) ?>
                </div>
                <div class="modal-footer" style="border: none;">
                    <button type="button" class="btn btn-ab" data-bs-dismiss="modal">J'ai compris</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($modalEnabled && !empty($modalMessage)): ?>
    <script>
    (function() {
        const maxViews = <?= $modalMaxViews ?>;
        const msgHash = '<?= md5($modalMessage) ?>';
        const storageKey = 'ab_modal_' + msgHash;
        // Clean old modal keys when message changes
        for (let i = localStorage.length - 1; i >= 0; i--) {
            const k = localStorage.key(i);
            if (k && k.startsWith('ab_modal_') && k !== storageKey) localStorage.removeItem(k);
        }
        let views = parseInt(localStorage.getItem(storageKey) || '0', 10);
        if (views < maxViews) {
            localStorage.setItem(storageKey, String(views + 1));
            new bootstrap.Modal(document.getElementById('importantModal')).show();
        }
    })();
    </script>
    <?php endif; ?>
    <script>
    const API_URL = '<?= ab_url('api/index.php') ?>';
    let booking = { serviceId: null, providerId: null, date: null, time: null, serviceName: '', providerName: '', duration: 0, price: 0, deposit: false, depositType: '', depositAmount: 0 };
    let currentMonth = new Date();
    let availableDaysCache = {};

    // Step navigation
    function goToStep(n) {
        document.querySelectorAll('.booking-step').forEach(s => s.classList.remove('active'));
        document.getElementById('step-' + n).classList.add('active');
        document.querySelectorAll('.step-dot').forEach(d => {
            const step = parseInt(d.dataset.step);
            d.classList.toggle('active', step === n);
            d.classList.toggle('done', step < n);
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Step 1: Service selection
    document.querySelectorAll('.service-card').forEach(card => {
        card.addEventListener('click', function() {
            document.querySelectorAll('.service-card').forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
            booking.serviceId = this.dataset.serviceId;
            booking.serviceName = this.dataset.name;
            booking.duration = parseInt(this.dataset.duration);
            booking.price = parseFloat(this.dataset.price);
            booking.deposit = this.dataset.deposit === '1';
            booking.depositType = this.dataset.depositType;
            booking.depositAmount = parseFloat(this.dataset.depositAmount);
            setTimeout(() => goToStep(2), 300);
        });
    });

    // Step 2: Provider selection
    document.querySelectorAll('.provider-card').forEach(card => {
        card.addEventListener('click', function() {
            document.querySelectorAll('.provider-card').forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
            booking.providerId = this.dataset.providerId;
            booking.providerName = this.dataset.name;
            currentMonth = new Date();
            renderCalendar();
            setTimeout(() => goToStep(3), 300);
        });
    });

    // Step 3: Calendar
    const monthNames = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
    const dayNames = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];

    function renderCalendar() {
        const year = currentMonth.getFullYear();
        const month = currentMonth.getMonth();
        document.getElementById('month-title').textContent = monthNames[month] + ' ' + year;

        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const today = new Date(); today.setHours(0,0,0,0);

        let html = dayNames.map(d => '<div class="date-cell disabled"><div class="day-name">' + d + '</div></div>').join('');

        // Empty cells before first day
        for (let i = 0; i < firstDay.getDay(); i++) html += '<div class="date-cell disabled"></div>';

        for (let d = 1; d <= lastDay.getDate(); d++) {
            const date = new Date(year, month, d);
            const dateStr = year + '-' + String(month+1).padStart(2,'0') + '-' + String(d).padStart(2,'0');
            const isPast = date < today;
            const selected = booking.date === dateStr;
            html += '<div class="date-cell ' + (isPast ? 'disabled' : '') + (selected ? ' selected' : '') + '" data-date="' + dateStr + '">'
                + '<div class="day-num">' + d + '</div></div>';
        }

        document.getElementById('date-picker').innerHTML = html;
        document.querySelectorAll('.date-cell[data-date]').forEach(cell => {
            cell.addEventListener('click', () => selectDate(cell.dataset.date));
        });

        loadAvailableDays();
    }

    function loadAvailableDays() {
        if (!booking.serviceId || !booking.providerId) return;

        const year = currentMonth.getFullYear();
        const month = currentMonth.getMonth() + 1;
        const cacheKey = year + '-' + month + '-' + booking.serviceId + '-' + booking.providerId;
        const datePicker = document.getElementById('date-picker');

        if (availableDaysCache[cacheKey] !== undefined) {
            applyAvailableDays(availableDaysCache[cacheKey]);
            return;
        }

        datePicker.classList.add('loading-days');
        fetch(API_URL + '?route=available-days&service_id=' + booking.serviceId + '&provider_id=' + booking.providerId + '&year=' + year + '&month=' + month)
            .then(r => r.json())
            .then(data => {
                availableDaysCache[cacheKey] = data.days || [];
                applyAvailableDays(availableDaysCache[cacheKey]);
            })
            .catch(() => { /* on error, leave all days clickable */ })
            .finally(() => { datePicker.classList.remove('loading-days'); });
    }

    function applyAvailableDays(availableDays) {
        document.querySelectorAll('.date-cell[data-date]').forEach(cell => {
            if (cell.classList.contains('disabled') || cell.classList.contains('selected')) return;
            if (availableDays.includes(cell.dataset.date)) {
                cell.classList.remove('no-slots');
            } else {
                cell.classList.add('no-slots');
            }
        });
    }

    document.getElementById('prev-month').addEventListener('click', () => { currentMonth.setMonth(currentMonth.getMonth() - 1); renderCalendar(); });
    document.getElementById('next-month').addEventListener('click', () => { currentMonth.setMonth(currentMonth.getMonth() + 1); renderCalendar(); });

    function selectDate(date) {
        booking.date = date;
        document.querySelectorAll('.date-cell').forEach(c => c.classList.remove('selected'));
        document.querySelector('.date-cell[data-date="' + date + '"]')?.classList.add('selected');
        loadTimeSlots();
    }

    function loadTimeSlots() {
        document.getElementById('time-slots').innerHTML = '<div class="loading-spinner"><div class="spinner-border text-secondary"></div><p>Chargement...</p></div>';
        fetch(API_URL + '?route=available-slots&service_id=' + booking.serviceId + '&provider_id=' + booking.providerId + '&date=' + booking.date)
            .then(r => {
                if (!r.ok) {
                    return r.text().then(text => {
                        try { return JSON.parse(text); } catch(e) { throw new Error(text || 'Erreur serveur ' + r.status); }
                    }).then(data => { throw new Error(data.error || 'Erreur serveur ' + r.status); });
                }
                return r.json();
            })
            .then(data => {
                if (data.error) {
                    document.getElementById('time-slots').innerHTML = '<p class="text-danger text-center">' + data.error + '</p>';
                    return;
                }
                if (!data.slots || data.slots.length === 0) {
                    let debugHtml = '';
                    if (data.debug) {
                        debugHtml = '<pre class="text-start small mt-2 p-2 bg-light" style="font-size:11px;">' + JSON.stringify(data.debug, null, 2) + '</pre>';
                    }
                    document.getElementById('time-slots').innerHTML = '<p class="text-muted text-center">Aucun créneau disponible ce jour</p>' + debugHtml;
                    return;
                }
                let html = '<div class="text-center">';
                data.slots.forEach(slot => {
                    html += '<span class="time-slot' + (booking.time === slot ? ' selected' : '') + '" data-time="' + slot + '">' + slot + '</span>';
                });
                html += '</div>';
                document.getElementById('time-slots').innerHTML = html;
                document.querySelectorAll('.time-slot').forEach(s => {
                    s.addEventListener('click', function() {
                        document.querySelectorAll('.time-slot').forEach(t => t.classList.remove('selected'));
                        this.classList.add('selected');
                        booking.time = this.dataset.time;
                        setTimeout(() => goToStep(4), 300);
                    });
                });
            })
            .catch(err => {
                console.error('Slot loading error:', err);
                document.getElementById('time-slots').innerHTML = '<p class="text-danger text-center">Erreur de chargement : ' + (err.message || 'vérifiez la console') + '</p>';
            });
    }

    // Step 4: Form submit
    document.getElementById('booking-form').addEventListener('submit', function(e) {
        e.preventDefault();
        showSummary();
        goToStep(5);
    });

    function showSummary() {
        const dateObj = new Date(booking.date + 'T00:00:00');
        const dateStr = dateObj.toLocaleDateString('fr-FR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

        document.getElementById('sum-service').textContent = booking.serviceName;
        document.getElementById('sum-provider').textContent = booking.providerName;
        document.getElementById('sum-date').textContent = dateStr;
        document.getElementById('sum-time').textContent = booking.time;
        document.getElementById('sum-duration').textContent = booking.duration + ' minutes';
        document.getElementById('sum-price').textContent = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(booking.price);

        document.getElementById('sum-name').textContent = document.getElementById('bf-firstname').value + ' ' + document.getElementById('bf-lastname').value;
        document.getElementById('sum-email').textContent = document.getElementById('bf-email').value;
        document.getElementById('sum-phone').textContent = document.getElementById('bf-phone').value;

        if (booking.deposit) {
            const depAmount = booking.depositType === 'percentage' ? booking.price * booking.depositAmount / 100 : booking.depositAmount;
            document.getElementById('sum-deposit-amount').textContent = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(depAmount);
            document.getElementById('sum-deposit').style.display = 'block';
        } else {
            document.getElementById('sum-deposit').style.display = 'none';
        }
    }

    // Step 5: Confirm
    function confirmBooking() {
        const btn = document.getElementById('confirm-btn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Confirmation...';

        const body = {
            service_id: booking.serviceId,
            provider_id: booking.providerId,
            date: booking.date,
            time: booking.time,
            first_name: document.getElementById('bf-firstname').value,
            last_name: document.getElementById('bf-lastname').value,
            email: document.getElementById('bf-email').value,
            phone: document.getElementById('bf-phone').value,
            notes: document.getElementById('bf-notes').value
        };

        fetch(API_URL + '?route=book', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (data.deposit) {
                    document.getElementById('success-deposit').style.display = 'block';
                    document.getElementById('success-deposit-text').textContent =
                        'Un acompte de ' + new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(data.deposit.amount)
                        + ' est requis. Les instructions de paiement vous ont été envoyées par email.';
                    document.getElementById('success-message').textContent = 'Votre rendez-vous sera confirmé après réception de l\'acompte.';
                } else {
                    document.getElementById('success-message').textContent = data.status === 'confirmed'
                        ? 'Votre rendez-vous est confirmé ! Vous recevrez un email de confirmation.'
                        : 'Votre demande a été enregistrée. Vous recevrez un email de confirmation.';
                }
                document.querySelectorAll('.booking-step').forEach(s => s.classList.remove('active'));
                document.getElementById('step-success').classList.add('active');
            } else {
                alert(data.error || 'Erreur lors de la réservation.');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-circle"></i> Confirmer le rendez-vous';
            }
        })
        .catch(() => {
            alert('Erreur de connexion. Veuillez réessayer.');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle"></i> Confirmer le rendez-vous';
        });
    }
    </script>
</body>
</html>
