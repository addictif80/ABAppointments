<?php
$pageTitle = 'Codes Promo';
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $codeId = (int)($_POST['code_id'] ?? 0);

    if ($action === 'create' || $action === 'update') {
        $data = [
            'code' => strtoupper(trim($_POST['code'] ?? '')),
            'description' => trim($_POST['description'] ?? ''),
            'type' => in_array($_POST['type'] ?? '', ['percentage', 'fixed']) ? $_POST['type'] : 'percentage',
            'value' => (float)($_POST['value'] ?? 0),
            'min_order_amount' => ($_POST['min_order_amount'] ?? '') !== '' ? (float)$_POST['min_order_amount'] : null,
            'max_discount' => ($_POST['max_discount'] ?? '') !== '' ? (float)$_POST['max_discount'] : null,
            'usage_limit' => ($_POST['usage_limit'] ?? '') !== '' ? (int)$_POST['usage_limit'] : null,
            'usage_limit_per_user' => (int)($_POST['usage_limit_per_user'] ?? 1),
            'valid_from' => !empty($_POST['valid_from']) ? $_POST['valid_from'] : null,
            'valid_to' => !empty($_POST['valid_to']) ? $_POST['valid_to'] : null,
            'applicable_products' => !empty($_POST['applicable_products']) ? json_encode($_POST['applicable_products']) : null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        if (empty($data['code'])) {
            wp_flash('error', 'Le code est obligatoire.');
        } elseif ($data['value'] <= 0) {
            wp_flash('error', 'La valeur doit etre positive.');
        } else {
            if ($action === 'create') {
                $existing = $db->fetchOne("SELECT id FROM wp_promo_codes WHERE code = ?", [$data['code']]);
                if ($existing) {
                    wp_flash('error', 'Ce code existe deja.');
                } else {
                    $db->insert('wp_promo_codes', $data);
                    wp_flash('success', 'Code promo cree.');
                    wp_log_activity('promo_code_created', 'promo_code', null, ['code' => $data['code']]);
                }
            } else {
                $db->update('wp_promo_codes', $data, 'id = ?', [$codeId]);
                wp_flash('success', 'Code promo mis a jour.');
                wp_log_activity('promo_code_updated', 'promo_code', $codeId);
            }
        }
    } elseif ($action === 'delete' && $codeId) {
        $db->delete('wp_promo_codes', 'id = ?', [$codeId]);
        wp_flash('success', 'Code promo supprime.');
        wp_log_activity('promo_code_deleted', 'promo_code', $codeId);
    }
    wp_redirect(wp_url('admin/?page=promo-codes'));
}

