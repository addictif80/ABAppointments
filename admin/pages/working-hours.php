<?php
$db = Database::getInstance();
$providerId = Auth::isAdmin() ? (int)($_GET['provider'] ?? Auth::userId()) : Auth::userId();
$providers = Auth::isAdmin() ? $db->fetchAll("SELECT * FROM ab_users WHERE is_active = 1 ORDER BY first_name") : [];

$days = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Delete existing hours
    $db->delete('ab_working_hours', 'provider_id = ?', [$providerId]);
    $db->delete('ab_breaks', 'provider_id = ?', [$providerId]);

    for ($d = 0; $d < 7; $d++) {
        if (isset($_POST['active'][$d])) {
            $data = [
                'provider_id' => $providerId,
                'day_of_week' => $d,
                'start_time' => $_POST['start'][$d] ?? '09:00',
                'end_time' => $_POST['end'][$d] ?? '18:00',
                'break_start' => !empty($_POST['break_start'][$d]) ? $_POST['break_start'][$d] : null,
                'break_end' => !empty($_POST['break_end'][$d]) ? $_POST['break_end'][$d] : null,
                'is_active' => 1,
            ];
            $db->insert('ab_working_hours', $data);
        }
    }

    ab_flash('success', 'Horaires enregistrés.');
    ab_redirect(ab_url('admin/index.php?page=working-hours' . (Auth::isAdmin() ? '&provider=' . $providerId : '')));
}

$workingHours = $db->fetchAll("SELECT * FROM ab_working_hours WHERE provider_id = ?", [$providerId]);
$hoursMap = [];
foreach ($workingHours as $wh) {
    $hoursMap[$wh['day_of_week']] = $wh;
}
?>

<h4 class="mb-3"><i class="bi bi-clock"></i> Horaires de travail</h4>

<?php if (Auth::isAdmin() && count($providers) > 1): ?>
<div class="card mb-3">
    <div class="card-body py-2">
        <form class="d-flex gap-2 align-items-center" method="GET">
            <input type="hidden" name="page" value="working-hours">
            <label class="form-label mb-0 me-2">Prestataire :</label>
            <select name="provider" class="form-select form-select-sm" style="max-width:250px;" onchange="this.form.submit()">
                <?php foreach ($providers as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $p['id'] == $providerId ? 'selected' : '' ?>><?= ab_escape($p['first_name'] . ' ' . $p['last_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST">
            <?= Auth::csrfField() ?>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Jour</th><th>Actif</th><th>Début</th><th>Fin</th><th>Pause début</th><th>Pause fin</th></tr></thead>
                    <tbody>
                    <?php for ($d = 0; $d < 7; $d++): $h = $hoursMap[$d] ?? null; ?>
                    <tr>
                        <td><strong><?= $days[$d] ?></strong></td>
                        <td><input type="checkbox" name="active[<?= $d ?>]" class="form-check-input" <?= ($h && $h['is_active']) ? 'checked' : '' ?> <?= in_array($d, [1,2,3,4,5]) && !$h ? 'checked' : '' ?>></td>
                        <td><input type="time" name="start[<?= $d ?>]" class="form-control form-control-sm" value="<?= $h['start_time'] ?? '09:00' ?>" step="900"></td>
                        <td><input type="time" name="end[<?= $d ?>]" class="form-control form-control-sm" value="<?= $h['end_time'] ?? '18:00' ?>" step="900"></td>
                        <td><input type="time" name="break_start[<?= $d ?>]" class="form-control form-control-sm" value="<?= $h['break_start'] ?? '12:00' ?>" step="900"></td>
                        <td><input type="time" name="break_end[<?= $d ?>]" class="form-control form-control-sm" value="<?= $h['break_end'] ?? '13:00' ?>" step="900"></td>
                    </tr>
                    <?php endfor; ?>
                    </tbody>
                </table>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check"></i> Enregistrer les horaires</button>
        </form>
    </div>
</div>
