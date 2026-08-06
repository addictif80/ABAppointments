<?php
$db = Database::getInstance();
$tickets = new Tickets();
$action = $_GET['action'] ?? 'list';
$isAdmin = Auth::isAdmin();
$userId = (int) Auth::userId();

$statusLabels = ['open' => 'Ouvert', 'resolved' => 'Résolu', 'closed' => 'Clôturé'];
$categoryLabels = ['commercial' => 'Commercial', 'technique' => 'Technique'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'reply' && isset($_POST['id'], $_POST['body'])) {
        $ticket = $tickets->getById((int) $_POST['id']);
        if (!$ticket || !$tickets->canStaffAccess($ticket, $isAdmin, $userId)) {
            http_response_code(403);
            exit('Accès refusé.');
        }
        $body = trim($_POST['body']);
        if ($body !== '') {
            $tickets->reply($ticket, 'staff', $userId, $body, $_FILES['attachments'] ?? []);
            ab_flash('success', 'Réponse envoyée, le client a été notifié.');
        }
        ab_redirect(ab_url('admin/index.php?page=tickets&action=view&id=' . $ticket['id']));
    }

    if ($postAction === 'update_status' && isset($_POST['id'], $_POST['status'])) {
        $ticket = $tickets->getById((int) $_POST['id']);
        if (!$ticket || !$tickets->canStaffAccess($ticket, $isAdmin, $userId)) {
            http_response_code(403);
            exit('Accès refusé.');
        }
        if (in_array($_POST['status'], ['open', 'resolved', 'closed'], true)) {
            $tickets->updateStatus($ticket['id'], $_POST['status']);
            ab_flash('success', 'Statut du ticket mis à jour.');
        }
        ab_redirect(ab_url('admin/index.php?page=tickets&action=view&id=' . $ticket['id']));
    }

    if ($postAction === 'reassign' && isset($_POST['id']) && $isAdmin) {
        $ticket = $tickets->getById((int) $_POST['id']);
        if ($ticket) {
            $tickets->reassign($ticket['id'], (int) ($_POST['provider_id'] ?? 0));
            ab_flash('success', 'Ticket réassigné.');
        }
        ab_redirect(ab_url('admin/index.php?page=tickets&action=view&id=' . $ticket['id']));
    }

    if ($postAction === 'create') {
        $subject = trim($_POST['subject'] ?? '');
        $category = $_POST['category'] ?? '';
        $body = trim($_POST['body'] ?? '');
        $providerId = (int) ($_POST['provider_id'] ?? 0);
        $email = trim($_POST['email'] ?? '');
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');

        if ($subject === '' || $body === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $firstName === '' || $lastName === ''
            || !in_array($category, ['commercial', 'technique'], true)) {
            ab_flash('error', 'Merci de compléter tous les champs requis.');
            ab_redirect(ab_url('admin/index.php?page=tickets&action=create'));
        }

        $dob = ab_parse_dob(trim($_POST['date_of_birth'] ?? ''));
        $customerId = $tickets->resolveCustomer($firstName, $lastName, $email, trim($_POST['phone'] ?? ''), $dob);
        $ticket = $tickets->create([
            'customer_id' => $customerId,
            'subject' => $subject,
            'category' => $category,
            'provider_id' => $providerId,
            'body' => $body,
            'created_by' => 'staff',
            'sender_user_id' => $userId,
            'files' => $_FILES['attachments'] ?? [],
        ]);
        ab_flash('success', 'Ticket créé, le client a été notifié.');
        ab_redirect(ab_url('admin/index.php?page=tickets&action=view&id=' . $ticket['id']));
    }
}

if ($action === 'view' && isset($_GET['id'])):
    $ticket = $tickets->getById((int) $_GET['id']);
    if (!$ticket || !$tickets->canStaffAccess($ticket, $isAdmin, $userId)) {
        http_response_code(403);
        exit('Accès refusé.');
    }
    $messages = $tickets->getMessages((int) $ticket['id']);
    $providers = $isAdmin ? $db->fetchAll("SELECT * FROM ab_users WHERE is_active = 1 ORDER BY first_name") : [];
