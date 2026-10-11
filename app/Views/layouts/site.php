<?php
use App\Core\Auth;
use App\Core\Settings;
use App\Core\Translator;
$siteTitle = (string) Settings::get('brand.title', 'evrako');
$slogan = (string) Settings::get('brand.slogan', '');
$user = Auth::user();
?>
<!doctype html>
<html lang="<?= e(Translator::locale()) ?>" dir="<?= e(Translator::direction()) ?>">
<head>
<?php require BASE_PATH . '/app/Views/partials/head.php'; ?>
</head>
<body>
<?php require BASE_PATH . '/app/Views/partials/preloader.php'; ?>

<header class="site-header">
    <div class="container site-header__inner">
        <a class="brand" href="<?= e(url('/')) ?>">
            <?php require BASE_PATH . '/app/Views/partials/brand.php'; ?>
        </a>

        <nav class="nav" aria-label="Ana menü">
            <a class="is-active" href="<?= e(url('/')) ?>"><?= e(t('nav.home')) ?></a>
            <a href="<?= e(url('/nasil-calisir')) ?>"><?= e(t('nav.how')) ?></a>
            <a href="<?= e(url('/fiyatlar')) ?>"><?= e(t('nav.pricing')) ?></a>
            <a href="<?= e(url('/iletisim')) ?>"><?= e(t('nav.contact')) ?></a>
        </nav>

        <div class="header-actions" style="margin-inline-start:auto">
            <div class="lang">
                <label class="sr-only" for="lang-select"><?= e(t('common.language')) ?></label>
                <select id="lang-select" data-language-select>
                    <?php foreach (Translator::options() as $code => $name): ?>
                        <option value="<?= e($code) ?>" <?= $code === Translator::locale() ? 'selected' : '' ?>><?= e($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="button" class="theme-toggle" data-theme-toggle aria-label="<?= e(t('common.theme')) ?>">
                <span data-theme-icon aria-hidden="true">☾</span>
            </button>
            <?php if ($user !== null): ?>
                <a class="btn btn-outline btn-sm" href="<?= e(url('/panel')) ?>"><?= e(t('nav.dashboard')) ?></a>
                <a class="btn btn-primary btn-sm" href="<?= e(url('/cikis')) ?>"><?= e(t('auth.logout')) ?></a>
            <?php else: ?>
                <button type="button" class="btn btn-primary btn-sm" data-open-drawer="login"><?= e(t('nav.login')) ?><span>/</span><?= e(t('nav.register')) ?></button>
            <?php endif; ?>
            <button type="button" class="burger" data-open-drawer="login" aria-label="Menü">☰</button>
        </div>
    </div>
</header>

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
            <form method="post" action="<?= e(url('/giris')) ?>">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="drawer-email"><?= e(t('auth.email')) ?></label>
                    <input id="drawer-email" type="email" name="email" required autocomplete="email" value="<?= e((string) old('email')) ?>">
                </div>
                <div class="field">
                    <label for="drawer-password"><?= e(t('auth.password')) ?></label>
                    <input id="drawer-password" type="password" name="password" required autocomplete="current-password" minlength="8">
                </div>
                <div class="field check">
                    <input id="drawer-remember" type="checkbox" name="remember" value="1">
                    <label for="drawer-remember"><?= e(t('auth.remember')) ?></label>
                </div>
                <button class="btn btn-accent btn-block" type="submit"><?= e(t('auth.login')) ?></button>
                <p class="center mt-2"><a href="<?= e(url('/sifremi-unuttum')) ?>"><?= e(t('auth.forgot')) ?></a></p>
            </form>
        </div>

        <div class="tab-panel" data-tab-panel="register">
            <form method="post" action="<?= e(url('/kayit')) ?>">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="drawer-name"><?= e(t('auth.name')) ?></label>
                    <input id="drawer-name" type="text" name="full_name" required minlength="3" autocomplete="name">
                </div>
                <div class="field">
                    <label for="drawer-remail"><?= e(t('auth.email')) ?></label>
                    <input id="drawer-remail" type="email" name="email" required autocomplete="email">
                </div>
                <div class="field">
                    <label for="drawer-phone"><?= e(t('auth.phone')) ?> <span class="muted">(<?= e(t('common.optional')) ?>)</span></label>
                    <input id="drawer-phone" type="tel" name="phone" autocomplete="tel">
                </div>
                <div class="field">
                    <label for="drawer-rpass"><?= e(t('auth.password')) ?></label>
                    <input id="drawer-rpass" type="password" name="password" required minlength="8" autocomplete="new-password">
                </div>
                <div class="field">
                    <label for="drawer-rpass2"><?= e(t('auth.password_confirm')) ?></label>
                    <input id="drawer-rpass2" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
                </div>
                <div class="field check">
                    <input id="drawer-terms" type="checkbox" name="terms" value="1" required>
                    <label for="drawer-terms"><?= e(t('auth.terms')) ?></label>
                </div>
                <div class="field check">
                    <input id="drawer-marketing" type="checkbox" name="marketing_opt_in" value="1" checked>
                    <label for="drawer-marketing"><?= e(t('auth.marketing_opt_in')) ?></label>
                </div>
                <button class="btn btn-accent btn-block" type="submit"><?= e(t('auth.register')) ?></button>
            </form>
        </div>
    </div>
</aside>
<?php endif; ?>

<main>
<?= $content ?? '' ?>
</main>

<footer class="site-footer">
    <div class="container">
        <div class="grid grid-3">
            <div>
                <div class="brand mb-2"><?php require BASE_PATH . '/app/Views/partials/brand.php'; ?></div>
                <p class="muted mb-0"><?= e($slogan) ?></p>
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
                </ul>
            </div>
        </div>
        <div class="site-footer__bottom">
            <span>&copy; <?= date('Y') ?> <?= e($siteTitle) ?>. <?= e(t('footer.rights')) ?></span>
            <span>
                <?= e(t('common.developed_by')) ?>:
                <a href="<?= e((string) Settings::get('brand.website', 'https://webadvisor.com.tr')) ?>" target="_blank" rel="noopener noreferrer">webadvisor</a>
            </span>
        </div>
    </div>
</footer>

<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
