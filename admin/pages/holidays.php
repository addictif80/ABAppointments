<?php
$db = Database::getInstance();
$providerId = Auth::isAdmin() ? null : Auth::userId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    if ($postAction === 'save') {
        $data = [
            'provider_id' => !empty($_POST['provider_id']) ? (int)$_POST['provider_id'] : null,
            'title' => trim($_POST['title']),
            'date_start' => $_POST['date_start'],
            'date_end' => $_POST['date_end'],
        ];
        if (!empty($_POST['id'])) {
            $db->update('ab_holidays', $data, 'id = ?', [(int)$_POST['id']]);
        } else {
            $db->insert('ab_holidays', $data);
        }
        ab_flash('success', 'Congé enregistré.');
        ab_redirect(ab_url('admin/index.php?page=holidays'));
    }
    if ($postAction === 'delete' && isset($_POST['id'])) {
        $db->delete('ab_holidays', 'id = ?', [(int)$_POST['id']]);
        ab_flash('success', 'Congé supprimé.');
        ab_redirect(ab_url('admin/index.php?page=holidays'));
    }
}

$sql = "SELECT h.*, u.first_name, u.last_name FROM ab_holidays h LEFT JOIN ab_users u ON h.provider_id = u.id";
if ($providerId) $sql .= " WHERE h.provider_id = $providerId OR h.provider_id IS NULL";
$sql .= " ORDER BY h.date_start DESC";
$holidays = $db->fetchAll($sql);
$providers = Auth::isAdmin() ? $db->fetchAll("SELECT * FROM ab_users WHERE is_active = 1 ORDER BY first_name") : [];
$editHoliday = isset($_GET['edit']) ? $db->fetchOne("SELECT * FROM ab_holidays WHERE id = ?", [(int)$_GET['edit']]) : null;
?>

<h4 class="mb-3"><i class="bi bi-calendar-x"></i> Congés & Jours fériés</h4>
<div class="row">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header bg-white"><h6 class="mb-0"><?= $editHoliday ? 'Modifier' : 'Nouveau' ?> congé</h6></div>
            <div class="card-body">
                <form method="POST">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="action" value="save">
                    <?php if ($editHoliday): ?><input type="hidden" name="id" value="<?= $editHoliday['id'] ?>"><?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Titre *</label>
                        <input type="text" name="title" class="form-control" required value="<?= ab_escape($editHoliday['title'] ?? '') ?>">
                    </div>
                    <?php if (Auth::isAdmin()): ?>
                    <div class="mb-3">
                        <label class="form-label">Prestataire</label>
                        <select name="provider_id" class="form-select">
                            <option value="">Tous les prestataires</option>
                            <?php foreach ($providers as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ($editHoliday['provider_id'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= ab_escape($p['first_name'] . ' ' . $p['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Date début *</label>
                        <input type="date" name="date_start" class="form-control" required value="<?= $editHoliday['date_start'] ?? '' ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date fin *</label>
                        <input type="date" name="date_end" class="form-control" required value="<?= $editHoliday['date_end'] ?? '' ?>">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check"></i> Enregistrer</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Titre</th><th>Prestataire</th><th>Du</th><th>Au</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($holidays as $h): ?>
                    <tr>
                        <td><?= ab_escape($h['title']) ?></td>
                        <td><?= $h['first_name'] ? ab_escape($h['first_name'] . ' ' . $h['last_name']) : '<em>Tous</em>' ?></td>
                        <td><?= ab_format_date($h['date_start']) ?></td>
                        <td><?= ab_format_date($h['date_end']) ?></td>
                        <td>
                            <a href="<?= ab_url('admin/index.php?page=holidays&edit=' . $h['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer ?')">
                                <?= Auth::csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $h['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