?>
<div class="row">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0"><i class="bi bi-life-preserver"></i> Ticket #<?= $ticket['id'] ?> — <?= ab_escape($ticket['subject']) ?></h5>
                    <small class="text-muted"><?= ab_escape($ticket['customer_first_name'] . ' ' . $ticket['customer_last_name']) ?> · <?= ab_escape($ticket['customer_email']) ?></small>
                </div>
                <span class="badge badge-<?= $ticket['status'] ?>"><?= $statusLabels[$ticket['status']] ?></span>
            </div>
            <div class="card-body" style="max-height:500px;overflow-y:auto;">
                <?php foreach ($messages as $m): ?>
                <div class="mb-3 p-3 rounded <?= $m['sender_type'] === 'customer' ? 'bg-light' : 'bg-primary bg-opacity-10' ?>">
                    <div class="small text-muted mb-1">
                        <strong><?= $m['sender_type'] === 'customer' ? ab_escape($ticket['customer_first_name']) : ab_escape(trim(($m['staff_first_name'] ?? '') . ' ' . ($m['staff_last_name'] ?? '')) ?: 'Équipe') ?></strong>
                        &middot; <?= ab_format_date($m['created_at']) ?> <?= ab_format_time($m['created_at']) ?>
                    </div>
                    <div><?= nl2br(ab_escape($m['body'])) ?></div>
                    <?php if (!empty($m['attachments'])): ?>
                    <div class="mt-2">
                        <?php foreach ($m['attachments'] as $att): ?>
                        <a href="<?= ab_url('admin/index.php?page=tickets&action=download&id=' . $att['id']) ?>" target="_blank" class="badge bg-secondary text-decoration-none me-1">
                            <i class="bi bi-paperclip"></i> <?= ab_escape($att['original_filename']) ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if ($ticket['status'] !== 'closed'): ?>
            <div class="card-footer bg-white">
                <form method="POST" enctype="multipart/form-data">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="action" value="reply">
                    <input type="hidden" name="id" value="<?= $ticket['id'] ?>">
                    <textarea name="body" class="form-control mb-2" rows="3" placeholder="Votre réponse..." required></textarea>
                    <input type="file" name="attachments[]" class="form-control form-control-sm mb-2" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.csv">
                    <button class="btn btn-primary"><i class="bi bi-send"></i> Répondre</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">Détails</h6></div>
            <div class="card-body">
                <p class="mb-1"><strong>Motif :</strong> <?= $categoryLabels[$ticket['category']] ?></p>
                <p class="mb-1"><strong>Prestataire assigné :</strong> <?= $ticket['provider_first_name'] ? ab_escape($ticket['provider_first_name'] . ' ' . $ticket['provider_last_name']) : '—' ?></p>
                <p class="mb-0"><strong>Créé le :</strong> <?= ab_format_date($ticket['created_at']) ?></p>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header bg-white"><h6 class="mb-0">Statut</h6></div>
            <div class="card-body d-grid gap-2">
                <?php foreach (['open' => 'Rouvrir', 'resolved' => 'Marquer résolu', 'closed' => 'Clôturer'] as $st => $label): ?>
                    <?php if ($ticket['status'] !== $st): ?>
                    <form method="POST">
                        <?= Auth::csrfField() ?>
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="id" value="<?= $ticket['id'] ?>">
                        <input type="hidden" name="status" value="<?= $st ?>">
                        <button class="btn btn-outline-secondary w-100 btn-sm"><?= $label ?></button>
                    </form>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php if ($isAdmin): ?>
        <div class="card">
            <div class="card-header bg-white"><h6 class="mb-0">Réassigner</h6></div>
            <div class="card-body">
                <form method="POST">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="action" value="reassign">
                    <input type="hidden" name="id" value="<?= $ticket['id'] ?>">
                    <select name="provider_id" class="form-select form-select-sm mb-2">
                        <option value="">Aucun (admin/technique)</option>
                        <?php foreach ($providers as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= (int) $ticket['assigned_provider_id'] === (int) $p['id'] ? 'selected' : '' ?>><?= ab_escape($p['first_name'] . ' ' . $p['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-outline-primary btn-sm w-100">Enregistrer</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
        <a href="<?= ab_url('admin/index.php?page=tickets') ?>" class="btn btn-outline-secondary w-100 mt-3"><i class="bi bi-arrow-left"></i> Retour</a>
    </div>
</div>

<?php elseif ($action === 'download' && isset($_GET['id'])):
    $attachment = $tickets->getAttachment((int) $_GET['id']);
    if (!$attachment) { http_response_code(404); exit('Fichier non trouvé.'); }
    $downloadTicket = $tickets->getById((int) $attachment['ticket_id']);
    if (!$downloadTicket || !$tickets->canStaffAccess($downloadTicket, $isAdmin, $userId)) {
        http_response_code(403);
        exit('Accès refusé.');
    }
    $path = $tickets->attachmentPath($attachment);
    if (!is_file($path)) { http_response_code(404); exit('Fichier non trouvé.'); }
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: ' . $attachment['mime_type']);
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: inline; filename="' . preg_replace('/[^\w.\-]+/u', '_', $attachment['original_filename']) . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;

elseif ($action === 'create'): ?>
<?php $providers = $db->fetchAll("SELECT * FROM ab_users WHERE is_active = 1 ORDER BY first_name"); ?>
<div class="card">
    <div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-plus-circle"></i> Nouveau ticket</h5></div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="create">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Prénom client *</label><input type="text" name="first_name" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Nom client *</label><input type="text" name="last_name" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Email client *</label><input type="email" name="email" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Téléphone</label><input type="tel" name="phone" class="form-control"></div>
                <div class="col-md-6">
                    <label class="form-label">Date de naissance</label>
                    <input type="text" name="date_of_birth" class="form-control" placeholder="JJ.MM.AAAA" maxlength="10">
                    <small class="text-muted">Requise pour que le client accède à son espace personnel.</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Motif *</label>
                    <select name="category" class="form-select" required>
                        <option value="commercial">Commercial</option>
                        <option value="technique">Technique</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Prestataire concerné</label>
                    <select name="provider_id" class="form-select">
                        <option value="">—</option>
                        <?php foreach ($providers as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= ab_escape($p['first_name'] . ' ' . $p['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12"><label class="form-label">Sujet *</label><input type="text" name="subject" class="form-control" required></div>
                <div class="col-12"><label class="form-label">Message *</label><textarea name="body" class="form-control" rows="4" required></textarea></div>
                <div class="col-12">
                    <label class="form-label">Pièces jointes</label>
                    <input type="file" name="attachments[]" class="form-control" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.csv">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check"></i> Créer le ticket</button>
                    <a href="<?= ab_url('admin/index.php?page=tickets') ?>" class="btn btn-outline-secondary">Annuler</a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php else: // List view ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-life-preserver"></i> Support</h4>
    <a href="<?= ab_url('admin/index.php?page=tickets&action=create') ?>" class="btn btn-primary"><i class="bi bi-plus"></i> Nouveau ticket</a>
</div>

<?php
$filter = $_GET['status'] ?? '';
$ticketsList = $isAdmin ? $tickets->listForAdmin($filter) : $tickets->listForProvider($userId, $filter);
?>
<div class="card">
    <div class="card-header bg-white">
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= ab_url('admin/index.php?page=tickets') ?>" class="btn btn-sm <?= !$filter ? 'btn-primary' : 'btn-outline-primary' ?>">Tous</a>
            <a href="<?= ab_url('admin/index.php?page=tickets&status=open') ?>" class="btn btn-sm <?= $filter === 'open' ? 'btn-warning' : 'btn-outline-warning' ?>">Ouverts</a>
            <a href="<?= ab_url('admin/index.php?page=tickets&status=resolved') ?>" class="btn btn-sm <?= $filter === 'resolved' ? 'btn-success' : 'btn-outline-success' ?>">Résolus</a>
            <a href="<?= ab_url('admin/index.php?page=tickets&status=closed') ?>" class="btn btn-sm <?= $filter === 'closed' ? 'btn-secondary' : 'btn-outline-secondary' ?>">Clôturés</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Client</th><th>Sujet</th><th>Motif</th><th>Prestataire</th><th>Statut</th><th>Mis à jour</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($ticketsList as $t): ?>
            <tr>
                <td>#<?= $t['id'] ?></td>
                <td><?= ab_escape($t['customer_first_name'] . ' ' . $t['customer_last_name']) ?></td>
                <td><?= ab_escape($t['subject']) ?></td>
                <td><?= $categoryLabels[$t['category']] ?? $t['category'] ?></td>
                <td><?= $t['provider_first_name'] ? ab_escape($t['provider_first_name'] . ' ' . $t['provider_last_name']) : '—' ?></td>
                <td><span class="badge badge-<?= $t['status'] ?>"><?= $statusLabels[$t['status']] ?? $t['status'] ?></span></td>
                <td><?= ab_format_date($t['updated_at']) ?></td>
                <td><a href="<?= ab_url('admin/index.php?page=tickets&action=view&id=' . $t['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($ticketsList)): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">Aucun ticket</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
