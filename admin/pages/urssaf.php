<?php
Auth::requireAdmin();
$db = Database::getInstance();

// Stream a generated PDF to the browser (admin only).
if (($_GET['action'] ?? '') === 'download' && isset($_GET['id'])) {
    $declaration = $db->fetchOne("SELECT * FROM ab_urssaf_declarations WHERE id = ?", [(int) $_GET['id']]);
    $path = $declaration ? __DIR__ . '/../../storage/urssaf_declarations/' . $declaration['pdf_filename'] : null;
    if (!$declaration || !$path || !is_file($path)) {
        http_response_code(404);
        exit('Fichier non trouvé.');
    }
    header('Content-Type: application/pdf');
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: inline; filename="' . basename($path) . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    $year = (int) ($_POST['year'] ?? date('Y'));
    $month = (int) ($_POST['month'] ?? date('n'));
    $sendEmail = isset($_POST['send_email']);

    $report = new UrssafReport();
    $data = $report->getData($year, $month);
    $pdfPath = $report->generateAndStore($year, $month);

    $recipient = ab_setting('urssaf_recipient_email') ?: ab_setting('business_email');
    $sent = false;
    if ($sendEmail && !empty($recipient)) {
        $mailer = new Mailer();
        $sent = $mailer->sendTemplate('urssaf_declaration', $recipient, [
            'business_name' => ab_setting('business_name'),
            'period_label' => $data['label'],
            'appointment_count' => (string) $data['count'],
            'total_amount' => ab_format_price($data['total']),
        ], '', [[
            'filename' => basename($pdfPath),
            'content' => file_get_contents($pdfPath),
            'mime' => 'application/pdf',
        ]]);
        if (!$sent) {
            ab_flash('error', "PDF généré mais l'email n'a pas pu être envoyé : " . $mailer->getLastError());
        }
    }

    $recordData = [
        'total_amount' => $data['total'],
        'appointment_count' => $data['count'],
        'recipient_email' => $recipient,
        'pdf_filename' => basename($pdfPath),
    ];
    $existing = $db->fetchOne("SELECT id FROM ab_urssaf_declarations WHERE period_year = ? AND period_month = ?", [$year, $month]);
    if ($sent) {
        $recordData['sent_at'] = date('Y-m-d H:i:s');
    }
    if ($existing) {
        $db->update('ab_urssaf_declarations', $recordData, 'id = ?', [$existing['id']]);
    } else {
        $recordData['period_year'] = $year;
        $recordData['period_month'] = $month;
        $db->insert('ab_urssaf_declarations', $recordData);
    }

    if ($sendEmail && $sent) {
        ab_flash('success', 'PDF généré et envoyé par email à ' . $recipient . '.');
    } elseif (!$sendEmail) {
        ab_flash('success', 'PDF généré. Téléchargez-le depuis l\'historique ci-dessous.');
    }
    ab_redirect(ab_url('admin/index.php?page=urssaf'));
}

$declarations = $db->fetchAll("SELECT * FROM ab_urssaf_declarations ORDER BY period_year DESC, period_month DESC");
$report = new UrssafReport();
$prevMonthTs = strtotime('first day of last month');
?>

<h4 class="mb-3"><i class="bi bi-file-earmark-pdf"></i> Déclaration URSSAF (chiffre d'affaires mensuel)</h4>

<div class="alert alert-info">
    <i class="bi bi-info-circle"></i>
    Le PDF détaille les rendez-vous confirmés/terminés du mois choisi et leur montant total, à joindre ou reprendre pour
    votre déclaration sur autoentrepreneur.urssaf.fr. L'envoi automatique se configure dans
    <a href="<?= ab_url('admin/index.php?page=settings&tab=urssaf') ?>">Paramètres &gt; URSSAF</a>.
</div>

<div class="card mb-4">
    <div class="card-header bg-white"><strong>Générer un rapport manuellement</strong></div>
    <div class="card-body">
        <form method="POST" class="row g-3 align-items-end">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="generate">
            <div class="col-md-3">
                <label class="form-label">Mois</label>
                <select name="month" class="form-select">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m === (int) date('n', $prevMonthTs) ? 'selected' : '' ?>><?= $report->monthName($m) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Année</label>
                <input type="number" name="year" class="form-control" value="<?= date('Y', $prevMonthTs) ?>" min="2020" max="2100">
            </div>
            <div class="col-md-4">
                <div class="form-check form-switch mt-4">
                    <input type="checkbox" name="send_email" class="form-check-input" id="sendEmail" checked>
                    <label class="form-check-label" for="sendEmail">Envoyer par email après génération</label>
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-gear"></i> Générer</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white"><strong>Historique</strong></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Période</th><th>RDV</th><th>Chiffre d'affaires</th><th>Destinataire</th><th>Statut</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($declarations as $d): ?>
            <tr>
                <td><?= ab_escape($report->monthLabel((int) $d['period_year'], (int) $d['period_month'])) ?></td>
                <td><?= (int) $d['appointment_count'] ?></td>
                <td><strong><?= ab_format_price((float) $d['total_amount']) ?></strong></td>
                <td><?= ab_escape($d['recipient_email'] ?? '-') ?></td>
                <td>
                    <?php if ($d['sent_at']): ?>
                    <span class="badge bg-success">Envoyé le <?= ab_format_date($d['sent_at']) ?></span>
                    <?php else: ?>
                    <span class="badge bg-secondary">Non envoyé</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($d['pdf_filename']): ?>
                    <a href="<?= ab_url('admin/index.php?page=urssaf&action=download&id=' . $d['id']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i> PDF</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($declarations)): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">Aucune déclaration générée</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
