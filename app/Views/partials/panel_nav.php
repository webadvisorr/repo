<?php
/** Müşteri paneli üst menüsü. $active: dashboard|vehicles|profile|notifications|messages|support|calendar|billing|payments|team */
use App\Core\Auth;
use App\Services\InboxService;
use App\Services\SupportService;

$active = $active ?? '';
$navUserId = (int) (Auth::id() ?? 0);
$navUnread = InboxService::available() ? InboxService::unreadCount($navUserId) : 0;
$navSupportUnread = SupportService::available() ? SupportService::userUnread($navUserId) : 0;
$navItems = [
    ['dashboard',     '/panel',               '👤', t('nav.dashboard')],
    ['vehicles',      '/panel/araclar',       '🚗', t('vehicles.title')],
    ['profile',       '/panel/profil',        '📋', __('Hesabım')],
    ['notifications', '/panel/bildirimler',   '🔔', t('inbox.title'), $navUnread],
    ['messages',      '/panel/mesajlar',      '✉️', __('Mesajlar')],
    ['support',       '/panel/destek',        '🎧', __('Destek'), $navSupportUnread],
    ['calendar',      '/panel/takvim',        '🗓️', t('calendar.title')],
    ['team',          '/panel/ekip',          '👥', t('team.title')],
    ['billing',       '/panel/odeme',         '💳', t('billing.title')],
    ['payments',      '/panel/odemelerim',    '🧾', t('billing.history')],
];
?>
<nav class="panel-nav" aria-label="<?= e(t('nav.dashboard')) ?>">
    <?php foreach ($navItems as $item): ?>
        <?php [$key, $href, $icon, $label] = $item; $count = (int) ($item[4] ?? 0); ?>
        <a class="btn <?= $active === $key ? 'btn-accent' : 'btn-outline' ?> btn-sm"
           href="<?= e(url($href)) ?>" title="<?= e($label) ?>" aria-label="<?= e($label) ?>">
            <span aria-hidden="true"><?= $icon ?></span>
            <span class="panel-nav__text"><?= e($label) ?></span>
            <?php if ($count > 0): ?><span class="panel-nav__count"><?= $count > 99 ? '99+' : $count ?></span><?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>
