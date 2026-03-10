<?php
$pageTitle = 'Gestion Produits';
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $productId = (int)($_POST['product_id'] ?? 0);

    if ($action === 'create' || $action === 'update') {
        $data = [
            'type' => $_POST['type'] ?? 'vps',
            'name' => trim($_POST['name'] ?? ''),
            'slug' => trim($_POST['slug'] ?? '') ?: strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($_POST['name'] ?? ''))),
            'description' => trim($_POST['description'] ?? ''),
            'price_monthly' => (float)($_POST['price_monthly'] ?? 0),
            'price_yearly' => ($_POST['price_yearly'] ?? '') !== '' ? (float)$_POST['price_yearly'] : null,
            'setup_fee' => (float)($_POST['setup_fee'] ?? 0),
            'features' => !empty($_POST['features']) ? json_encode(array_filter(array_map('trim', explode("\n", $_POST['features'])))) : null,
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'stock' => ($_POST['stock'] ?? '') !== '' ? (int)$_POST['stock'] : null,
            'proxmox_cores' => ($_POST['proxmox_cores'] ?? '') !== '' ? (int)$_POST['proxmox_cores'] : null,
            'proxmox_ram_mb' => ($_POST['proxmox_ram_mb'] ?? '') !== '' ? (int)$_POST['proxmox_ram_mb'] : null,
            'proxmox_disk_gb' => ($_POST['proxmox_disk_gb'] ?? '') !== '' ? (int)$_POST['proxmox_disk_gb'] : null,
            'proxmox_bandwidth_gb' => ($_POST['proxmox_bandwidth_gb'] ?? '') !== '' ? (int)$_POST['proxmox_bandwidth_gb'] : null,
            'proxmox_storage' => trim($_POST['proxmox_storage'] ?? '') ?: null,
            'proxmox_bridge' => trim($_POST['proxmox_bridge'] ?? '') ?: null,
            'hosting_disk_mb' => ($_POST['hosting_disk_mb'] ?? '') !== '' ? (int)$_POST['hosting_disk_mb'] : null,
            'hosting_bandwidth_mb' => ($_POST['hosting_bandwidth_mb'] ?? '') !== '' ? (int)$_POST['hosting_bandwidth_mb'] : null,
            'hosting_email_accounts' => ($_POST['hosting_email_accounts'] ?? '') !== '' ? (int)$_POST['hosting_email_accounts'] : null,
            'hosting_databases' => ($_POST['hosting_databases'] ?? '') !== '' ? (int)$_POST['hosting_databases'] : null,
            'hosting_domains' => ($_POST['hosting_domains'] ?? '') !== '' ? (int)$_POST['hosting_domains'] : null,
            'hosting_package' => trim($_POST['hosting_package'] ?? '') ?: null,
            'navidrome_storage_mb' => ($_POST['navidrome_storage_mb'] ?? '') !== '' ? (int)$_POST['navidrome_storage_mb'] : null,
            'navidrome_max_playlists' => ($_POST['navidrome_max_playlists'] ?? '') !== '' ? (int)$_POST['navidrome_max_playlists'] : null,
        ];

        if ($action === 'create') {
            $db->insert('wp_products', $data);
            wp_flash('success', 'Produit cree.');
        } else {
            $db->update('wp_products', $data, 'id = ?', [$productId]);
            wp_flash('success', 'Produit mis a jour.');
        }
    } elseif ($action === 'delete' && $productId) {
        $hasSubs = $db->count('wp_subscriptions', 'product_id = ?', [$productId]);
        if ($hasSubs) {
            wp_flash('error', 'Impossible de supprimer : des abonnements utilisent ce produit.');
        } else {
            $db->delete('wp_products', 'id = ?', [$productId]);
            wp_flash('success', 'Produit supprime.');
        }
    }
    wp_redirect(wp_url('admin/?page=products'));
}

