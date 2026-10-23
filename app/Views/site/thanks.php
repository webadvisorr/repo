<?php
use App\Core\Settings;
$name = trim((string) ($name ?? ''));
$greeting = $name !== '' ? $name . ', ' : '';
?>
<section class="section">
    <div class="container" style="max-width:760px">
        <div class="card thanks-card center">
            <div class="thanks-check" aria-hidden="true">
                <svg viewBox="0 0 52 52" width="82" height="82">
                    <circle class="thanks-check__circle" cx="26" cy="26" r="24" fill="none" stroke="currentColor" stroke-width="3"/>
                    <path class="thanks-check__mark" fill="none" stroke="currentColor" stroke-width="4"
                          stroke-linecap="round" stroke-linejoin="round" d="M15 27 L23 35 L38 18"/>
                </svg>
            </div>
            <h1 class="mt-3"><?= e(__('Teşekkürler!')) ?></h1>
            <p class="lead" style="margin-inline:auto;max-width:520px">
                <?= e(__(':greeting Mesajınız bize ulaştı. En kısa sürede e-posta ile dönüş yapacağız.', ['greeting' => $greeting])) ?>
            </p>
            <div class="row" style="justify-content:center">
                <a class="btn btn-accent" href="<?= e(url('/')) ?>"><?= e(t('nav.home')) ?></a>
                <a class="btn btn-outline" href="<?= e(url('/panel')) ?>"><?= e(t('nav.dashboard')) ?></a>
            </div>
        </div>

        <div class="grid grid-3 mt-3">
            <div class="card card--hover">
                <div class="feature-icon">📥</div>
                <h3><?= e(__('1. Talebiniz kaydedildi')) ?></h3>
                <p class="muted mb-0"><?= e(__('Mesajınız destek kuyruğuna düştü.')) ?></p>
            </div>
            <div class="card card--hover">
                <div class="feature-icon">✉️</div>
                <h3><?= e(__('2. Yanıt hazırlanıyor')) ?></h3>
                <p class="muted mb-0"><?= e(__('Aynı gün içinde, en geç 1 iş gününde döneriz.')) ?></p>
            </div>
            <div class="card card--hover">
                <div class="feature-icon">🚗</div>
                <h3><?= e(__('3. Beklerken başlayın')) ?></h3>
                <p class="muted mb-0"><?= e(__('Ücretsiz hesap açıp aracınızı ekleyebilirsiniz.')) ?></p>
            </div>
        </div>

        <?php $phone = (string) Settings::get('brand.phone', ''); $wa = whatsapp_url(); ?>
        <?php if ($phone !== '' || $wa !== ''): ?>
        <p class="center muted mt-3" style="font-size:13.5px">
            <?= e(__('Acil durumlar için: ')) ?>
            <?php if ($phone !== ''): ?><a href="tel:<?= e(preg_replace('/\s+/', '', $phone) ?? $phone) ?>"><?= e($phone) ?></a><?php endif; ?>
            <?php if ($phone !== '' && $wa !== ''): ?> · <?php endif; ?>
            <?php if ($wa !== ''): ?><a href="<?= e($wa) ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a><?php endif; ?>
        </p>
        <?php endif; ?>
    </div>
</section>
