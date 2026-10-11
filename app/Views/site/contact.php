<?php use App\Core\Settings; ?>
<section class="hero">
    <div class="container">
        <h1><?= e(t('nav.contact')) ?></h1>
        <p class="lead"><?= e('Sorularınız için bize yazın; en kısa sürede dönüş yapıyoruz.') ?></p>
    </div>
</section>
<section class="section--tight">
    <div class="container">
        <div class="split">
            <div class="card">
                <form method="post" action="<?= e(url('/iletisim')) ?>">
                    <?= csrf_field() ?>
                    <div class="field">
                        <label for="c-name"><?= e(t('auth.name')) ?></label>
                        <input id="c-name" type="text" name="name" required minlength="3" value="<?= e((string) old('name')) ?>">
                    </div>
                    <div class="field">
                        <label for="c-email"><?= e(t('auth.email')) ?></label>
                        <input id="c-email" type="email" name="email" required value="<?= e((string) old('email')) ?>">
                    </div>
                    <div class="field">
                        <label for="c-phone"><?= e(t('auth.phone')) ?> <span class="muted">(<?= e(t('common.optional')) ?>)</span></label>
                        <input id="c-phone" type="tel" name="phone" value="<?= e((string) old('phone')) ?>">
                    </div>
                    <div class="field">
                        <label for="c-message"><?= e('Mesajınız') ?></label>
                        <textarea id="c-message" name="message" required minlength="10"><?= e((string) old('message')) ?></textarea>
                    </div>
                    <button class="btn btn-accent btn-block" type="submit"><?= e('Gönder') ?></button>
                </form>
            </div>
            <div class="stack">
                <div class="card">
                    <h3><?= e('İletişim bilgileri') ?></h3>
                    <p class="mb-0"><strong><?= e(t('auth.email')) ?>:</strong> <?= e((string) Settings::get('brand.email', '-')) ?></p>
                    <p class="mb-0"><strong><?= e(t('auth.phone')) ?>:</strong> <?= e((string) Settings::get('brand.phone', '-')) ?></p>
                    <p class="mb-0"><strong>WhatsApp:</strong> <?= e((string) Settings::get('brand.whatsapp', '-')) ?></p>
                    <p class="mb-0"><strong><?= e('Adres') ?>:</strong> <?= e((string) Settings::get('brand.address', '-')) ?></p>
                </div>
                <div class="card">
                    <h3><?= e('Çalışma saatleri') ?></h3>
                    <p class="muted mb-0"><?= e('Pazartesi – Cuma 09:00 – 18:00') ?></p>
                </div>
            </div>
        </div>
    </div>
</section>
