<?php
use App\Core\Settings;

$email = (string) Settings::get('brand.email', '');
$phone = (string) Settings::get('brand.phone', '');
$whatsapp = (string) Settings::get('brand.whatsapp', '');
$address = trim((string) Settings::get('brand.address', '') . ' ' . (string) Settings::get('brand.city', '') . ' ' . (string) Settings::get('brand.country', ''));
$waUrl = whatsapp_url();
?>
<section class="hero">
    <div class="container">
        <span class="badge badge--info"><?= e(__('7/24 form')) ?></span>
        <h1 class="mt-2"><?= e(t('nav.contact')) ?></h1>
        <p class="lead"><?= e(__('Sorularınız için bize yazın; en kısa sürede dönüş yapıyoruz.')) ?></p>
    </div>
</section>
<section class="section--tight">
    <div class="container">
        <div class="split">
            <div class="card">
                <h3 style="font-size:1.05rem"><?= e(__('Bize yazın')) ?></h3>
                <form method="post" action="<?= e(url('/iletisim')) ?>" data-form-validate>
                    <?= csrf_field() ?>
                    <div class="field<?= old_has_error('name') ? ' has-error' : '' ?>">
                        <label for="c-name"><?= e(t('auth.name')) ?></label>
                        <input id="c-name" type="text" name="name" required minlength="3" value="<?= e((string) old('name')) ?>"<?= old_has_error('name') ? ' aria-invalid="true"' : '' ?>>
                        <?php if (old_has_error('name')): ?><p class="form-error"><?= e(error_first('name')) ?></p><?php endif; ?>
                    </div>
                    <div class="field<?= old_has_error('email') ? ' has-error' : '' ?>">
                        <label for="c-email"><?= e(t('auth.email')) ?></label>
                        <input id="c-email" type="email" name="email" required value="<?= e((string) old('email')) ?>"<?= old_has_error('email') ? ' aria-invalid="true"' : '' ?>>
                        <?php if (old_has_error('email')): ?><p class="form-error"><?= e(error_first('email')) ?></p><?php endif; ?>
                    </div>
                    <div class="field<?= old_has_error('phone') ? ' has-error' : '' ?>">
                        <label for="c-phone"><?= e(t('auth.phone')) ?> <span class="muted">(<?= e(t('common.optional')) ?>)</span></label>
                        <input id="c-phone" type="tel" name="phone" value="<?= e((string) old('phone')) ?>">
                        <?php if (old_has_error('phone')): ?><p class="form-error"><?= e(error_first('phone')) ?></p><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="c-subject"><?= e(__('Konu')) ?> <span class="muted">(<?= e(t('common.optional')) ?>)</span></label>
                        <input id="c-subject" type="text" name="subject" maxlength="120" value="<?= e((string) old('subject')) ?>">
                    </div>
                    <div class="field<?= old_has_error('message') ? ' has-error' : '' ?>">
                        <label for="c-message"><?= e(__('Mesajınız')) ?></label>
                        <textarea id="c-message" name="message" required minlength="10" maxlength="5000"<?= old_has_error('message') ? ' aria-invalid="true"' : '' ?>><?= e((string) old('message')) ?></textarea>
                        <p class="hint"><span data-char-count-for="c-message" data-max="5000">0</span> / 5000</p>
                        <?php if (old_has_error('message')): ?><p class="form-error"><?= e(error_first('message')) ?></p><?php endif; ?>
                    </div>
                    <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="hp-field">
                    <button class="btn btn-accent btn-block" type="submit" data-loading-text="<?= e(__('Gönderiliyor…')) ?>">
                        <span class="btn__icon" aria-hidden="true">✉️</span><span><?= e(__('Gönder')) ?></span>
                    </button>
                    <p class="hint center mt-2"><?= e(__('Formu göndererek '))?><a href="<?= e(url('/gizlilik')) ?>"><?= e(t('footer.privacy')) ?></a><?= e(__(' metnini okuduğunuzu kabul edersiniz.')) ?></p>
                </form>
            </div>
            <div class="stack">
                <div class="card card--hover">
                    <h3><?= e(__('İletişim bilgileri')) ?></h3>
                    <ul class="contact-list">
                        <?php if ($email !== ''): ?>
                            <li><span aria-hidden="true">✉️</span><span><strong>E-posta</strong><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></span></li>
                        <?php endif; ?>
                        <?php if ($phone !== ''): ?>
                            <li><span aria-hidden="true">📞</span><span><strong>Telefon</strong><a href="tel:<?= e(preg_replace('/\s+/', '', $phone) ?? $phone) ?>"><?= e($phone) ?></a></span></li>
                        <?php endif; ?>
                        <?php if ($waUrl !== ''): ?>
                            <li><span class="contact-list__icon" aria-hidden="true"><?php $icon = 'whatsapp'; $size = 20; require BASE_PATH . '/app/Views/partials/icon.php'; ?></span><span><strong>WhatsApp</strong><a href="<?= e($waUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e($whatsapp) ?></a></span></li>
                        <?php endif; ?>
                        <?php if ($address !== ''): ?>
                            <li><span aria-hidden="true">📍</span><span><strong>Adres</strong><?= e($address) ?></span></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="card card--hover">
                    <h3><?= e(__('Çalışma saatleri')) ?></h3>
                    <p class="mb-0"><?= e(__('Pazartesi – Cuma 09:00 – 18:00')) ?></p>
                    <p class="muted mb-0" style="font-size:13.5px"><?= e(__('Formlar 7/24 ulaşır; ortalama yanıt süresi 1 iş günüdür.')) ?></p>
                </div>
                <div class="card card--hover">
                    <h3><?= e(__('Hızlı yardım')) ?></h3>
                    <ul class="mb-0">
                        <li><a href="<?= e(url('/nasil-calisir')) ?>"><?= e(__('Sistem nasıl çalışır?')) ?></a></li>
                        <li><a href="<?= e(url('/fiyatlar')) ?>"><?= e(__('Plan ve fiyatlar')) ?></a></li>
                        <li><a href="<?= e(url('/gizlilik')) ?>"><?= e(t('footer.privacy')) ?></a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>
