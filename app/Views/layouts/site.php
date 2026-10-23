<?php
use App\Core\Auth;
use App\Core\Settings;
use App\Core\Translator;
use App\Services\CurrencyService;
$siteTitle = (string) Settings::get('brand.title', 'Evrako');
$slogan = (string) Settings::get('brand.slogan', '');
$user = Auth::user();
$path = current_path();
$isHome = $path === '/';
$phone = trim((string) Settings::get('brand.phone', ''));
$email = trim((string) Settings::get('brand.email', ''));
$whatsapp = trim((string) Settings::get('brand.whatsapp', ''));
$address = trim((string) Settings::get('brand.address', '') . ' ' . (string) Settings::get('brand.city', ''));
$socialLabels = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube'];
$socials = [];
foreach ($socialLabels as $network => $networkLabel) {
    $value = trim((string) Settings::get('brand.' . $network, ''));
    if ($value === '') {
        continue;
    }
    // Tam URL girilmişse olduğu gibi kullan, aksi hâlde kullanıcı adından profil adresi üret.
    $socials[$network] = preg_match('~^https?://~i', $value) === 1
        ? $value
        : match ($network) {
            'facebook'  => 'https://www.facebook.com/' . rawurlencode(ltrim($value, '@')),
            'instagram' => 'https://www.instagram.com/' . rawurlencode(ltrim($value, '@')),
            'youtube'   => 'https://www.youtube.com/@' . rawurlencode(ltrim($value, '@')),
        };
}
$whatsappUrl = whatsapp_url();
?>
<!doctype html>
<html lang="<?= e(Translator::locale()) ?>" dir="<?= e(Translator::direction()) ?>">
<head>
<?php require BASE_PATH . '/app/Views/partials/head.php'; ?>
</head>
<body>
<a class="skip-link" href="#main"><?= e(__('İçeriğe geç')) ?></a>
<?php require BASE_PATH . '/app/Views/partials/preloader.php'; ?>
<?php require BASE_PATH . '/app/Views/partials/floaters.php'; ?>

<header class="site-header">
    <div class="container site-header__inner">
        <a class="brand" href="<?= e(url('/')) ?>">
            <?php require BASE_PATH . '/app/Views/partials/brand.php'; ?>
        </a>

        <nav class="nav" aria-label="<?= e(__('Ana menü')) ?>">
            <a class="<?= $isHome ? 'is-active' : '' ?>" href="<?= e(url('/')) ?>"><?= e(t('nav.home')) ?></a>
            <a class="<?= is_active('/nasil-calisir') ? 'is-active' : '' ?>" href="<?= e(url('/nasil-calisir')) ?>"><?= e(t('nav.how')) ?></a>
            <a class="<?= is_active('/fiyatlar') ? 'is-active' : '' ?>" href="<?= e(url('/fiyatlar')) ?>"><?= e(t('nav.pricing')) ?></a>
            <a class="<?= is_active('/iletisim') ? 'is-active' : '' ?>" href="<?= e(url('/iletisim')) ?>"><?= e(t('nav.contact')) ?></a>
        </nav>

        <div class="header-actions" style="margin-inline-start:auto">
            <button type="button" class="theme-toggle" data-theme-toggle aria-label="<?= e(t('common.theme')) ?>">
                <span data-theme-icon aria-hidden="true">☾</span>
            </button>
            <?php if ($user !== null): ?>
                <?php require BASE_PATH . '/app/Views/partials/notification_bell.php'; ?>
                <a class="btn btn-primary btn-sm" href="<?= e(url('/panel')) ?>"><?= e(t('nav.dashboard')) ?></a>
            <?php else: ?>
                <button type="button" class="btn btn-auth btn-sm" data-open-drawer="login"><?= e(t('nav.login')) ?><span>/</span><?= e(t('nav.register')) ?></button>
            <?php endif; ?>
            <button type="button" class="burger" data-open-menu aria-controls="mobile-menu" aria-expanded="false" aria-label="<?= e(t('nav.menu')) ?>">
                <span class="burger__bars" aria-hidden="true"><span></span><span></span><span></span></span>
            </button>
        </div>
    </div>
