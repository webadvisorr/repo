<?php
use App\Core\Settings;
use App\Core\Translator;
?>
<!doctype html>
<html lang="<?= e(Translator::locale()) ?>" dir="<?= e(Translator::direction()) ?>">
<head>
<?php require BASE_PATH . '/app/Views/partials/head.php'; ?>
</head>
<body style="display:grid;place-items:center;min-height:100vh;padding:24px">
<div style="width:100%;max-width:440px">
    <div class="center mb-3">
        <a class="brand" href="<?= e(url('/')) ?>" style="display:inline-flex"><?php require BASE_PATH . '/app/Views/partials/brand.php'; ?></a>
        <p class="muted mb-0"><?= e((string) Settings::get('brand.slogan', '')) ?></p>
    </div>
    <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
    <?= $content ?? '' ?>
    <p class="center muted mt-3" style="font-size:13px">
        <?= e(t('common.developed_by')) ?> <a href="https://webadvisor.com.tr" target="_blank" rel="noopener noreferrer">webadvisor</a>
    </p>
</div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
