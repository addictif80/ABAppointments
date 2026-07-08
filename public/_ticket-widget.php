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
    #ab-help-panel .ab-help-view.d-none { display: none; }
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

            <!-- Choice screen -->
            <div id="ab-help-view-menu" class="ab-help-view">
                <p class="text-muted small mb-3">Comment pouvons-nous vous aider ?</p>
                <button type="button" class="btn btn-primary w-100 mb-2 ab-help-goto" data-target="ab-help-view-create">
                    <i class="bi bi-plus-circle"></i> Ouvrir un ticket
                </button>
                <button type="button" class="btn btn-outline-secondary w-100 ab-help-goto" data-target="ab-help-view-recover">
                    <i class="bi bi-envelope-paper"></i> Retrouver un ticket existant
                </button>
            </div>

            <!-- Recovery screen -->
            <div id="ab-help-view-recover" class="ab-help-view d-none">
                <button type="button" class="btn btn-link btn-sm px-0 mb-2 ab-help-back"><i class="bi bi-arrow-left"></i> Retour</button>
                <p class="text-muted small">Saisissez l'email utilisé pour votre ticket, nous vous renverrons le lien.</p>
                <div id="ab-help-recover-success" class="alert alert-success d-none py-2"></div>
                <div id="ab-help-recover-alert" class="alert alert-danger d-none py-2"></div>
                <form id="ab-help-recover-form">
                    <div class="mb-2">
                        <input type="email" name="email" class="form-control form-control-sm" placeholder="votre@email.com" required>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-send"></i> Envoyer</button>
                </form>
            </div>

            <!-- Create ticket screen -->
            <div id="ab-help-view-create" class="ab-help-view d-none">
                <button type="button" class="btn btn-link btn-sm px-0 mb-2 ab-help-back"><i class="bi bi-arrow-left"></i> Retour</button>

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
                    <div class="mb-2">
                        <label class="form-label">Date de naissance <span class="text-muted">(optionnel)</span></label>
                        <input type="text" name="date_of_birth" id="ab-help-dob" class="form-control form-control-sm" placeholder="JJ.MM.AAAA" maxlength="10">
                        <small class="text-muted">Permet de retrouver vos tickets dans votre espace client.</small>
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
</div>

<script>
(function() {
    const toggle = document.getElementById('ab-help-toggle');
    const panel = document.getElementById('ab-help-panel');
    const closeBtn = document.getElementById('ab-help-close');
    const views = document.querySelectorAll('.ab-help-view');

    function showView(id) {
        views.forEach(v => v.classList.toggle('d-none', v.id !== id));
    }

    toggle.addEventListener('click', () => panel.classList.toggle('open'));
    closeBtn.addEventListener('click', () => panel.classList.remove('open'));

    document.querySelectorAll('.ab-help-goto').forEach(btn => {
        btn.addEventListener('click', () => showView(btn.dataset.target));
    });
    document.querySelectorAll('.ab-help-back').forEach(btn => {
        btn.addEventListener('click', () => showView('ab-help-view-menu'));
    });

    // -- Create ticket form --
    const categorySelect = document.getElementById('ab-help-category');
    const providerWrap = document.getElementById('ab-help-provider-wrap');
    const form = document.getElementById('ab-help-form');
    const alertEl = document.getElementById('ab-help-alert');
    const successEl = document.getElementById('ab-help-success');
    const ticketLink = document.getElementById('ab-help-ticket-link');

    const dobInput = document.getElementById('ab-help-dob');
    if (dobInput) {
        dobInput.addEventListener('input', function() {
            let v = this.value.replace(/\D/g, '');
            if (v.length > 2) v = v.substring(0, 2) + '.' + v.substring(2);
            if (v.length > 5) v = v.substring(0, 5) + '.' + v.substring(5);
            this.value = v.substring(0, 10);
        });
    }

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

    // -- Recovery form --
    const recoverForm = document.getElementById('ab-help-recover-form');
    const recoverAlert = document.getElementById('ab-help-recover-alert');
    const recoverSuccess = document.getElementById('ab-help-recover-success');

    recoverForm.addEventListener('submit', function(e) {
        e.preventDefault();
        recoverAlert.classList.add('d-none');
        const formData = new FormData(recoverForm);
        formData.append('action', 'recover');

        fetch('<?= ab_url('public/ticket-submit.php') ?>', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    recoverAlert.textContent = data.error;
                    recoverAlert.classList.remove('d-none');
                    return;
                }
                recoverForm.classList.add('d-none');
                recoverSuccess.textContent = data.message || 'Si un ticket existe pour cet email, vous allez recevoir un message avec vos liens.';
                recoverSuccess.classList.remove('d-none');
            })
            .catch(() => {
                recoverAlert.textContent = 'Une erreur est survenue, veuillez réessayer.';
                recoverAlert.classList.remove('d-none');
            });
    });
})();
</script>
