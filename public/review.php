<?php
/**
 * ABAppointments - Public Review Submission
 */
require_once __DIR__ . '/../core/App.php';

$hash = $_GET['hash'] ?? '';
if (empty($hash)) {
    http_response_code(404);
    exit('Rendez-vous non trouvé.');
}

$reviews = new Reviews();
$appointment = $reviews->getByAppointmentHash($hash);

if (!$appointment) {
    http_response_code(404);
    exit('Rendez-vous non trouvé.');
}

$message = '';
$error = '';
$alreadySubmitted = !empty($appointment['review_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadySubmitted && $appointment['status'] === 'completed') {
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    if ($rating < 1 || $rating > 5) {
        $error = 'Merci de choisir une note entre 1 et 5.';
    } elseif ($reviews->submit((int)$appointment['id'], $rating, $comment)) {
        $message = 'Merci beaucoup pour votre avis !';
        $alreadySubmitted = true;
    } else {
        $error = 'Un avis a déjà été soumis pour ce rendez-vous.';
    }
}

$primaryColor = ab_setting('primary_color', '#e91e63');
$businessName = ab_setting('business_name', 'ABAppointments');
$canReview = $appointment['status'] === 'completed' && !$alreadySubmitted;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Votre avis - <?= ab_escape($businessName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        html, body { overflow-x: hidden; max-width: 100%; }
        body { background: #f8f9fa; }
        .review-card { max-width: 560px; margin: 40px auto; padding: 0 10px; }
        .header-bar { background: <?= $primaryColor ?>; color: #fff; padding: 20px; text-align: center; }
        .star-rating { display: flex; flex-direction: row-reverse; justify-content: center; gap: 6px; font-size: 2.2rem; }
        .star-rating input { display: none; }
        .star-rating label { color: #ddd; cursor: pointer; transition: color 0.15s; }
        .star-rating input:checked ~ label,
        .star-rating label:hover,
        .star-rating label:hover ~ label { color: #ffc107; }
    </style>
</head>
<body>
    <div class="header-bar">
        <h4><i class="bi bi-star-fill"></i> <?= ab_escape($businessName) ?></h4>
    </div>

    <div class="container">
        <div class="review-card">
            <?php if ($message): ?>
            <div class="alert alert-success"><?= ab_escape($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="alert alert-danger"><?= ab_escape($error) ?></div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Votre rendez-vous du <?= ab_format_date($appointment['start_datetime']) ?></h5>
                    <small class="text-muted"><?= ab_escape($appointment['service_name']) ?></small>
                </div>
                <div class="card-body">
                    <?php if (!$canReview && !$alreadySubmitted): ?>
                    <p class="text-muted mb-0">Les avis ne peuvent être laissés qu'après un rendez-vous terminé.</p>
                    <?php elseif ($alreadySubmitted): ?>
                    <p class="text-muted mb-0">Vous avez déjà partagé votre avis pour ce rendez-vous. Merci !</p>
                    <?php else: ?>
                    <form method="POST">
                        <div class="mb-4 text-center">
                            <label class="form-label d-block mb-2">Votre note</label>
                            <div class="star-rating">
                                <input type="radio" name="rating" value="5" id="star5"><label for="star5">★</label>
                                <input type="radio" name="rating" value="4" id="star4"><label for="star4">★</label>
                                <input type="radio" name="rating" value="3" id="star3"><label for="star3">★</label>
                                <input type="radio" name="rating" value="2" id="star2"><label for="star2">★</label>
                                <input type="radio" name="rating" value="1" id="star1"><label for="star1">★</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Votre commentaire (facultatif)</label>
                            <textarea name="comment" class="form-control" rows="4"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-send"></i> Envoyer mon avis</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