$codes = $db->fetchAll("SELECT * FROM wp_promo_codes ORDER BY created_at DESC");
$products = $db->fetchAll("SELECT id, name, type FROM wp_products WHERE is_active = 1 ORDER BY name");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <span class="text-muted"><?= count($codes) ?> code(s) promo</span>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#promoModal" onclick="resetPromoForm()">
        <i class="bi bi-plus-lg me-1"></i> Nouveau code promo
    </button>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Type</th>
                    <th>Valeur</th>
                    <th>Utilisations</th>
                    <th>Validite</th>
                    <th>Actif</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($codes as $c): ?>
                <tr>
                    <td>
                        <strong class="font-monospace"><?= wp_escape($c['code']) ?></strong>
                        <?php if ($c['description']): ?>
                            <br><small class="text-muted"><?= wp_escape($c['description']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-<?= $c['type'] === 'percentage' ? 'info' : 'success' ?>">
                            <?= $c['type'] === 'percentage' ? 'Pourcentage' : 'Montant fixe' ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($c['type'] === 'percentage'): ?>
                            <?= (int)$c['value'] ?>%
                        <?php else: ?>
                            <?= wp_format_price($c['value']) ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= $c['used_count'] ?><?= $c['usage_limit'] ? ' / ' . $c['usage_limit'] : '' ?>
                    </td>
                    <td>
                        <?php if ($c['valid_from'] || $c['valid_to']): ?>
                            <?= $c['valid_from'] ? wp_format_date($c['valid_from']) : '...' ?>
                            - <?= $c['valid_to'] ? wp_format_date($c['valid_to']) : '...' ?>
                        <?php else: ?>
                            <span class="text-muted">Illimitee</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= $c['is_active'] ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-x-circle text-danger"></i>' ?>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="editPromo(<?= wp_escape(json_encode($c)) ?>)">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce code promo ?')">
                            <?= wp_csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="code_id" value="<?= $c['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($codes)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Aucun code promo</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Promo Code Modal -->
<div class="modal fade" id="promoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content" id="promoForm">
            <?= wp_csrf_field() ?>
            <input type="hidden" name="action" value="create" id="promoAction">
            <input type="hidden" name="code_id" id="promoId">
            <div class="modal-header">
                <h5 class="modal-title" id="promoModalTitle">Nouveau code promo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Code *</label>
                        <div class="input-group">
                            <input type="text" name="code" id="pcCode" class="form-control font-monospace text-uppercase" required placeholder="EX: PROMO20">
                            <button type="button" class="btn btn-outline-secondary" onclick="generateCode()">
                                <i class="bi bi-shuffle"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" id="pcDesc" class="form-control" placeholder="Description interne">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Type *</label>
                        <select name="type" id="pcType" class="form-select">
                            <option value="percentage">Pourcentage (%)</option>
                            <option value="fixed">Montant fixe (€)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Valeur *</label>
                        <input type="number" name="value" id="pcValue" class="form-control" step="0.01" min="0.01" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Remise max (€)</label>
                        <input type="number" name="max_discount" id="pcMaxDiscount" class="form-control" step="0.01" placeholder="Illimitee">
                        <div class="form-text">Pour les % uniquement</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Commande min (€)</label>
                        <input type="number" name="min_order_amount" id="pcMinAmount" class="form-control" step="0.01" placeholder="Aucun">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Nb utilisations max</label>
                        <input type="number" name="usage_limit" id="pcUsageLimit" class="form-control" min="1" placeholder="Illimite">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Max par utilisateur</label>
                        <input type="number" name="usage_limit_per_user" id="pcPerUser" class="form-control" min="1" value="1">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Valide a partir du</label>
                        <input type="datetime-local" name="valid_from" id="pcFrom" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Valide jusqu'au</label>
                        <input type="datetime-local" name="valid_to" id="pcTo" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Produits applicables</label>
                        <div class="row g-2">
                            <?php foreach ($products as $prod): ?>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input type="checkbox" name="applicable_products[]" value="<?= $prod['id'] ?>" class="form-check-input product-check" id="prod_<?= $prod['id'] ?>">
                                    <label class="form-check-label" for="prod_<?= $prod['id'] ?>"><?= wp_escape($prod['name']) ?></label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-text">Laisser vide = tous les produits</div>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" id="pcActive" class="form-check-input" checked>
                            <label class="form-check-label" for="pcActive">Actif</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
function generateCode() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let code = '';
    for (let i = 0; i < 8; i++) code += chars.charAt(Math.floor(Math.random() * chars.length));
    document.getElementById('pcCode').value = code;
}

function resetPromoForm() {
    document.getElementById('promoForm').reset();
    document.getElementById('promoAction').value = 'create';
    document.getElementById('promoModalTitle').textContent = 'Nouveau code promo';
    document.querySelectorAll('.product-check').forEach(c => c.checked = false);
}

function editPromo(c) {
    document.getElementById('promoAction').value = 'update';
    document.getElementById('promoId').value = c.id;
    document.getElementById('promoModalTitle').textContent = 'Modifier: ' + c.code;
    document.getElementById('pcCode').value = c.code;
    document.getElementById('pcDesc').value = c.description || '';
    document.getElementById('pcType').value = c.type;
    document.getElementById('pcValue').value = c.value;
    document.getElementById('pcMaxDiscount').value = c.max_discount || '';
    document.getElementById('pcMinAmount').value = c.min_order_amount || '';
    document.getElementById('pcUsageLimit').value = c.usage_limit || '';
    document.getElementById('pcPerUser').value = c.usage_limit_per_user || 1;
    document.getElementById('pcFrom').value = c.valid_from ? c.valid_from.replace(' ', 'T').substring(0, 16) : '';
    document.getElementById('pcTo').value = c.valid_to ? c.valid_to.replace(' ', 'T').substring(0, 16) : '';
    document.getElementById('pcActive').checked = c.is_active == 1;

    // Check applicable products
    document.querySelectorAll('.product-check').forEach(cb => cb.checked = false);
    if (c.applicable_products) {
        const prods = JSON.parse(c.applicable_products);
        prods.forEach(pid => {
            const cb = document.getElementById('prod_' + pid);
            if (cb) cb.checked = true;
        });
    }

    new bootstrap.Modal(document.getElementById('promoModal')).show();
}
</script>