$type = $_GET['type'] ?? 'all';
$where = '1';
$params = [];
if ($type !== 'all') { $where = 'type = ?'; $params = [$type]; }
$products = $db->fetchAll("SELECT p.*, (SELECT COUNT(*) FROM wp_subscriptions WHERE product_id = p.id AND status = 'active') as active_subs FROM wp_products p WHERE $where ORDER BY type, sort_order", $params);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <ul class="nav nav-pills">
        <li class="nav-item"><a class="nav-link <?= $type === 'all' ? 'active' : '' ?>" href="<?= wp_url('admin/?page=products') ?>">Tous</a></li>
        <li class="nav-item"><a class="nav-link <?= $type === 'vps' ? 'active' : '' ?>" href="<?= wp_url('admin/?page=products&type=vps') ?>">VPS</a></li>
        <li class="nav-item"><a class="nav-link <?= $type === 'hosting' ? 'active' : '' ?>" href="<?= wp_url('admin/?page=products&type=hosting') ?>">Hebergement</a></li>
        <li class="nav-item"><a class="nav-link <?= $type === 'navidrome' ? 'active' : '' ?>" href="<?= wp_url('admin/?page=products&type=navidrome') ?>">Navidrome</a></li>
    </ul>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal" onclick="resetProductForm()"><i class="bi bi-plus-lg me-1"></i> Nouveau produit</button>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Produit</th><th>Type</th><th>Prix /mois</th><th>Prix /an</th><th>Abonnes</th><th>Actif</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($products as $p): ?>
            <tr>
                <td><strong><?= wp_escape($p['name']) ?></strong><br><small class="text-muted"><?= wp_escape($p['slug']) ?></small></td>
                <td><span class="badge bg-<?= $p['type'] === 'vps' ? 'primary' : ($p['type'] === 'hosting' ? 'success' : 'info') ?>"><?= strtoupper($p['type']) ?></span></td>
                <td><?= wp_format_price($p['price_monthly']) ?></td>
                <td><?= $p['price_yearly'] ? wp_format_price($p['price_yearly']) : '-' ?></td>
                <td><span class="badge bg-secondary"><?= $p['active_subs'] ?></span></td>
                <td><?= $p['is_active'] ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-x-circle text-danger"></i>' ?></td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="editProduct(<?= wp_escape(json_encode($p)) ?>)"><i class="bi bi-pencil"></i></button>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce produit ?')">
                        <?= wp_csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Product Modal -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content" id="productForm">
            <?= wp_csrf_field() ?>
            <input type="hidden" name="action" value="create" id="productAction">
            <input type="hidden" name="product_id" id="productId">
            <div class="modal-header"><h5 class="modal-title" id="productModalTitle">Nouveau produit</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Type *</label>
                        <select name="type" id="pType" class="form-select" onchange="toggleProductFields()">
                            <option value="vps">VPS</option><option value="hosting">Hebergement</option><option value="navidrome">Navidrome</option>
                        </select>
                    </div>
                    <div class="col-md-5"><label class="form-label">Nom *</label><input type="text" name="name" id="pName" class="form-control" required></div>
                    <div class="col-md-3"><label class="form-label">Slug</label><input type="text" name="slug" id="pSlug" class="form-control"></div>
                    <div class="col-12"><label class="form-label">Description</label><textarea name="description" id="pDesc" class="form-control" rows="2"></textarea></div>
                    <div class="col-md-4"><label class="form-label">Prix mensuel *</label><input type="number" name="price_monthly" id="pPriceM" class="form-control" step="0.01" required></div>
                    <div class="col-md-4"><label class="form-label">Prix annuel</label><input type="number" name="price_yearly" id="pPriceY" class="form-control" step="0.01"></div>
                    <div class="col-md-4"><label class="form-label">Frais install.</label><input type="number" name="setup_fee" id="pSetup" class="form-control" step="0.01" value="0"></div>
                    <div class="col-md-4"><label class="form-label">Ordre</label><input type="number" name="sort_order" id="pSort" class="form-control" value="0"></div>
                    <div class="col-md-4"><label class="form-label">Stock</label><input type="number" name="stock" id="pStock" class="form-control" placeholder="Illimite"></div>
                    <div class="col-md-4 d-flex align-items-end"><div class="form-check"><input type="checkbox" name="is_active" id="pActive" class="form-check-input" checked><label class="form-check-label" for="pActive">Actif</label></div></div>
                    <div class="col-12"><label class="form-label">Features (une par ligne)</label><textarea name="features" id="pFeatures" class="form-control" rows="3"></textarea></div>

                    <!-- VPS fields -->
                    <div class="vps-fields">
                        <div class="row g-3">
                            <div class="col-md-3"><label class="form-label">CPU (cores)</label><input type="number" name="proxmox_cores" id="pCores" class="form-control"></div>
                            <div class="col-md-3"><label class="form-label">RAM (MB)</label><input type="number" name="proxmox_ram_mb" id="pRam" class="form-control"></div>
                            <div class="col-md-3"><label class="form-label">Disque (GB)</label><input type="number" name="proxmox_disk_gb" id="pDisk" class="form-control"></div>
                            <div class="col-md-3"><label class="form-label">Bande passante (GB)</label><input type="number" name="proxmox_bandwidth_gb" id="pBw" class="form-control"></div>
                            <div class="col-md-6"><label class="form-label">Storage Proxmox</label><input type="text" name="proxmox_storage" id="pStorage" class="form-control" placeholder="local-lvm"></div>
                            <div class="col-md-6"><label class="form-label">Bridge Proxmox</label><input type="text" name="proxmox_bridge" id="pBridge" class="form-control" placeholder="vmbr0"></div>
                        </div>
                    </div>
                    <!-- Hosting fields -->
                    <div class="hosting-fields" style="display:none">
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label">Disque (MB)</label><input type="number" name="hosting_disk_mb" id="hDisk" class="form-control"></div>
                            <div class="col-md-4"><label class="form-label">Bande passante (MB)</label><input type="number" name="hosting_bandwidth_mb" id="hBw" class="form-control"></div>
                            <div class="col-md-4"><label class="form-label">Comptes email</label><input type="number" name="hosting_email_accounts" id="hEmail" class="form-control"></div>
                            <div class="col-md-4"><label class="form-label">Bases donnees</label><input type="number" name="hosting_databases" id="hDb" class="form-control"></div>
                            <div class="col-md-4"><label class="form-label">Domaines</label><input type="number" name="hosting_domains" id="hDomains" class="form-control"></div>
                            <div class="col-md-4"><label class="form-label">Package CyberPanel</label><input type="text" name="hosting_package" id="hPackage" class="form-control"></div>
                        </div>
                    </div>
                    <!-- Navidrome fields -->
                    <div class="navidrome-fields" style="display:none">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Stockage (MB)</label><input type="number" name="navidrome_storage_mb" id="nStorage" class="form-control"></div>
                            <div class="col-md-6"><label class="form-label">Max playlists</label><input type="number" name="navidrome_max_playlists" id="nPlaylists" class="form-control" placeholder="Illimite"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-primary">Enregistrer</button></div>
        </form>
    </div>
