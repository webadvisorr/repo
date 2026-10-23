<?php
use App\Core\Auth;
$user = Auth::user();
$links = [
    ['/' , '🏠', t('nav.home'), __('Ana sayfaya dön')],
    ['/nasil-calisir', '🧭', t('nav.how'), __('Sistemin işleyişi')],
    ['/fiyatlar', '💳', t('nav.pricing'), __('Üyelik planları')],
    ['/iletisim', '✉️', t('nav.contact'), __('Bize ulaşın')],
];
?>
<section class="section">
    <div class="container" style="max-width:820px">
        <div class="error-hero center">
            <div class="error-code" aria-hidden="true">404</div>
            <h1><?= e(__('Aradığınız sayfayı bulamadık')) ?></h1>
            <p class="lead"><?= e($title ?? t('http.404')) ?></p>
            <p class="muted"><?= e(__('Bağlantı taşınmış, adı değişmiş ya da hiç var olmamış olabilir. Aşağıdaki kısayollardan devam edebilirsiniz.')) ?></p>
            <div class="row" style="justify-content:center">
                <a class="btn btn-accent" href="<?= e(url('/')) ?>"><?= e(t('nav.home')) ?></a>
                <?php if ($user !== null): ?>
                    <a class="btn btn-outline" href="<?= e(url('/panel')) ?>"><?= e(t('nav.dashboard')) ?></a>
                <?php else: ?>
                    <button class="btn btn-outline" type="button" data-open-drawer="login"><?= e(t('nav.login')) ?>/<?= e(t('nav.register')) ?></button>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid grid-2 mt-3">
            <?php foreach ($links as [$href, $icon, $label, $hint]): ?>
                <a class="card card--hover error-link" href="<?= e(url($href)) ?>">
                    <span class="feature-icon" aria-hidden="true"><?= $icon ?></span>
                    <span>
                        <strong><?= e($label) ?></strong>
                        <span class="muted" style="display:block;font-size:13.5px"><?= e($hint) ?></span>
                    </span>
                    <span class="menu-arrow" aria-hidden="true">→</span>
                </a>
            <?php endforeach; ?>
        </div>

        <p class="center muted mt-3" style="font-size:13.5px">
            <?= e(__('Aradığınızı bulamadıysanız ')) ?><a href="<?= e(url('/iletisim')) ?>"><?= e(__('bize yazın')) ?></a><?= e(__(' — yardımcı olalım.')) ?>
        </p>
    </div>
</section>