</header>

<?php $navLinks = [
    ['/', 'nav.home'],
    ['/nasil-calisir', 'nav.how'],
    ['/fiyatlar', 'nav.pricing'],
    ['/iletisim', 'nav.contact'],
]; ?>
<div class="mobile-menu-backdrop" data-menu-backdrop></div>
<aside class="mobile-menu" id="mobile-menu" data-mobile-menu aria-hidden="true" aria-label="<?= e(t('nav.menu')) ?>">
    <div class="mobile-menu__head">
        <strong><?= e(t('nav.menu')) ?></strong>
        <button type="button" class="drawer__close" data-close-menu aria-label="<?= e(t('common.close')) ?>">&times;</button>
    </div>
    <nav class="mobile-menu__nav">
        <?php foreach ($navLinks as [$href, $label]): ?>
            <a href="<?= e(url($href)) ?>">
                <span><?= e(t($label)) ?></span>
                <span class="menu-arrow" aria-hidden="true">→</span>
            </a>
        <?php endforeach; ?>
        <div class="mobile-menu__group">
            <?php if ($user !== null): ?>
                <a class="btn btn-primary btn-block" href="<?= e(url('/panel')) ?>"><?= e(t('nav.dashboard')) ?></a>
            <?php else: ?>
                <button type="button" class="btn btn-auth btn-block" data-open-drawer="login"><?= e(t('auth.login')) ?></button>
                <button type="button" class="btn btn-auth btn-block" data-open-drawer="register"><?= e(t('auth.register')) ?></button>
            <?php endif; ?>
        </div>
        <div class="mobile-menu__group mobile-menu__brand">
            <a class="brand" href="<?= e(url('/')) ?>" aria-label="<?= e($siteTitle) ?>">
                <?php require BASE_PATH . '/app/Views/partials/brand.php'; ?>
            </a>
        </div>
    </nav>
</aside>

