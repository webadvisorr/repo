<?php
use App\Core\Auth;
use App\Core\Settings;
use App\Core\Translator;
$user = Auth::user();
$current = current_path();
$items = [
    ['/yonetim/', '📊', t('admin.dashboard'), false],
    ['/yonetim/uyeler', '👥', t('admin.members'), false],
    ['/yonetim/planlar', '💳', t('admin.plans'), false],
    ['/yonetim/odemeler', '💰', 'Ödemeler', false],
    ['/yonetim/ayarlar', '⚙️', t('admin.site_identity'), false],
    ['/yonetim/araclar', '🚗', t('admin.vehicles'), true],
    ['/yonetim/evraklar', '📄', t('admin.documents'), true],
    ['/yonetim/sablonlar', '✉️', t('admin.mail_templates'), true],
    ['/yonetim/kampanya', '📣', t('admin.campaigns'), true],
    ['/yonetim/bildirimler', '🔔', t('admin.notifications'), true],
    ['/yonetim/denetim', '🛡️', t('admin.audit'), true],
    ['/yonetim/sistem', '🧩', t('admin.system'), false],
];
?>
<!doctype html>
<html lang="<?= e(Translator::locale()) ?>" dir="<?= e(Translator::direction()) ?>">
<head>
<?php require BASE_PATH . '/app/Views/partials/head.php'; ?>
<style>
    .admin-shell { display: grid; grid-template-columns: 264px 1fr; min-height: 100vh; }
    .admin-side { background: var(--c-card); border-inline-end: 1px solid var(--c-border); padding: 18px 14px; }
    .admin-side__brand { display: flex; align-items: center; gap: 10px; padding: 6px 10px 18px; border-bottom: 1px solid var(--c-border); margin-bottom: 14px; }
    .admin-side nav { display: grid; gap: 4px; }
    .admin-side nav a { display: flex; align-items: center; gap: 11px; padding: 10px 12px; border-radius: 10px; color: var(--c-text); font-size: 14.5px; font-weight: 500; }
    .admin-side nav a:hover { background: color-mix(in srgb, var(--c-primary) 7%, transparent); text-decoration: none; }
    .admin-side nav a.is-active { background: color-mix(in srgb, var(--c-accent) 15%, transparent); color: var(--c-text); font-weight: 700; }
    .admin-side nav a span.soon { margin-inline-start: auto; font-size: 11px; color: var(--c-muted); }
    .admin-main { min-width: 0; }
    .admin-top { display: flex; align-items: center; gap: 12px; padding: 12px 24px; border-bottom: 1px solid var(--c-border); background: var(--c-card); position: sticky; top: 0; z-index: 40; }
    .admin-body { padding: 26px 24px 60px; }
    .admin-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 22px; }
    .admin-tabs a { padding: 9px 15px; border-radius: 99px; border: 1px solid var(--c-border); font-size: 14px; color: var(--c-text); }
    .admin-tabs a:hover { text-decoration: none; border-color: var(--c-accent); }
    .admin-tabs a.is-active { background: var(--c-primary); border-color: var(--c-primary); color: #fff; }
    .field-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; }
    .media-preview { display: flex; align-items: center; gap: 14px; padding: 12px; border: 1px dashed var(--c-border); border-radius: 12px; }
    .media-preview img { max-height: 52px; max-width: 160px; background: repeating-conic-gradient(#f1f5f9 0% 25%, #ffffff 0% 50%) 50% / 14px 14px; border-radius: 7px; padding: 3px; }
    @media (max-width: 900px) {
        .admin-shell { grid-template-columns: 1fr; }
        .admin-side { position: fixed; z-index: 120; inset-block: 0; inset-inline-start: 0; width: 268px; transform: translateX(-102%); transition: transform .25s ease; overflow-y: auto; }
        .admin-side.is-open { transform: none; }
    }
</style>
</head>
<body>
<div class="admin-shell">
    <aside class="admin-side" id="admin-side">
        <div class="admin-side__brand">
            <a class="brand" href="<?= e(url('/yonetim/')) ?>"><?php require BASE_PATH . '/app/Views/partials/brand.php'; ?></a>
        </div>
        <nav>
            <?php foreach ($items as [$href, $icon, $label, $soon]): ?>
                <?php $active = $href === '/yonetim/' ? rtrim($current, '/') === '/yonetim' : str_starts_with($current, $href); ?>
                <a href="<?= e(url($href)) ?>" class="<?= $active ? 'is-active' : '' ?>">
                    <span aria-hidden="true"><?= $icon ?></span>
                    <span><?= e((string) $label) ?></span>
                    <?php if ($soon): ?><span class="soon"><?= e('yakında') ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <div class="admin-main">
        <div class="admin-top">
            <button type="button" class="burger" onclick="document.getElementById('admin-side').classList.toggle('is-open')" aria-label="Menü">☰</button>
            <strong style="font-family:var(--font-head)"><?= e((string) Settings::get('brand.title', 'evrako')) ?> — <?= e(t('admin.dashboard')) ?></strong>
            <div class="header-actions" style="margin-inline-start:auto">
                <div class="lang">
                    <select data-language-select aria-label="<?= e(t('common.language')) ?>">
                        <?php foreach (Translator::options() as $code => $name): ?>
                            <option value="<?= e($code) ?>" <?= $code === Translator::locale() ? 'selected' : '' ?>><?= e($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="button" class="theme-toggle" data-theme-toggle aria-label="<?= e(t('common.theme')) ?>"><span data-theme-icon aria-hidden="true">☾</span></button>
                <a class="btn btn-outline btn-sm" href="<?= e(url('/')) ?>" target="_blank" rel="noopener"><?= e('Siteyi gör') ?></a>
                <a class="btn btn-primary btn-sm" href="<?= e(url('/yonetim/cikis')) ?>"><?= e(t('admin.logout')) ?></a>
            </div>
        </div>
        <div class="admin-body">
            <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
            <?= $content ?? '' ?>
        </div>
    </div>
</div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
