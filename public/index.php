<?php
/**
 * ABAppointments - Public Booking Page
 */
require_once __DIR__ . '/../core/App.php';

$primaryColor = ab_setting('primary_color', '#e91e63');
$secondaryColor = ab_setting('secondary_color', '#9c27b0');
$businessName = ab_setting('business_name', 'ABAppointments');
$db = Database::getInstance();

$services = $db->fetchAll(
    "SELECT s.*, sc.name as category_name FROM ab_services s
     LEFT JOIN ab_service_categories sc ON s.category_id = sc.id
     WHERE s.is_active = 1 ORDER BY sc.sort_order, s.sort_order, s.name"
);

$providers = $db->fetchAll("SELECT id, first_name, last_name, welcome_message FROM ab_users WHERE is_active = 1 AND is_visible_booking = 1 AND role IN ('admin','provider') ORDER BY first_name");
$bookingAnnouncement = ab_setting('booking_announcement');
$ageCheckEnabled = ab_setting('age_check_enabled', '0') === '1';
$ageMinBooking = (int) ab_setting('age_min_booking', '10');
$ageMinSolo = (int) ab_setting('age_min_solo', '18');
$cgvEnabled = ab_setting('cgv_enabled', '0') === '1';
$cgvLabel   = ab_setting('cgv_label', 'les conditions générales de vente');
$cgvUrl     = ab_setting('cgv_url');
$cgsEnabled = ab_setting('cgs_enabled', '0') === '1';
$cgsLabel   = ab_setting('cgs_label', 'les conditions générales de services');
$cgsUrl     = ab_setting('cgs_url');
$modalEnabled = ab_setting('modal_enabled', '0') === '1';
$modalMessage = ab_setting('modal_message');
$modalMaxViews = (int) ab_setting('modal_max_views', '3');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prendre rendez-vous – <?= ab_escape($businessName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --p: <?= $primaryColor ?>;
            --s: <?= $secondaryColor ?>;
        }
        html, body { overflow-x: hidden; max-width: 100%; }
        body {
            background: #f2f3f7;
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            color: #18182b;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Hero ── */
        .booking-hero {
            background: linear-gradient(135deg, var(--p) 0%, var(--s) 100%);
            color: #fff;
            padding: 52px 20px 84px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .booking-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                radial-gradient(circle at 15% 50%, rgba(255,255,255,0.09) 0%, transparent 55%),
                radial-gradient(circle at 85% 15%, rgba(255,255,255,0.06) 0%, transparent 45%);
            pointer-events: none;
        }
        .hero-business { font-size: 1.95rem; font-weight: 800; letter-spacing: -0.03em; margin-bottom: 5px; position: relative; }
        .hero-sub { font-size: 0.9rem; opacity: 0.75; font-weight: 400; margin-bottom: 34px; position: relative; }

        /* ── Stepper ── */
        .step-stepper {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            max-width: 480px;
            margin: 0 auto;
            position: relative;
        }
        .step-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }
        .step-item:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 14px;
            left: calc(50% + 16px);
            right: -50%;
            height: 2px;
            background: rgba(255,255,255,0.25);
            transition: background 0.3s;
        }
        .step-item.done:not(:last-child)::after { background: rgba(255,255,255,0.7); }
        .step-circle {
            width: 30px; height: 30px;
            border-radius: 50%;
            background: rgba(255,255,255,0.18);
            border: 2px solid rgba(255,255,255,0.38);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.76rem; font-weight: 700;
            color: rgba(255,255,255,0.65);
            position: relative; z-index: 1;
            transition: all 0.26s cubic-bezier(0.4,0,0.2,1);
        }
        .step-item.active .step-circle {
            background: #fff;
            border-color: #fff;
            color: var(--p);
            box-shadow: 0 4px 16px rgba(0,0,0,0.22);
            transform: scale(1.15);
        }
        .step-item.done .step-circle {
            background: rgba(255,255,255,0.82);
            border-color: rgba(255,255,255,0.9);
            color: var(--p);
        }
        .step-label {
            font-size: 0.56rem; text-transform: uppercase; letter-spacing: 0.06em;
            color: rgba(255,255,255,0.5); margin-top: 6px; font-weight: 600;
            text-align: center; line-height: 1.2;
        }
        .step-item.active .step-label { color: rgba(255,255,255,0.95); }
        .step-item.done .step-label { color: rgba(255,255,255,0.72); }

        /* Prestataire link */
        .prestataire-link {
            position: absolute; top: 14px; right: 16px;
            color: rgba(255,255,255,0.68); font-size: 0.72rem; text-decoration: none;
            background: rgba(0,0,0,0.16); padding: 5px 13px; border-radius: 20px;
            transition: all 0.22s; white-space: nowrap; backdrop-filter: blur(4px);
        }
        .prestataire-link:hover { color: #fff; background: rgba(0,0,0,0.32); }

        /* ── Container ── */
        .booking-container {
            max-width: 700px;
            margin: -52px auto 60px;
            padding: 0 15px;
            position: relative;
        }

        /* ── Cards ── */
        .booking-step { display: none; }
        .booking-step.active { display: block; }
        .card {
            border: none;
            box-shadow: 0 4px 28px rgba(0,0,0,0.08);
            border-radius: 18px;
            background: #fff;
        }
        .booking-step.active .step-card {
            animation: slideUp 0.3s cubic-bezier(0.4,0,0.2,1) both;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .step-card { padding: 26px; }

        /* ── Step title ── */
        .step-title {
            display: flex; align-items: center; gap: 12px; margin-bottom: 22px;
        }
        .step-title-icon {
            width: 40px; height: 40px; border-radius: 12px; flex-shrink: 0;
            background: linear-gradient(135deg, var(--p), var(--s));
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 1.05rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.16);
        }
        .step-title h4 { font-size: 1.08rem; font-weight: 700; color: #18182b; margin: 0; }

        /* ── Category label ── */
        .category-label {
            font-size: 0.67rem; color: var(--p); font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.08em;
            margin: 18px 0 8px; padding-left: 3px;
        }
        .category-label:first-child { margin-top: 0; }

        /* ── Service cards ── */
        .service-card {
            cursor: pointer; transition: all 0.22s cubic-bezier(0.4,0,0.2,1);
            border: 1.5px solid #ececec !important;
            border-radius: 12px !important; overflow: hidden; position: relative;
            margin-bottom: 8px; background: #fff;
        }
        .service-card:hover {
            transform: translateY(-2px);
            border-color: var(--p) !important;
            box-shadow: 0 8px 22px rgba(0,0,0,0.09);
        }
        .service-card.selected {
            border-color: var(--p) !important;
            box-shadow: 0 4px 18px rgba(0,0,0,0.08);
        }
        .service-color { width: 5px; position: absolute; left: 0; top: 0; bottom: 0; border-radius: 12px 0 0 12px; }
        .service-card .card-body { padding: 13px 15px 13px 20px; }
        .svc-name { font-weight: 700; font-size: 0.94rem; color: #18182b; margin-bottom: 2px; }
        .svc-desc { font-size: 0.79rem; color: #999; }
        .svc-price { font-weight: 800; color: var(--p); font-size: 1.02rem; }
        .svc-meta { font-size: 0.77rem; color: #bbb; margin-top: 1px; }
        .svc-deposit-badge {
            display: inline-flex; align-items: center; gap: 3px;
            font-size: 0.68rem; padding: 2px 8px; border-radius: 20px;
            background: #fff3cd; color: #856404; margin-top: 4px;
        }

        /* ── Provider cards ── */
        .provider-card {
            cursor: pointer; transition: all 0.22s cubic-bezier(0.4,0,0.2,1);
            border: 1.5px solid #ececec !important; border-radius: 14px !important;
            text-align: center; padding: 22px 14px; height: 100%;
        }
        .provider-card:hover {
            border-color: var(--p) !important;
            transform: translateY(-3px);
            box-shadow: 0 10px 26px rgba(0,0,0,0.09);
        }
        .provider-card.selected { border-color: var(--p) !important; }
        .provider-avatar {
            width: 62px; height: 62px; border-radius: 50%;
            background: linear-gradient(135deg, var(--p), var(--s));
            color: #fff; display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem; font-weight: 700; margin: 0 auto 11px;
            box-shadow: 0 6px 16px rgba(0,0,0,0.18); letter-spacing: -0.02em;
        }
        .prov-name { font-weight: 700; font-size: 0.88rem; color: #18182b; }
        .prov-msg { font-size: 0.75rem; color: #aaa; margin-top: 5px; line-height: 1.4; }

        /* ── Calendar ── */
        .month-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
        .month-nav h5 { margin: 0; font-weight: 700; font-size: 0.98rem; color: #18182b; text-transform: capitalize; }
        .btn-nav {
            width: 34px; height: 34px; border-radius: 50%;
            border: 1.5px solid #e5e5e5; background: #fff;
            display: flex; align-items: center; justify-content: center;
            color: #777; cursor: pointer; transition: all 0.2s; font-size: 0.88rem;
            padding: 0;
        }
        .btn-nav:hover { border-color: var(--p); color: var(--p); background: rgba(233,30,99,0.04); }
        .date-picker { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; }
        .date-cell {
            text-align: center; padding: 8px 3px; border-radius: 10px;
            cursor: pointer; transition: all 0.18s; position: relative;
        }
        .date-cell:not(.disabled):not(.no-slots):hover {
            background: linear-gradient(135deg, rgba(233,30,99,0.07), rgba(156,39,176,0.07));
        }
        .date-cell.selected {
            background: linear-gradient(135deg, var(--p), var(--s));
            color: #fff;
            box-shadow: 0 4px 14px rgba(0,0,0,0.17);
        }
        .date-cell.today:not(.selected)::after {
            content: '';
            display: block;
            width: 4px; height: 4px;
            border-radius: 50%;
            background: var(--p);
            margin: 2px auto 0;
        }
        .date-cell.disabled { opacity: 0.25; pointer-events: none; }
        .date-cell.no-slots { color: #d0d0d0; pointer-events: none; }
        .date-cell.no-slots .day-num { text-decoration: line-through; }
        .date-picker.loading-days .date-cell[data-date]:not(.disabled) { opacity: 0.45; }
        .date-cell .day-name { font-size: 0.58rem; text-transform: uppercase; color: #bbb; font-weight: 600; letter-spacing: 0.03em; }
        .date-cell.selected .day-name { color: rgba(255,255,255,0.72); }
        .date-cell .day-num { font-size: 0.97rem; font-weight: 700; }

        /* section divider */
        .section-sep {
            display: flex; align-items: center; gap: 10px;
            margin: 20px 0 14px; color: #ccc;
        }
        .section-sep::before, .section-sep::after { content: ''; flex: 1; height: 1px; background: #f0f0f0; }
        .section-sep span { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; white-space: nowrap; color: #bbb; }

        /* ── Time slots ── */
        .time-slots-grid { display: flex; flex-wrap: wrap; gap: 8px; padding: 2px 0; }
        .time-slot {
            padding: 9px 18px; border: 1.5px solid #e5e5e5; border-radius: 30px;
            cursor: pointer; transition: all 0.2s cubic-bezier(0.4,0,0.2,1);
            font-weight: 600; font-size: 0.88rem; color: #444; background: #fff;
        }
        .time-slot:hover {
            border-color: var(--p); color: var(--p);
            background: rgba(233,30,99,0.04);
            transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.07);
        }
        .time-slot.selected {
            background: linear-gradient(135deg, var(--p), var(--s));
            border-color: transparent; color: #fff;
            box-shadow: 0 4px 16px rgba(0,0,0,0.18);
            transform: translateY(-1px);
        }

        /* ── Buttons ── */
        .btn-ab {
            background: linear-gradient(135deg, var(--p), var(--s));
            border: none; color: #fff; padding: 12px 28px; border-radius: 30px;
            font-weight: 700; font-size: 0.92rem; letter-spacing: 0.01em;
            box-shadow: 0 4px 16px rgba(0,0,0,0.16);
            transition: all 0.22s cubic-bezier(0.4,0,0.2,1);
        }
        .btn-ab:hover { color: #fff; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.22); }
        .btn-ab:active { transform: translateY(0); }
        .btn-ab-outline {
            border: 1.5px solid #ddd; color: #777; background: #fff;
            padding: 10px 22px; border-radius: 30px;
            font-weight: 600; font-size: 0.89rem;
            transition: all 0.2s;
        }
        .btn-ab-outline:hover { border-color: #bbb; color: #444; background: #f7f7f7; }

        /* ── Forms ── */
        .form-control, .form-select {
            border-radius: 10px; border: 1.5px solid #e8e8e8;
            padding: 10px 14px; font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--p);
            box-shadow: 0 0 0 3px rgba(233,30,99,0.09);
            outline: none;
        }
        .form-label { font-weight: 600; font-size: 0.81rem; color: #666; margin-bottom: 5px; }
        .form-check-input:checked { background-color: var(--p); border-color: var(--p); }
        .form-check-label { font-size: 0.84rem; color: #555; }
        .form-check-label a { color: var(--p); font-weight: 600; }

        /* ── Options ── */
        .options-header {
            font-size: 0.76rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.06em; color: #888; margin-bottom: 10px;
        }
        .option-card {
            display: flex; align-items: flex-start; gap: 10px;
            border: 1.5px solid #eee; border-radius: 10px;
            padding: 11px 14px; margin-bottom: 8px; cursor: pointer;
            transition: all 0.2s; text-decoration: none;
        }
        .option-card:has(.opt-cb:checked) {
            border-color: var(--p);
            background: linear-gradient(90deg, rgba(233,30,99,0.03), #fff);
        }
        .opt-name { font-size: 0.88rem; font-weight: 600; color: #18182b; }
        .opt-price { font-size: 0.78rem; font-weight: 700; color: var(--p); }
        .opt-desc { font-size: 0.76rem; color: #aaa; margin-top: 2px; }
        .options-total {
            background: linear-gradient(135deg, rgba(233,30,99,0.05), rgba(156,39,176,0.05));
            border: 1px solid rgba(233,30,99,0.18); border-radius: 10px;
            padding: 10px 14px; font-size: 0.88rem; font-weight: 600; color: #555; margin-top: 4px;
        }

        /* ── Summary ── */
        .summary-row {
            display: flex; align-items: center; gap: 13px;
            padding: 11px 0; border-bottom: 1px solid #f4f4f4;
        }
        .summary-row:last-child { border-bottom: none; }
        .summary-ico {
            width: 34px; height: 34px; border-radius: 9px; flex-shrink: 0;
            background: linear-gradient(135deg, rgba(233,30,99,0.08), rgba(156,39,176,0.08));
            display: flex; align-items: center; justify-content: center;
            color: var(--p); font-size: 0.88rem;
        }
        .summary-lbl { font-size: 0.7rem; color: #bbb; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; line-height: 1; }
        .summary-val { font-size: 0.94rem; font-weight: 600; color: #18182b; word-break: break-word; line-height: 1.3; }
        .summary-price { font-size: 1.08rem; font-weight: 800; color: var(--p); }

        .info-block {
            border-radius: 12px; padding: 15px 17px; margin-top: 14px;
        }
        .info-block-title { font-size: 0.7rem; color: #bbb; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 7px; }
        .info-block-name { font-weight: 700; font-size: 0.96rem; color: #18182b; }
        .info-block-detail { font-size: 0.83rem; color: #888; margin-top: 2px; }
        .bg-customer { background: linear-gradient(135deg, #f9f9ff, #fef5fb); border: 1px solid #ede8f5; }
        .bg-guardian { background: linear-gradient(135deg, #fffcf0, #fff8e1); border: 1px solid #ffd54f; }
        .bg-options  { background: #f9f9f9; border: 1px solid #f0f0f0; }

        /* ── Deposit ── */
        .deposit-info {
            background: linear-gradient(135deg, #fffcf0, #fff8e1);
            border: 1px solid #ffd54f; border-radius: 12px;
            padding: 15px 17px; margin-top: 14px;
        }

        /* ── Success ── */
        .success-icon {
            width: 82px; height: 82px; border-radius: 50%;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 18px;
            box-shadow: 0 8px 28px rgba(34,197,94,0.32);
            animation: popIn 0.5s cubic-bezier(0.34,1.56,0.64,1) both;
        }
        .success-icon i { font-size: 2.2rem; color: #fff; }
        @keyframes popIn {
            from { transform: scale(0); opacity: 0; }
            to   { transform: scale(1); opacity: 1; }
        }

        /* ── Loading ── */
        .loading-spinner { text-align: center; padding: 38px; color: #ccc; }

        /* ── Mobile ── */
        @media (max-width: 480px) {
            .booking-hero { padding: 32px 14px 80px; }
            .hero-business { font-size: 1.5rem; }
            .step-label { display: none; }
            .step-circle { width: 26px; height: 26px; font-size: 0.68rem; }
            .step-item:not(:last-child)::after { top: 12px; }
            .booking-container { padding: 0 10px; margin-top: -50px; }
            .step-card { padding: 16px 13px; }
            .date-picker { gap: 2px; }
            .date-cell { padding: 5px 2px; border-radius: 7px; }
            .date-cell .day-num { font-size: 0.83rem; }
            .date-cell .day-name { font-size: 0.5rem; }
            .time-slot { padding: 8px 12px; font-size: 0.82rem; }
            .btn-ab, .btn-ab-outline { padding: 10px 18px; font-size: 0.87rem; }
            .mt-4.d-flex, .mt-3.d-flex { flex-wrap: wrap; gap: 8px; }
            .mt-4.d-flex .btn-ab, .mt-3.d-flex .btn-ab-outline { flex: 1; text-align: center; }
            #confirm-btn { width: 100%; }
        }
    </style>
</head>
<body>

<div class="booking-hero">
    <a href="<?= ab_url('admin/index.php') ?>" class="prestataire-link"><i class="bi bi-grid-3x3-gap-fill"></i> Prestataire</a>
    <div class="hero-business"><?= ab_escape($businessName) ?></div>
    <div class="hero-sub">Réservation en ligne · Rapide &amp; sécurisé</div>
    <?php $reviewStats = (new Reviews())->getStats(); if ((int)$reviewStats['total'] > 0): ?>
    <div class="hero-sub" style="margin-top:4px;"><i class="bi bi-star-fill" style="color:#ffc107;"></i> <?= $reviewStats['avg_rating'] ?>/5 <span style="opacity:0.75;">(<?= (int)$reviewStats['total'] ?> avis)</span></div>
    <?php endif; ?>
    <div class="step-stepper">
        <div class="step-item active" data-step="1">
            <div class="step-circle">1</div>
            <div class="step-label">Prestation</div>
        </div>
        <div class="step-item" data-step="2">
            <div class="step-circle">2</div>
            <div class="step-label">Prestataire</div>
        </div>
        <div class="step-item" data-step="3">
            <div class="step-circle">3</div>
            <div class="step-label">Date &amp; Heure</div>
        </div>
        <div class="step-item" data-step="4">
            <div class="step-circle">4</div>
            <div class="step-label">Mes infos</div>
        </div>
        <div class="step-item" data-step="5">
            <div class="step-circle">5</div>
            <div class="step-label">Confirmer</div>
        </div>
    </div>
</div>

<div class="booking-container">
    <?php if (!empty($bookingAnnouncement)): ?>
    <div class="alert alert-info mb-3" style="border-radius:14px; font-size:0.88rem;">
        <i class="bi bi-megaphone-fill"></i> <?= ab_safe_html($bookingAnnouncement) ?>
    </div>
    <?php endif; ?>

    <!-- Step 1: Choose service -->
    <div class="booking-step active" id="step-1">
        <div class="card step-card">
            <div class="step-title">
                <div class="step-title-icon"><i class="bi bi-palette2"></i></div>
                <h4>Choisissez votre prestation</h4>
            </div>
            <?php
            $currentCategory = null;
            foreach ($services as $s):
                if ($s['category_name'] !== $currentCategory):
                    $currentCategory = $s['category_name'];
            ?>
            <div class="category-label"><?= ab_escape($currentCategory ?: 'Autres') ?></div>
            <?php endif; ?>
            <div class="card service-card position-relative overflow-hidden"
                 data-service-id="<?= $s['id'] ?>"
                 data-duration="<?= $s['duration'] ?>"
                 data-price="<?= $s['price'] ?>"
                 data-deposit="<?= $s['deposit_enabled'] ?>"
                 data-deposit-type="<?= $s['deposit_type'] ?>"
                 data-deposit-amount="<?= $s['deposit_amount'] ?>"
                 data-name="<?= ab_escape($s['name']) ?>">
                <div class="service-color" style="background:<?= ab_escape($s['color']) ?>"></div>
                <div class="card-body ps-4">
                    <div class="d-flex justify-content-between align-items-start">
                        <div style="flex:1;min-width:0;">
                            <div class="svc-name"><?= ab_escape($s['name']) ?></div>
                            <?php if ($s['description']): ?>
                            <div class="svc-desc"><?= ab_escape($s['description']) ?></div>
                            <?php endif; ?>
                            <?php if ($s['deposit_enabled']): ?>
                            <span class="svc-deposit-badge"><i class="bi bi-cash-coin"></i> Acompte requis</span>
                            <?php endif; ?>
                        </div>
                        <div class="text-end ms-3" style="flex-shrink:0;">
                            <div class="svc-price"><?= ab_format_price($s['price']) ?></div>
                            <div class="svc-meta"><i class="bi bi-clock"></i> <?= $s['duration'] ?> min</div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Step 2: Choose provider -->
    <div class="booking-step" id="step-2">
        <div class="card step-card">
            <div class="step-title">
                <div class="step-title-icon"><i class="bi bi-person-badge"></i></div>
                <h4>Choisissez votre prestataire</h4>
            </div>
            <div class="row g-3" id="providers-list">
                <?php foreach ($providers as $p): ?>
                <div class="col-6 col-md-4">
                    <div class="card provider-card"
                         data-provider-id="<?= $p['id'] ?>"
                         data-name="<?= ab_escape($p['first_name'] . ' ' . $p['last_name']) ?>"
                         data-message="<?= ab_escape($p['welcome_message'] ?? '') ?>">
                        <div class="provider-avatar"><?= strtoupper(substr($p['first_name'],0,1).substr($p['last_name'],0,1)) ?></div>
                        <div class="prov-name"><?= ab_escape($p['first_name'] . ' ' . $p['last_name']) ?></div>
                        <?php if (!empty($p['welcome_message'])): ?>
                        <div class="prov-msg"><?= ab_safe_html($p['welcome_message']) ?></div>
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
        <div class="card step-card">
            <div class="step-title">
                <div class="step-title-icon"><i class="bi bi-calendar3"></i></div>
                <h4>Choisissez la date et l'heure</h4>
            </div>
            <div class="month-nav">
                <button class="btn-nav" id="prev-month"><i class="bi bi-chevron-left"></i></button>
                <h5 id="month-title"></h5>
                <button class="btn-nav" id="next-month"><i class="bi bi-chevron-right"></i></button>
            </div>
            <div class="date-picker" id="date-picker"></div>
            <div class="section-sep"><span>Créneaux disponibles</span></div>
            <div id="time-slots">
                <p class="text-center" style="color:#bbb;font-size:0.86rem;padding:16px 0 8px;">Sélectionnez une date pour voir les créneaux</p>
            </div>
            <div class="mt-3">
                <button class="btn btn-ab-outline btn-sm" onclick="goToStep(2)"><i class="bi bi-arrow-left"></i> Retour</button>
            </div>
        </div>
    </div>

    <!-- Step 4: Customer info -->
    <div class="booking-step" id="step-4">
        <div class="card step-card">
            <div class="step-title">
                <div class="step-title-icon"><i class="bi bi-person-lines-fill"></i></div>
                <h4>Vos informations</h4>
            </div>
            <form id="booking-form">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Prénom *</label>
                        <input type="text" name="first_name" class="form-control" required id="bf-firstname" placeholder="Jean">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nom *</label>
                        <input type="text" name="last_name" class="form-control" required id="bf-lastname" placeholder="Dupont">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" required id="bf-email" placeholder="votre@email.fr">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Téléphone <?= ab_setting('require_phone', '1') === '1' ? '*' : '' ?></label>
                        <input type="tel" name="phone" class="form-control" id="bf-phone" placeholder="06 00 00 00 00" <?= ab_setting('require_phone', '1') === '1' ? 'required' : '' ?>>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Date de naissance *</label>
                        <input type="text" name="date_of_birth" class="form-control" required id="bf-dob" placeholder="JJ.MM.AAAA" maxlength="10" autocomplete="bday">
                    </div>
                    <div class="col-12" id="dob-warning" style="display:none;">
                        <div class="alert alert-warning py-2 mb-0" style="border-radius:10px;font-size:0.84rem;"><i class="bi bi-exclamation-triangle"></i> Veuillez renseigner votre date de naissance pour continuer.</div>
                    </div>
                    <div class="col-12" id="age-blocked-msg" style="display:none;">
                        <div class="alert alert-danger mb-0" style="border-radius:10px;font-size:0.87rem;">
                            <i class="bi bi-x-circle-fill"></i>
                            <strong>Réservation impossible.</strong>
                            Vous devez avoir au moins <strong><?= $ageMinBooking ?> ans</strong> pour réserver en ligne.
                            Veuillez nous contacter directement.
                        </div>
                    </div>
                    <div class="col-12" id="guardian-section" style="display:none;">
                        <div class="alert alert-warning py-2 mb-2" style="border-radius:10px;font-size:0.85rem;">
                            <i class="bi bi-person-check-fill"></i>
                            En raison de votre âge, un accompagnateur adulte est requis.
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Prénom de l'accompagnateur *</label>
                                <input type="text" class="form-control" id="bf-guardian-firstname" placeholder="Prénom">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nom de l'accompagnateur *</label>
                                <input type="text" class="form-control" id="bf-guardian-lastname" placeholder="Nom">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Téléphone de l'accompagnateur *</label>
                                <input type="tel" class="form-control" id="bf-guardian-phone" placeholder="06...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email de l'accompagnateur *</label>
                                <input type="email" class="form-control" id="bf-guardian-email" placeholder="email@exemple.fr">
                            </div>
                        </div>
                    </div>
                    <div class="col-12" id="options-section" style="display:none;">
                        <div style="height:1px;background:#f2f2f2;margin:4px 0 16px;"></div>
                        <div class="options-header"><i class="bi bi-plus-square"></i> Options disponibles</div>
                        <div id="options-list"></div>
                        <div id="options-total-bar" class="options-total" style="display:none;">
                            <i class="bi bi-calculator"></i> <span id="options-total-text"></span>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes / Remarques</label>
                        <textarea name="notes" class="form-control" rows="2" id="bf-notes" placeholder="Précisez vos souhaits…"></textarea>
                    </div>
                    <?php if ($cgvEnabled || $cgsEnabled): ?>
                    <div class="col-12" id="terms-section">
                        <div style="height:1px;background:#f2f2f2;margin-bottom:12px;"></div>
                        <?php if ($cgvEnabled): ?>
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" id="accept-cgv" required>
                            <label class="form-check-label" for="accept-cgv">
                                J'accepte <?php if ($cgvUrl): ?><a href="<?= ab_escape($cgvUrl) ?>" target="_blank" rel="noopener"><?= ab_escape($cgvLabel) ?></a><?php else: ?><?= ab_escape($cgvLabel) ?><?php endif; ?> *
                            </label>
                        </div>
                        <?php endif; ?>
                        <?php if ($cgsEnabled): ?>
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" id="accept-cgs" required>
                            <label class="form-check-label" for="accept-cgs">
                                J'accepte <?php if ($cgsUrl): ?><a href="<?= ab_escape($cgsUrl) ?>" target="_blank" rel="noopener"><?= ab_escape($cgsLabel) ?></a><?php else: ?><?= ab_escape($cgsLabel) ?><?php endif; ?> *
                            </label>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="mt-4 d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-ab-outline btn-sm" onclick="goToStep(3)"><i class="bi bi-arrow-left"></i> Retour</button>
                    <button type="submit" class="btn btn-ab"><i class="bi bi-arrow-right"></i> Continuer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Step 5: Summary & Confirm -->
    <div class="booking-step" id="step-5">
        <div class="card step-card">
            <div class="step-title">
                <div class="step-title-icon"><i class="bi bi-check2-all"></i></div>
                <h4>Récapitulatif</h4>
            </div>

            <div>
                <div class="summary-row">
                    <div class="summary-ico"><i class="bi bi-palette2"></i></div>
                    <div style="min-width:0;">
                        <div class="summary-lbl">Prestation</div>
                        <div class="summary-val" id="sum-service"></div>
                    </div>
                </div>
                <div class="summary-row">
                    <div class="summary-ico"><i class="bi bi-person-badge"></i></div>
                    <div style="min-width:0;">
                        <div class="summary-lbl">Prestataire</div>
                        <div class="summary-val" id="sum-provider"></div>
                    </div>
                </div>
                <div class="summary-row">
                    <div class="summary-ico"><i class="bi bi-calendar3"></i></div>
                    <div style="min-width:0;">
                        <div class="summary-lbl">Date</div>
                        <div class="summary-val" id="sum-date"></div>
                    </div>
                </div>
                <div class="summary-row">
                    <div class="summary-ico"><i class="bi bi-clock"></i></div>
                    <div style="min-width:0;">
                        <div class="summary-lbl">Heure &amp; Durée</div>
                        <div class="summary-val"><span id="sum-time"></span> &middot; <span id="sum-duration"></span></div>
                    </div>
                </div>
                <div class="summary-row">
                    <div class="summary-ico"><i class="bi bi-tag"></i></div>
                    <div style="min-width:0;">
                        <div class="summary-lbl">Prix</div>
                        <div class="summary-price" id="sum-price"></div>
                    </div>
                </div>
            </div>

            <div id="sum-deposit" style="display:none;">
                <div class="deposit-info">
                    <div class="fw-bold mb-1" style="font-size:0.9rem;"><i class="bi bi-cash-coin"></i> Acompte requis</div>
                    <p class="mb-1" style="font-size:0.88rem;">Un acompte de <strong id="sum-deposit-amount"></strong> est requis pour confirmer votre rendez-vous.</p>
                    <p class="mb-0" style="font-size:0.8rem;color:#888;">Les instructions de paiement vous seront envoyées par email.</p>
                </div>
            </div>

            <div class="info-block bg-customer" id="sum-customer">
                <div class="info-block-title"><i class="bi bi-person"></i> Vos coordonnées</div>
                <div class="info-block-name" id="sum-name"></div>
                <div class="info-block-detail" id="sum-email"></div>
                <div class="info-block-detail" id="sum-phone"></div>
                <div class="info-block-detail"><span style="color:#ccc;font-size:0.76rem;">Naissance : </span><span id="sum-dob"></span></div>
            </div>

            <div id="sum-options" class="info-block bg-options" style="display:none;">
                <div class="info-block-title"><i class="bi bi-plus-square"></i> Options sélectionnées</div>
                <div id="sum-options-list"></div>
                <div class="d-flex justify-content-between fw-bold mt-2 pt-2" style="border-top:1px solid #eee;font-size:0.92rem;">
                    <span style="color:#666;">Total</span>
                    <span style="color:var(--p);" id="sum-total-price"></span>
                </div>
            </div>

            <div id="sum-guardian" class="info-block bg-guardian" style="display:none;">
                <div class="info-block-title"><i class="bi bi-person-check-fill"></i> Accompagnateur adulte</div>
                <div class="info-block-name" id="sum-guardian-name"></div>
                <div class="info-block-detail" id="sum-guardian-email"></div>
                <div class="info-block-detail" id="sum-guardian-phone"></div>
            </div>

            <div class="mt-4 d-flex justify-content-between align-items-center">
                <button class="btn btn-ab-outline btn-sm" onclick="goToStep(4)"><i class="bi bi-arrow-left"></i> Modifier</button>
                <button class="btn btn-ab btn-lg" id="confirm-btn" onclick="confirmBooking()">
                    <i class="bi bi-check-circle"></i> Confirmer le rendez-vous
                </button>
            </div>
        </div>
    </div>

    <!-- Success -->
    <div class="booking-step" id="step-success">
        <div class="card step-card text-center" style="padding:40px 26px;">
            <div class="success-icon"><i class="bi bi-check-lg"></i></div>
            <h3 style="font-weight:800;color:#18182b;margin-bottom:8px;">Rendez-vous enregistré !</h3>
            <p class="text-muted" id="success-message" style="font-size:0.93rem;">Vous recevrez un email de confirmation.</p>
            <div id="success-deposit" style="display:none;" class="deposit-info text-start mx-auto mt-3" style="max-width:500px;">
                <div class="fw-bold mb-1" style="font-size:0.9rem;"><i class="bi bi-cash-coin"></i> Acompte à régler</div>
                <p id="success-deposit-text" style="font-size:0.88rem;margin:0;"></p>
            </div>
            <div class="mt-4">
                <a href="<?= ab_url('public/') ?>" class="btn btn-ab">Prendre un autre rendez-vous</a>
            </div>
        </div>
    </div>
</div>

<?php $publicReviews = (new Reviews())->getApproved(6); if (!empty($publicReviews)): ?>
<div class="booking-container" style="margin-top:0;">
    <h5 class="text-center mb-3" style="color:#888;font-weight:700;font-size:0.95rem;">Ce que disent nos clients</h5>
    <div class="row g-3 mb-3">
        <?php foreach ($publicReviews as $rv): ?>
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm" style="border-radius:14px;">
                <div class="card-body">
                    <div style="color:#ffc107;font-size:0.95rem;"><?= str_repeat('★', (int)$rv['rating']) . str_repeat('☆', 5 - (int)$rv['rating']) ?></div>
                    <?php if (!empty($rv['comment'])): ?>
                    <p class="mt-2 mb-2" style="font-size:0.85rem;color:#555;"><?= nl2br(ab_escape($rv['comment'])) ?></p>
                    <?php endif; ?>
                    <div class="small text-muted">— <?= ab_escape($rv['customer_first_name']) ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<footer style="text-align:center;padding:20px;color:#ccc;font-size:0.78rem;">
    <?= ab_escape($businessName) ?> &middot; <?= ab_escape(ab_setting('business_phone')) ?>
</footer>

<?php if ($modalEnabled && !empty($modalMessage)): ?>
<div class="modal fade" id="importantModal" tabindex="-1" aria-labelledby="importantModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:18px;overflow:hidden;border:none;box-shadow:0 20px 60px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background:linear-gradient(135deg,var(--p),var(--s));color:#fff;border:none;">
                <h5 class="modal-title" id="importantModalLabel" style="font-weight:700;"><i class="bi bi-exclamation-triangle-fill"></i> Information importante</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body" style="padding:24px;font-size:1rem;line-height:1.6;">
                <?= ab_safe_html($modalMessage) ?>
            </div>
            <div class="modal-footer" style="border:none;padding:16px 24px;">
                <button type="button" class="btn btn-ab" data-bs-dismiss="modal">J'ai compris</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="waitlistModal" tabindex="-1" aria-labelledby="waitlistModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:18px;overflow:hidden;border:none;box-shadow:0 20px 60px rgba(0,0,0,0.15);">
            <form id="waitlist-form">
                <div class="modal-header" style="background:linear-gradient(135deg,var(--p),var(--s));color:#fff;border:none;">
                    <h5 class="modal-title" id="waitlistModalLabel" style="font-weight:700;"><i class="bi bi-bell"></i> Liste d'attente</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body" style="padding:24px;">
                    <p class="text-muted small">Nous vous préviendrons par email dès qu'un créneau se libère sur la période choisie.</p>
                    <div id="waitlist-alert" class="alert d-none" role="alert"></div>
                    <div class="mb-2">
                        <label class="form-label">Prénom</label>
                        <input type="text" class="form-control" name="first_name" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Nom</label>
                        <input type="text" class="form-control" name="last_name" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Téléphone</label>
                        <input type="tel" class="form-control" name="phone">
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Du</label>
                            <input type="date" class="form-control" name="desired_date_start" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Au</label>
                            <input type="date" class="form-control" name="desired_date_end" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border:none;padding:16px 24px;">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-ab"><i class="bi bi-bell"></i> M'inscrire</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openWaitlistModal() {
    const modalEl = document.getElementById('waitlistModal');
    const alertEl = document.getElementById('waitlist-alert');
    alertEl.classList.add('d-none');
    document.getElementById('waitlist-form').reset();
    const startInput = document.querySelector('#waitlist-form [name="desired_date_start"]');
    const endInput = document.querySelector('#waitlist-form [name="desired_date_end"]');
    if (booking.date) {
        startInput.value = booking.date;
        const endDate = new Date(booking.date);
        endDate.setDate(endDate.getDate() + 14);
        endInput.value = endDate.toISOString().slice(0, 10);
    }
    new bootstrap.Modal(modalEl).show();
}

document.getElementById('waitlist-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const alertEl = document.getElementById('waitlist-alert');
    const formData = new FormData(this);
    const payload = Object.fromEntries(formData.entries());
    payload.service_id = booking.serviceId;
    payload.provider_id = booking.providerId;

    fetch(API_URL + '?route=waitlist-join', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                alertEl.className = 'alert alert-danger';
                alertEl.textContent = data.error;
                alertEl.classList.remove('d-none');
                return;
            }
            bootstrap.Modal.getInstance(document.getElementById('waitlistModal')).hide();
            alert('Vous avez bien été inscrit(e) à la liste d\'attente. Nous vous contacterons par email dès qu\'un créneau se libère.');
        })
        .catch(() => {
            alertEl.className = 'alert alert-danger';
            alertEl.textContent = 'Une erreur est survenue, veuillez réessayer.';
            alertEl.classList.remove('d-none');
        });
});
</script>
<?php if ($modalEnabled && !empty($modalMessage)): ?>
<script>
(function() {
    const maxViews = <?= $modalMaxViews ?>;
    const msgHash = '<?= md5($modalMessage) ?>';
    const storageKey = 'ab_modal_' + msgHash;
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
let booking = { serviceId: null, providerId: null, date: null, time: null, serviceName: '', providerName: '', duration: 0, price: 0, deposit: false, depositType: '', depositAmount: 0, dateOfBirth: '', guardian: null, selectedOptions: [] };
let currentMonth = new Date();
let availableDaysCache = {};

function goToStep(n) {
    document.querySelectorAll('.booking-step').forEach(s => s.classList.remove('active'));
    document.getElementById('step-' + n).classList.add('active');
    document.querySelectorAll('.step-item').forEach(d => {
        const step = parseInt(d.dataset.step);
        const isDone = step < n;
        const isActive = step === n;
        d.classList.toggle('active', isActive);
        d.classList.toggle('done', isDone);
        const circle = d.querySelector('.step-circle');
        if (isDone) {
            circle.innerHTML = '<i class="bi bi-check2" style="font-size:0.82rem;"></i>';
        } else {
            circle.textContent = step;
        }
    });
    window.scrollTo({ top: 0, behavior: 'smooth' });
    if (n === 4) loadBookingOptions();
}

function loadBookingOptions() {
    if (!booking.serviceId) return;
    const section = document.getElementById('options-section');
    const list = document.getElementById('options-list');
    list.innerHTML = '<div class="text-muted small py-2"><i class="bi bi-hourglass-split"></i> Chargement…</div>';
    section.style.display = 'block';
    fetch(API_URL + '?route=booking-options&service_id=' + booking.serviceId)
        .then(r => r.json())
        .then(data => {
            const opts = data.options || [];
            if (!opts.length) { section.style.display = 'none'; return; }
            let html = '';
            opts.forEach(opt => {
                const pv = parseFloat(opt.price_value);
                const priceLabel = pv === 0 ? 'Gratuit'
                    : (opt.price_type === 'percentage' ? '+' + pv + '%' : '+' + new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(pv));
                html += '<label class="option-card" for="opt-' + opt.id + '">'
                    + '<input class="form-check-input opt-cb flex-shrink-0" type="checkbox" id="opt-' + opt.id + '" value="' + opt.id + '"'
                    + ' data-price-type="' + opt.price_type + '" data-price-value="' + pv + '" data-name="' + opt.name.replace(/"/g, '&quot;') + '"'
                    + ' style="margin-top:3px;">'
                    + '<div style="flex:1;min-width:0;">'
                    + '<div class="d-flex justify-content-between align-items-start">'
                    + '<span class="opt-name">' + opt.name.replace(/</g,'&lt;') + '</span>'
                    + '<span class="opt-price ms-2">' + priceLabel + '</span>'
                    + '</div>'
                    + (opt.description ? '<div class="opt-desc">' + opt.description.replace(/</g,'&lt;') + '</div>' : '')
                    + '</div>'
                    + '</label>';
            });
            list.innerHTML = html;
            document.querySelectorAll('.opt-cb').forEach(cb => cb.addEventListener('change', updateOptionsTotal));
            updateOptionsTotal();
        })
        .catch(() => { section.style.display = 'none'; });
}

function updateOptionsTotal() {
    const checks = document.querySelectorAll('.opt-cb:checked');
    let total = 0;
    booking.selectedOptions = [];
    checks.forEach(cb => {
        const pv = parseFloat(cb.dataset.priceValue);
        const price = cb.dataset.priceType === 'percentage' ? booking.price * pv / 100 : pv;
        total += price;
        booking.selectedOptions.push({ id: parseInt(cb.value, 10), name: cb.dataset.name, price: price });
    });
    const bar = document.getElementById('options-total-bar');
    const txt = document.getElementById('options-total-text');
    if (total > 0) {
        const fmt = n => new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(n);
        txt.textContent = 'Prestation ' + fmt(booking.price) + ' + options ' + fmt(total) + ' = Total ' + fmt(booking.price + total);
        bar.style.display = 'block';
    } else {
        bar.style.display = 'none';
    }
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
    for (let i = 0; i < firstDay.getDay(); i++) html += '<div class="date-cell disabled"></div>';

    for (let d = 1; d <= lastDay.getDate(); d++) {
        const date = new Date(year, month, d);
        const dateStr = year + '-' + String(month+1).padStart(2,'0') + '-' + String(d).padStart(2,'0');
        const isPast = date < today;
        const isToday = date.getTime() === today.getTime();
        const selected = booking.date === dateStr;
        const classes = ['date-cell', isPast ? 'disabled' : '', isToday ? 'today' : '', selected ? 'selected' : ''].filter(Boolean).join(' ');
        html += '<div class="' + classes + '" data-date="' + dateStr + '"><div class="day-num">' + d + '</div></div>';
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
        .catch(() => {})
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
    document.getElementById('time-slots').innerHTML = '<div class="loading-spinner"><div class="spinner-border text-secondary"></div></div>';
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
                document.getElementById('time-slots').innerHTML = '<p class="text-danger text-center" style="font-size:0.88rem;">' + data.error + '</p>';
                return;
            }
            if (!data.slots || data.slots.length === 0) {
                let debugHtml = '';
                if (data.debug) {
                    debugHtml = '<pre class="text-start small mt-2 p-2 bg-light" style="font-size:11px;">' + JSON.stringify(data.debug, null, 2) + '</pre>';
                }
                document.getElementById('time-slots').innerHTML = '<p class="text-center" style="color:#bbb;font-size:0.86rem;padding:12px 0;">Aucun créneau disponible ce jour</p>'
                    + '<p class="text-center"><button type="button" class="btn btn-outline-secondary btn-sm" onclick="openWaitlistModal()"><i class="bi bi-bell"></i> Me prévenir si un créneau se libère</button></p>'
                    + debugHtml;
                return;
            }
            let html = '<div class="time-slots-grid">';
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
            document.getElementById('time-slots').innerHTML = '<p class="text-danger text-center" style="font-size:0.88rem;">Erreur : ' + (err.message || 'vérifiez la console') + '</p>';
        });
}

// Age check
const ageCheckEnabled = <?= $ageCheckEnabled ? 'true' : 'false' ?>;
const ageMinBooking = <?= $ageMinBooking ?>;
const ageMinSolo = <?= $ageMinSolo ?>;

function getAgeYears(dobStr) {
    const p = dobStr.split('.');
    if (p.length !== 3) return null;
    const dob = new Date(parseInt(p[2],10), parseInt(p[1],10)-1, parseInt(p[0],10));
    if (isNaN(dob.getTime())) return null;
    const now = new Date();
    let age = now.getFullYear() - dob.getFullYear();
    const m = now.getMonth() - dob.getMonth();
    if (m < 0 || (m === 0 && now.getDate() < dob.getDate())) age--;
    return age;
}

function evaluateAgeStatus(dobStr) {
    if (!ageCheckEnabled || !/^\d{2}\.\d{2}\.\d{4}$/.test(dobStr)) return 'ok';
    const age = getAgeYears(dobStr);
    if (age === null) return 'ok';
    if (ageMinBooking > 0 && age < ageMinBooking) return 'blocked';
    if (ageMinSolo > 0 && age < ageMinSolo) return 'needs_guardian';
    return 'ok';
}

function updateAgeUI() {
    const dob = document.getElementById('bf-dob').value.trim();
    const status = evaluateAgeStatus(dob);
    document.getElementById('age-blocked-msg').style.display = status === 'blocked' ? 'block' : 'none';
    document.getElementById('guardian-section').style.display = status === 'needs_guardian' ? 'block' : 'none';
}

document.getElementById('bf-dob').addEventListener('input', function() {
    let v = this.value.replace(/\D/g, '');
    if (v.length > 2) v = v.substring(0,2) + '.' + v.substring(2);
    if (v.length > 5) v = v.substring(0,5) + '.' + v.substring(5);
    this.value = v.substring(0, 10);
    updateAgeUI();
});

document.getElementById('bf-email').addEventListener('blur', function() {
    const email = this.value.trim();
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return;
    fetch(API_URL + '?route=check-customer&email=' + encodeURIComponent(email))
        .then(r => r.json())
        .then(data => {
            const warn = document.getElementById('dob-warning');
            warn.style.display = (data.exists && !data.has_dob) ? 'block' : 'none';
        })
        .catch(() => {});
});

// Step 4: Form submit
document.getElementById('booking-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const dob = document.getElementById('bf-dob').value.trim();
    if (!/^\d{2}\.\d{2}\.\d{4}$/.test(dob)) {
        document.getElementById('bf-dob').focus();
        document.getElementById('bf-dob').setCustomValidity('Veuillez entrer une date au format JJ.MM.AAAA');
        document.getElementById('bf-dob').reportValidity();
        return;
    }
    document.getElementById('bf-dob').setCustomValidity('');

    const ageStatus = evaluateAgeStatus(dob);
    if (ageStatus === 'blocked') {
        document.getElementById('age-blocked-msg').style.display = 'block';
        document.getElementById('age-blocked-msg').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }
    if (ageStatus === 'needs_guardian') {
        const gFirst = document.getElementById('bf-guardian-firstname').value.trim();
        const gLast  = document.getElementById('bf-guardian-lastname').value.trim();
        const gPhone = document.getElementById('bf-guardian-phone').value.trim();
        const gEmail = document.getElementById('bf-guardian-email').value.trim();
        if (!gFirst || !gLast || !gPhone || !gEmail) {
            document.getElementById('guardian-section').scrollIntoView({ behavior: 'smooth', block: 'center' });
            alert('Veuillez remplir toutes les informations de l\'accompagnateur.');
            return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(gEmail)) {
            alert('L\'email de l\'accompagnateur est invalide.');
            return;
        }
        booking.guardian = { firstName: gFirst, lastName: gLast, phone: gPhone, email: gEmail };
    } else {
        booking.guardian = null;
    }

    const cgvBox = document.getElementById('accept-cgv');
    const cgsBox = document.getElementById('accept-cgs');
    if (cgvBox && !cgvBox.checked) {
        cgvBox.setCustomValidity('Vous devez accepter les CGV pour continuer.');
        cgvBox.reportValidity();
        return;
    }
    if (cgvBox) cgvBox.setCustomValidity('');
    if (cgsBox && !cgsBox.checked) {
        cgsBox.setCustomValidity('Vous devez accepter les CGS pour continuer.');
        cgsBox.reportValidity();
        return;
    }
    if (cgsBox) cgsBox.setCustomValidity('');

    showSummary();
    goToStep(5);
});

function showSummary() {
    const dateObj = new Date(booking.date + 'T00:00:00');
    const dateStr = dateObj.toLocaleDateString('fr-FR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    const fmt = n => new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(n);

    document.getElementById('sum-service').textContent = booking.serviceName;
    document.getElementById('sum-provider').textContent = booking.providerName;
    document.getElementById('sum-date').textContent = dateStr;
    document.getElementById('sum-time').textContent = booking.time;
    document.getElementById('sum-duration').textContent = booking.duration + ' min';
    document.getElementById('sum-price').textContent = fmt(booking.price);

    document.getElementById('sum-name').textContent = document.getElementById('bf-firstname').value + ' ' + document.getElementById('bf-lastname').value;
    document.getElementById('sum-email').textContent = document.getElementById('bf-email').value;
    document.getElementById('sum-phone').textContent = document.getElementById('bf-phone').value;
    document.getElementById('sum-dob').textContent = document.getElementById('bf-dob').value;

    if (booking.selectedOptions.length > 0) {
        let optHtml = '';
        let optTotal = 0;
        booking.selectedOptions.forEach(o => {
            optTotal += o.price;
            optHtml += '<div class="d-flex justify-content-between small mb-1"><span>' + o.name.replace(/</g,'&lt;') + '</span><span>+' + fmt(o.price) + '</span></div>';
        });
        document.getElementById('sum-options-list').innerHTML = optHtml;
        document.getElementById('sum-total-price').textContent = fmt(booking.price + optTotal);
        document.getElementById('sum-options').style.display = 'block';
    } else {
        document.getElementById('sum-options').style.display = 'none';
    }

    if (booking.guardian) {
        document.getElementById('sum-guardian').style.display = 'block';
        document.getElementById('sum-guardian-name').textContent = booking.guardian.firstName + ' ' + booking.guardian.lastName;
        document.getElementById('sum-guardian-email').textContent = booking.guardian.email;
        document.getElementById('sum-guardian-phone').textContent = booking.guardian.phone;
    } else {
        document.getElementById('sum-guardian').style.display = 'none';
    }

    if (booking.deposit) {
        const depAmount = booking.depositType === 'percentage' ? booking.price * booking.depositAmount / 100 : booking.depositAmount;
        document.getElementById('sum-deposit-amount').textContent = fmt(depAmount);
        document.getElementById('sum-deposit').style.display = 'block';
    } else {
        document.getElementById('sum-deposit').style.display = 'none';
    }
}

// Step 5: Confirm
function confirmBooking() {
    const btn = document.getElementById('confirm-btn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Confirmation…';

    const body = {
        service_id: booking.serviceId,
        provider_id: booking.providerId,
        date: booking.date,
        time: booking.time,
        first_name: document.getElementById('bf-firstname').value,
        last_name: document.getElementById('bf-lastname').value,
        email: document.getElementById('bf-email').value,
        phone: document.getElementById('bf-phone').value,
        notes: document.getElementById('bf-notes').value,
        date_of_birth: document.getElementById('bf-dob').value,
        guardian: booking.guardian ? {
            first_name: booking.guardian.firstName,
            last_name: booking.guardian.lastName,
            phone: booking.guardian.phone,
            email: booking.guardian.email,
        } : null,
        options: booking.selectedOptions.map(o => o.id),
    };

    fetch(API_URL + '?route=book', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (data.needs_guardian) {
                document.getElementById('success-message').textContent = 'Votre demande est enregistrée. Un email a été envoyé à votre accompagnateur pour confirmer sa présence. Votre rendez-vous sera validé dès sa confirmation.';
            } else if (data.deposit) {
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