<?php if ($user === null): ?>
<div class="drawer-backdrop" data-drawer-backdrop></div>
<aside class="drawer" data-drawer aria-hidden="true" aria-label="<?= e(t('nav.login')) ?>">
    <div class="drawer__head">
        <strong><?= e(t('auth.welcome')) ?></strong>
        <button type="button" class="drawer__close" data-close-drawer aria-label="<?= e(t('common.close')) ?>">&times;</button>
    </div>
    <div class="drawer__body">
        <div class="tabs" role="tablist">
            <button type="button" data-tab="login" class="is-active" role="tab" aria-selected="true"><?= e(t('auth.login')) ?></button>
            <button type="button" data-tab="register" role="tab" aria-selected="false"><?= e(t('auth.register')) ?></button>
        </div>

        <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>

        <div class="tab-panel is-active" data-tab-panel="login">
            <form method="post" action="<?= e(url('/giris')) ?>" data-form-validate>
                <?= csrf_field() ?>
                <div class="field<?= old_has_error('email') ? ' has-error' : '' ?>">
                    <label for="drawer-email"><?= e(t('auth.email')) ?></label>
                    <input id="drawer-email" type="email" name="email" required autocomplete="email" value="<?= e((string) old('email')) ?>"<?= old_has_error('email') ? ' aria-invalid="true"' : '' ?>>
                    <?php if (old_has_error('email')): ?><p class="form-error"><?= e(error_first('email')) ?></p><?php endif; ?>
                </div>
                <div class="field<?= old_has_error('password') ? ' has-error' : '' ?>">
                    <label for="drawer-password"><?= e(t('auth.password')) ?></label>
                    <input id="drawer-password" type="password" name="password" required autocomplete="current-password" minlength="8"<?= old_has_error('password') ? ' aria-invalid="true"' : '' ?>>
                    <?php if (old_has_error('password')): ?><p class="form-error"><?= e(error_first('password')) ?></p><?php endif; ?>
                </div>
                <div class="field check">
                    <input id="drawer-remember" type="checkbox" name="remember" value="1">
                    <label for="drawer-remember"><?= e(t('auth.remember')) ?></label>
                </div>
                <button class="btn btn-auth btn-block" type="submit"><?= e(t('auth.login')) ?></button>
                <p class="center mt-2"><a href="<?= e(url('/sifremi-unuttum')) ?>"><?= e(t('auth.forgot')) ?></a></p>
            </form>
        </div>

        <div class="tab-panel" data-tab-panel="register">
            <form method="post" action="<?= e(url('/kayit')) ?>" data-form-validate>
                <?= csrf_field() ?>
                <div class="field<?= old_has_error('full_name') ? ' has-error' : '' ?>">
                    <label for="drawer-name"><?= e(t('auth.name')) ?></label>
                    <input id="drawer-name" type="text" name="full_name" required minlength="3" autocomplete="name" value="<?= e((string) old('full_name')) ?>">
                    <?php if (old_has_error('full_name')): ?><p class="form-error"><?= e(error_first('full_name')) ?></p><?php endif; ?>
                </div>
                <div class="field<?= old_has_error('email') ? ' has-error' : '' ?>">
                    <label for="drawer-remail"><?= e(t('auth.email')) ?></label>
                    <input id="drawer-remail" type="email" name="email" required autocomplete="email" value="<?= e((string) old('email')) ?>">
                    <?php if (old_has_error('email')): ?><p class="form-error"><?= e(error_first('email')) ?></p><?php endif; ?>
                </div>
                <div class="field">
                    <label for="drawer-phone"><?= e(t('auth.phone')) ?> <span class="muted">(<?= e(t('common.optional')) ?>)</span></label>
                    <input id="drawer-phone" type="tel" name="phone" autocomplete="tel">
                </div>
                <div class="field<?= old_has_error('password') ? ' has-error' : '' ?>">
                    <label for="drawer-rpass"><?= e(t('auth.password')) ?></label>
                    <input id="drawer-rpass" type="password" name="password" required minlength="8" autocomplete="new-password">
                    <?php if (old_has_error('password')): ?><p class="form-error"><?= e(error_first('password')) ?></p><?php endif; ?>
                </div>
                <div class="field<?= old_has_error('password_confirmation') ? ' has-error' : '' ?>">
                    <label for="drawer-rpass2"><?= e(t('auth.password_confirm')) ?></label>
                    <input id="drawer-rpass2" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
                    <?php if (old_has_error('password_confirmation')): ?><p class="form-error"><?= e(error_first('password_confirmation')) ?></p><?php endif; ?>
                </div>
                <div class="field check">
                    <input id="drawer-terms" type="checkbox" name="terms" value="1" required>
                    <label for="drawer-terms"><?= e(t('auth.terms')) ?></label>
                </div>
                <div class="field check">
                    <input id="drawer-marketing" type="checkbox" name="marketing_opt_in" value="1" checked>
                    <label for="drawer-marketing"><?= e(t('auth.marketing_opt_in')) ?></label>
                </div>
                <button class="btn btn-auth btn-block" type="submit"><?= e(t('auth.register')) ?></button>
            </form>
        </div>
    </div>
</aside>
<?php endif; ?>

<main id="main">
<?php if ($user !== null): ?>
    <div class="container flash-region"><?php require BASE_PATH . '/app/Views/partials/flash.php'; ?></div>
<?php endif; ?>
<?= $content ?? '' ?>
</main>