</div>

<script>
function toggleProductFields() {
    const t = document.getElementById('pType').value;
    document.querySelector('.vps-fields').style.display = t === 'vps' ? '' : 'none';
    document.querySelector('.hosting-fields').style.display = t === 'hosting' ? '' : 'none';
    document.querySelector('.navidrome-fields').style.display = t === 'navidrome' ? '' : 'none';
}
function resetProductForm() {
    document.getElementById('productForm').reset();
    document.getElementById('productAction').value = 'create';
    document.getElementById('productModalTitle').textContent = 'Nouveau produit';
    toggleProductFields();
}
function editProduct(p) {
    document.getElementById('productAction').value = 'update';
    document.getElementById('productId').value = p.id;
    document.getElementById('productModalTitle').textContent = 'Modifier: ' + p.name;
    document.getElementById('pType').value = p.type;
    document.getElementById('pName').value = p.name;
    document.getElementById('pSlug').value = p.slug;
    document.getElementById('pDesc').value = p.description || '';
    document.getElementById('pPriceM').value = p.price_monthly;
    document.getElementById('pPriceY').value = p.price_yearly || '';
    document.getElementById('pSetup').value = p.setup_fee;
    document.getElementById('pSort').value = p.sort_order;
    document.getElementById('pStock').value = p.stock || '';
    document.getElementById('pActive').checked = p.is_active == 1;
    const features = p.features ? JSON.parse(p.features) : [];
    document.getElementById('pFeatures').value = features.join('\n');
    document.getElementById('pCores').value = p.proxmox_cores || '';
    document.getElementById('pRam').value = p.proxmox_ram_mb || '';
    document.getElementById('pDisk').value = p.proxmox_disk_gb || '';
    document.getElementById('pBw').value = p.proxmox_bandwidth_gb || '';
    document.getElementById('pStorage').value = p.proxmox_storage || '';
    document.getElementById('pBridge').value = p.proxmox_bridge || '';
    document.getElementById('hDisk').value = p.hosting_disk_mb || '';
    document.getElementById('hBw').value = p.hosting_bandwidth_mb || '';
    document.getElementById('hEmail').value = p.hosting_email_accounts || '';
    document.getElementById('hDb').value = p.hosting_databases || '';
    document.getElementById('hDomains').value = p.hosting_domains || '';
    document.getElementById('hPackage').value = p.hosting_package || '';
    document.getElementById('nStorage').value = p.navidrome_storage_mb || '';
    document.getElementById('nPlaylists').value = p.navidrome_max_playlists || '';
    toggleProductFields();
    new bootstrap.Modal(document.getElementById('productModal')).show();
}
</script>
