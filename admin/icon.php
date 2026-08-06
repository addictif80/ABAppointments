<?php
require_once __DIR__ . '/../core/App.php';

$primaryColor = ab_setting('primary_color', '#e91e63');
$maskable = isset($_GET['maskable']);
// Maskable icons need the visual to stay inside a safe center zone (~80% of the canvas)
$padding = $maskable ? 90 : 30;

header('Content-Type: image/svg+xml');
header('Cache-Control: public, max-age=86400');
?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
    <rect width="512" height="512" rx="<?= $maskable ? 0 : 96 ?>" fill="<?= ab_escape($primaryColor) ?>"/>
    <g transform="translate(<?= $padding ?>,<?= $padding ?>)" fill="#ffffff">
        <?php $s = 512 - 2 * $padding; ?>
        <rect x="0" y="<?= $s * 0.12 ?>" width="<?= $s ?>" height="<?= $s * 0.78 ?>" rx="<?= $s * 0.08 ?>" fill="none" stroke="#ffffff" stroke-width="<?= $s * 0.06 ?>"/>
        <rect x="0" y="<?= $s * 0.12 ?>" width="<?= $s ?>" height="<?= $s * 0.18 ?>" fill="#ffffff"/>
        <circle cx="<?= $s * 0.28 ?>" cy="<?= $s * 0.55 ?>" r="<?= $s * 0.07 ?>"/>
        <circle cx="<?= $s * 0.5 ?>" cy="<?= $s * 0.55 ?>" r="<?= $s * 0.07 ?>"/>
        <circle cx="<?= $s * 0.72 ?>" cy="<?= $s * 0.55 ?>" r="<?= $s * 0.07 ?>"/>
        <circle cx="<?= $s * 0.28 ?>" cy="<?= $s * 0.78 ?>" r="<?= $s * 0.07 ?>"/>
    </g>
</svg>
