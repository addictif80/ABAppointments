<?php
/**
 * ABAppointments - Public Ticket Thread (view + reply)
 */
require_once __DIR__ . '/../core/App.php';

$hash = $_GET['hash'] ?? '';
if (empty($hash)) {
    http_response_code(404);
    exit('Ticket non trouvé.');
}

$tickets = new Tickets();
$ticket = $tickets->getByHash($hash);
if (!$ticket) {
    http_response_code(404);
    exit('Ticket non trouvé.');
}

$messages = $tickets->getMessages((int) $ticket['id']);
$primaryColor = ab_setting('primary_color', '#e91e63');
$businessName = ab_setting('business_name', 'ABAppointments');

$statusLabels = ['open' => 'Ouvert', 'resolved' => 'Résolu', 'closed' => 'Clôturé'];
$categoryLabels = ['commercial' => 'Commercial', 'technique' => 'Technique'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?= (int) $ticket['id'] ?> - <?= ab_escape($businessName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        html, body { overflow-x: hidden; max-width: 100%; }
        body { background: #f8f9fa; }
        .ticket-card { max-width: 720px; margin: 40px auto; padding: 0 10px; }
        .header-bar { background: <?= $primaryColor ?>; color: #fff; padding: 20px; text-align: center; }
        .msg { border-radius: 12px; padding: 14px 16px; margin-bottom: 14px; max-width: 85%; }
        .msg.customer { background: #fff; border: 1px solid #eee; margin-right: auto; }
        .msg.staff { background: <?= $primaryColor ?>; color: #fff; margin-left: auto; }
        .msg .meta { font-size: 0.75rem; opacity: 0.75; margin-bottom: 6px; }
        .msg .attachments { margin-top: 8px; }
        .msg .attachments a { display: inline-flex; align-items: center; gap: 4px; font-size: 0.8rem; background: rgba(0,0,0,0.06); padding: 3px 10px; border-radius: 20px; margin: 2px; text-decoration: none; color: inherit; }
        .msg.staff .attachments a { background: rgba(255,255,255,0.2); color: #fff; }
        .badge-open { background: #ffc107; color: #000; }
        .badge-resolved { background: #28a745; }
        .badge-closed { background: #6c757d; }
    </style>
</head>
<body>
    <div class="header-bar">
        <h5 class="mb-0"><i class="bi bi-life-preserver"></i> <?= ab_escape($businessName) ?></h5>
    </div>

    <div class="container">
        <div class="ticket-card">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h6 class="mb-0">Ticket #<?= (int) $ticket['id'] ?> — <?= ab_escape($ticket['subject']) ?></h6>
                        <small class="text-muted"><?= ab_escape($categoryLabels[$ticket['category']] ?? $ticket['category']) ?></small>
                    </div>
                    <span class="badge badge-<?= ab_escape($ticket['status']) ?>"><?= ab_escape($statusLabels[$ticket['status']] ?? $ticket['status']) ?></span>
                </div>
            </div>

            <div id="messages">
                <?php foreach ($messages as $m): ?>
                <div class="msg <?= $m['sender_type'] === 'customer' ? 'customer' : 'staff' ?>">
                    <div class="meta">
                        <?= $m['sender_type'] === 'customer' ? 'Vous' : ab_escape(trim(($m['staff_first_name'] ?? '') . ' ' . ($m['staff_last_name'] ?? '')) ?: $businessName) ?>
                        &middot; <?= ab_format_date($m['created_at']) ?> <?= ab_format_time($m['created_at']) ?>
                    </div>
                    <div><?= nl2br(ab_escape($m['body'])) ?></div>
                    <?php if (!empty($m['attachments'])): ?>
                    <div class="attachments">
                        <?php foreach ($m['attachments'] as $att): ?>
                        <a href="<?= ab_url('public/ticket-attachment.php?id=' . $att['id'] . '&hash=' . urlencode($ticket['hash'])) ?>" target="_blank" rel="noopener">
                            <i class="bi bi-paperclip"></i> <?= ab_escape($att['original_filename']) ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <div id="reply-alert" class="alert d-none"></div>

            <?php if ($ticket['status'] !== 'closed'): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form id="reply-form">
                        <input type="hidden" name="ticket_hash" value="<?= ab_escape($ticket['hash']) ?>">
                        <div class="mb-2">
                            <textarea name="body" class="form-control" rows="3" placeholder="Votre réponse..." required></textarea>
                        </div>
                        <div class="mb-2">
                            <input type="file" name="attachments[]" class="form-control form-control-sm" multiple
                                   accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.csv">
                            <small class="text-muted">Images, PDF, Word, Excel — 5 fichiers max, 5 Mo chacun.</small>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> Envoyer</button>
                    </form>
                </div>
            </div>
            <?php else: ?>
            <p class="text-center text-muted small">Ce ticket est clôturé.</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
    const replyForm = document.getElementById('reply-form');
    if (replyForm) {
        replyForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const alertEl = document.getElementById('reply-alert');
            const formData = new FormData(replyForm);
            formData.append('action', 'reply');

            fetch('<?= ab_url('public/ticket-submit.php') ?>', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.error) {
                        alertEl.className = 'alert alert-danger';
                        alertEl.textContent = data.error;
                        alertEl.classList.remove('d-none');
                        return;
                    }
                    location.reload();
                })
                .catch(() => {
                    alertEl.className = 'alert alert-danger';
                    alertEl.textContent = 'Une erreur est survenue, veuillez réessayer.';
                    alertEl.classList.remove('d-none');
                });
        });
    }
    </script>
</body>
</html>
