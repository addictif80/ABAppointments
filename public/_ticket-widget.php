<?php
/**
 * ABAppointments - Floating Help Bubble (support tickets)
 * Included at the bottom of public-facing pages (booking widget, account page).
 * Expects core/App.php to already be bootstrapped.
 */
$_ticketWidgetProviders = Database::getInstance()->fetchAll(
    "SELECT id, first_name, last_name FROM ab_users WHERE is_active = 1 ORDER BY first_name"
);
$_ticketWidgetCustomerLoggedIn = !empty($_SESSION['customer_id']);
$_ticketWidgetPrimaryColor = ab_setting('primary_color', '#e91e63');
?>
<style>
    #ab-help-bubble { position: fixed; bottom: 22px; right: 22px; z-index: 1050; }
    #ab-help-toggle { width: 56px; height: 56px; border-radius: 50%; background: <?= $_ticketWidgetPrimaryColor ?>; color: #fff; border: none; box-shadow: 0 4px 16px rgba(0,0,0,0.25); font-size: 1.4rem; display: flex; align-items: center; justify-content: center; cursor: pointer; }
    #ab-help-panel { position: fixed; bottom: 88px; right: 22px; width: 340px; max-width: calc(100vw - 32px); max-height: 70vh; overflow-y: auto; background: #fff; border-radius: 14px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); display: none; z-index: 1050; }
    #ab-help-panel.open { display: block; }
    #ab-help-panel .ab-help-header { background: <?= $_ticketWidgetPrimaryColor ?>; color: #fff; padding: 14px 16px; border-radius: 14px 14px 0 0; font-weight: 600; display: flex; justify-content: space-between; align-items: center; }
    #ab-help-panel .ab-help-body { padding: 16px; }
    #ab-help-panel .form-label { font-size: 0.85rem; margin-bottom: 3px; }
    #ab-help-panel .form-control, #ab-help-panel .form-select { font-size: 0.88rem; }
    @media (max-width: 480px) {
        #ab-help-panel { right: 12px; left: 12px; width: auto; }
        #ab-help-bubble { right: 14px; bottom: 14px; }
    }
</style>

<div id="ab-help-bubble">
    <button type="button" id="ab-help-toggle" aria-label="Aide et support">
        <i class="bi bi-headset"></i>
    </button>
    <div id="ab-help-panel">
        <div class="ab-help-header">
            <span><i class="bi bi-life-preserver"></i> Besoin d'aide ?</span>
            <button type="button" id="ab-help-close" class="btn-close btn-close-white btn-sm" aria-label="Fermer"></button>
        </div>
        <div class="ab-help-body">
            <div id="ab-help-success" class="d-none text-center py-3">
                <i class="bi bi-check-circle-fill text-success" style="font-size:2rem;"></i>
                <p class="mt-2 mb-2">Votre demande a bien été envoyée !</p>
                <a id="ab-help-ticket-link" href="#" class="btn btn-sm btn-outline-primary">Voir mon ticket</a>
            </div>
            <div id="ab-help-alert" class="alert alert-danger d-none py-2"></div>
            <form id="ab-help-form">
                <?php if (!$_ticketWidgetCustomerLoggedIn): ?>
                <div class="mb-2">
                    <label class="form-label">Prénom</label>
                    <input type="text" name="first_name" class="form-control form-control-sm" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Nom</label>
                    <input type="text" name="last_name" class="form-control form-control-sm" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control form-control-sm" required>
                </div>
                <?php endif; ?>
                <div class="mb-2">
                    <label class="form-label">Motif</label>
                    <select name="category" id="ab-help-category" class="form-select form-select-sm" required>
                        <option value="commercial">Commercial (RDV, prestation...)</option>
                        <option value="technique">Technique (site, bug...)</option>
                    </select>
                </div>
                <div class="mb-2" id="ab-help-provider-wrap">
                    <label class="form-label">Prestataire concerné</label>
                    <select name="provider_id" class="form-select form-select-sm">
                        <option value="">Choisir...</option>
                        <?php foreach ($_ticketWidgetProviders as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= ab_escape($p['first_name'] . ' ' . $p['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label">Sujet</label>
                    <input type="text" name="subject" class="form-control form-control-sm" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Message</label>
                    <textarea name="body" class="form-control form-control-sm" rows="3" required></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Pièces jointes</label>
                    <input type="file" name="attachments[]" class="form-control form-control-sm" multiple
                           accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.csv">
                    <small class="text-muted">5 fichiers max, 5 Mo chacun.</small>
                </div>
                <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-send"></i> Envoyer</button>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    const toggle = document.getElementById('ab-help-toggle');
    const panel = document.getElementById('ab-help-panel');
    const closeBtn = document.getElementById('ab-help-close');
    const categorySelect = document.getElementById('ab-help-category');
    const providerWrap = document.getElementById('ab-help-provider-wrap');
    const form = document.getElementById('ab-help-form');
    const alertEl = document.getElementById('ab-help-alert');
    const successEl = document.getElementById('ab-help-success');
    const ticketLink = document.getElementById('ab-help-ticket-link');

    toggle.addEventListener('click', () => panel.classList.toggle('open'));
    closeBtn.addEventListener('click', () => panel.classList.remove('open'));

    function syncProviderField() {
        providerWrap.style.display = categorySelect.value === 'commercial' ? 'block' : 'none';
    }
    categorySelect.addEventListener('change', syncProviderField);
    syncProviderField();

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        alertEl.classList.add('d-none');
        const formData = new FormData(form);
        formData.append('action', 'create');

        fetch('<?= ab_url('public/ticket-submit.php') ?>', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    alertEl.textContent = data.error;
                    alertEl.classList.remove('d-none');
                    return;
                }
                form.classList.add('d-none');
                ticketLink.href = data.ticket_url;
                successEl.classList.remove('d-none');
            })
            .catch(() => {
                alertEl.textContent = 'Une erreur est survenue, veuillez réessayer.';
                alertEl.classList.remove('d-none');
            });
    });
})();
</script>