<footer class="site-footer">
    <div class="container">
        <div class="grid grid-4 footer-grid">
            <div>
                <div class="brand mb-2"><?php require BASE_PATH . '/app/Views/partials/brand.php'; ?></div>
                <p class="muted mb-2"><?= e($slogan) ?></p>
                <?php if ($socials !== []): ?>
                    <div class="social-links" aria-label="<?= e(__('Sosyal medya')) ?>">
                        <?php foreach ($socials as $network => $href): ?>
                            <a href="<?= e($href) ?>" target="_blank" rel="noopener noreferrer"
                               title="<?= e($socialLabels[$network]) ?>" aria-label="<?= e($socialLabels[$network]) ?>">
                                <?php $icon = $network; $size = 18; require BASE_PATH . '/app/Views/partials/icon.php'; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div>
                <h4><?= e(t('footer.links')) ?></h4>
                <ul>
                    <li><a href="<?= e(url('/nasil-calisir')) ?>"><?= e(t('nav.how')) ?></a></li>
                    <li><a href="<?= e(url('/fiyatlar')) ?>"><?= e(t('nav.pricing')) ?></a></li>
                    <li><a href="<?= e(url('/iletisim')) ?>"><?= e(t('nav.contact')) ?></a></li>
                </ul>
            </div>
            <div>
                <h4><?= e(t('footer.legal')) ?></h4>
                <ul>
                    <li><a href="<?= e(url('/gizlilik')) ?>"><?= e(t('footer.privacy')) ?></a></li>
                    <li><a href="<?= e(url('/kullanim-kosullari')) ?>"><?= e(t('footer.terms')) ?></a></li>
                    <li><a href="<?= e(url('/gizlilik')) ?>"><?= e(t('footer.cookies')) ?></a></li>
                </ul>
            </div>
            <div>
                <h4><?= e(t('nav.contact')) ?></h4>
                <ul class="footer-contact">
                    <?php if ($phone !== ''): ?><li><a href="tel:<?= e(preg_replace('/\s+/', '', $phone) ?? $phone) ?>">📞 <?= e($phone) ?></a></li><?php endif; ?>
                    <?php if ($email !== ''): ?><li><a href="mailto:<?= e($email) ?>">✉️ <?= e($email) ?></a></li><?php endif; ?>
                    <?php if ($whatsappUrl !== ''): ?><li><a class="inline-icon" href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener noreferrer"><?php $icon = 'whatsapp'; $size = 17; require BASE_PATH . '/app/Views/partials/icon.php'; ?> WhatsApp</a></li><?php endif; ?>
                    <?php if ($address !== ''): ?><li class="muted">📍 <?= e($address) ?></li><?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="site-footer__bottom">
            <div class="footer-prefs">
                <div class="lang">
                    <label class="sr-only" for="lang-select"><?= e(t('common.language')) ?></label>
                    <select id="lang-select" data-language-select>
                        <?php foreach (Translator::options() as $code => $name): ?>
                            <option value="<?= e($code) ?>" <?= $code === Translator::locale() ? 'selected' : '' ?>><?= e($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php $currencyOptions = CurrencyService::options(); ?>
                <?php if (count($currencyOptions) > 1): ?>
                    <div class="lang">
                        <label class="sr-only" for="currency-select"><?= e(t('common.currency')) ?></label>
                        <select id="currency-select" data-currency-select>
                            <?php foreach ($currencyOptions as $code => $label): ?>
                                <option value="<?= e($code) ?>" <?= $code === CurrencyService::current() ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>
            <span>&copy; <?= date('Y') ?> <?= e($siteTitle) ?>. <?= e(t('footer.rights')) ?></span>
            <span>
                <?= e(t('common.developed_by')) ?>:
                <a href="<?= e((string) Settings::get('brand.website', 'https://webadvisor.com.tr')) ?>" target="_blank" rel="noopener noreferrer">webadvisor</a>
            </span>
        </div>
    </div>
</footer>

<?php require BASE_PATH . '/app/Views/partials/site_assistant.php'; ?>
<?php require BASE_PATH . '/app/Views/partials/sticky_cta.php'; ?>
<?php require BASE_PATH . '/app/Views/partials/cookie_banner.php'; ?>
<?php require BASE_PATH . '/app/Views/partials/popup.php'; ?>

<?php require BASE_PATH . '/app/Views/partials/hints.php'; ?>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
