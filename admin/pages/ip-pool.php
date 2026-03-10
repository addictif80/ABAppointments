<?php
$pageTitle = 'Pool IP';
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $db->insert('wp_ip_pool', [
            'ip_address' => trim($_POST['ip_address'] ?? ''),
            'type' => $_POST['type'] ?? 'ipv4',
            'gateway' => trim($_POST['gateway'] ?? '') ?: null,
            'netmask' => trim($_POST['netmask'] ?? '') ?: null,
            'notes' => trim($_POST['notes'] ?? '') ?: null
        ]);
        wp_flash('success', 'IP ajoutee.');
    } elseif ($action === 'add_range') {
        $start = ip2long(trim($_POST['range_start'] ?? ''));
        $end = ip2long(trim($_POST['range_end'] ?? ''));
        $gateway = trim($_POST['gateway'] ?? '') ?: null;
        $netmask = trim($_POST['netmask'] ?? '') ?: null;
        if ($start && $end && $end >= $start) {
            $count = 0;
            for ($ip = $start; $ip <= $end; $ip++) {
                try {
                    $db->insert('wp_ip_pool', ['ip_address' => long2ip($ip), 'type' => 'ipv4', 'gateway' => $gateway, 'netmask' => $netmask]);
                    $count++;
                } catch (Exception $e) {}
            }
            wp_flash('success', "$count IPs ajoutees.");
        }
    } elseif ($action === 'delete') {
        $ip = $db->fetchOne("SELECT * FROM wp_ip_pool WHERE id = ?", [(int)$_POST['ip_id']]);
        if ($ip && !$ip['is_assigned']) {
            $db->delete('wp_ip_pool', 'id = ?', [$ip['id']]);
            wp_flash('success', 'IP supprimee.');
        } else {
            wp_flash('error', 'Impossible de supprimer une IP assignee.');
        }
    }
    wp_redirect(wp_url('admin/?page=ip-pool'));
}

$ips = $db->fetchAll(
    "SELECT p.*, v.hostname, v.proxmox_vmid FROM wp_ip_pool p LEFT JOIN wp_services_vps v ON p.assigned_to_vps_id = v.id ORDER BY INET_ATON(p.ip_address)"
);
$totalIps = count($ips);
$assignedIps = count(array_filter($ips, fn($ip) => $ip['is_assigned']));
$availableIps = $totalIps - $assignedIps;
?>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="stat-card text-center"><div class="text-muted small">Total IPs</div><div class="fw-bold fs-4"><?= $totalIps ?></div></div></div>
    <div class="col-md-4"><div class="stat-card text-center"><div class="text-muted small">Disponibles</div><div class="fw-bold fs-4 text-success"><?= $availableIps ?></div></div></div>
    <div class="col-md-4"><div class="stat-card text-center"><div class="text-muted small">Assignees</div><div class="fw-bold fs-4 text-primary"><?= $assignedIps ?></div></div></div>
</div>

<div class="d-flex justify-content-end gap-2 mb-4">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addIpModal"><i class="bi bi-plus-lg me-1"></i> Ajouter IP</button>
    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addRangeModal"><i class="bi bi-list-ol me-1"></i> Ajouter plage</button>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Adresse IP</th><th>Type</th><th>Passerelle</th><th>Masque</th><th>Statut</th><th>Assigne a</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($ips as $ip): ?>
            <tr>
                <td class="font-monospace fw-semibold"><?= wp_escape($ip['ip_address']) ?></td>
                <td><span class="badge bg-secondary"><?= strtoupper($ip['type']) ?></span></td>
                <td><?= wp_escape($ip['gateway'] ?: '-') ?></td>
                <td><?= wp_escape($ip['netmask'] ?: '-') ?></td>
                <td><span class="badge bg-<?= $ip['is_assigned'] ? 'primary' : 'success' ?>"><?= $ip['is_assigned'] ? 'Assignee' : 'Disponible' ?></span></td>
                <td><?= $ip['hostname'] ? wp_escape($ip['hostname']) . ' (VMID: ' . $ip['proxmox_vmid'] . ')' : '-' ?></td>
                <td>
                    <?php if (!$ip['is_assigned']): ?>
                    <form method="POST" class="d-inline"><?= wp_csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="ip_id" value="<?= $ip['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add single IP Modal -->
<div class="modal fade" id="addIpModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content"><?= wp_csrf_field() ?><input type="hidden" name="action" value="add">
            <div class="modal-header"><h5 class="modal-title">Ajouter une IP</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Adresse IP *</label><input type="text" name="ip_address" class="form-control" required></div>
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Type</label><select name="type" class="form-select"><option value="ipv4">IPv4</option><option value="ipv6">IPv6</option></select></div>
                    <div class="col-md-4"><label class="form-label">Passerelle</label><input type="text" name="gateway" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label">Masque</label><input type="text" name="netmask" class="form-control" value="255.255.255.0"></div>
                </div>
                <div class="mt-3"><label class="form-label">Notes</label><input type="text" name="notes" class="form-control"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-primary">Ajouter</button></div>
        </form>
    </div>
</div>

<!-- Add range Modal -->
<div class="modal fade" id="addRangeModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content"><?= wp_csrf_field() ?><input type="hidden" name="action" value="add_range">
            <div class="modal-header"><h5 class="modal-title">Ajouter une plage</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">IP debut *</label><input type="text" name="range_start" class="form-control" required placeholder="192.168.1.10"></div>
                    <div class="col-md-6"><label class="form-label">IP fin *</label><input type="text" name="range_end" class="form-control" required placeholder="192.168.1.50"></div>
                    <div class="col-md-6"><label class="form-label">Passerelle</label><input type="text" name="gateway" class="form-control"></div>
                    <div class="col-md-6"><label class="form-label">Masque</label><input type="text" name="netmask" class="form-control" value="255.255.255.0"></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-primary">Ajouter la plage</button></div>
        </form>
    </div>
</div>
