<?php
/**
 * Müşteri paneli bildirim merkezi (tam sayfa).
 * Zil panelindeki son 8 kaydın tamamı, filtre ve okundu işlemleri burada.
 */

use App\Core\Csrf;
use App\Core\Security;
use App\Services\InboxService;

$active = 'notifications';
$unread = (int) ($unread ?? 0);
$counts = $counts ?? [];
$kind = (string) ($kind ?? '');
$tabKinds = [];
foreach (InboxService::KIND_ORDER as $tabKind) {
    if ((int) ($counts[$tabKind]['total'] ?? 0) > 0) {
        $tabKinds[$tabKind] = $counts[$tabKind];
    }
}
$feedEmptyTitle = $kind !== '' ? InboxService::kindLabel($kind) . ' · ' . t('inbox.empty_title') : t('inbox.empty_title');
$pages = max(1, (int) ($pages ?? 1));
$page = max(1, (int) ($page ?? 1));
$linkFor = static function (array $row): string {
    $link = (string) ($row['link_url'] ?? '');
    if (preg_match('~^https?://~i', $link) === 1) {
        return $link;
    }
    return url($link !== '' ? $link : '/panel/bildirimler');
};
?>
<section class="section section--tight">
    <div class="container">
        <div class="row row--between mb-3" style="align-items:flex-start;gap:12px;flex-wrap:wrap">
            <div>
                <h1 style="font-size:1.6rem;margin:0 0 4px">🔔 <?= e(__('Bildirimler')) ?></h1>
                <p class="muted mb-0">
                    <?= e(__('Evrak hatırlatmaları ve :brand duyuruları burada toplanır.', ['brand' => (string) setting('brand.title', 'Evrako')])) ?>
                    <?php if ($unread > 0): ?>
                        <span class="badge badge--warn" style="margin-inline-start:6px"><?= e(__(':count okunmamış', ['count' => $unread])) ?></span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="row" style="gap:8px">
                <form method="post" action="<?= e(url('/panel/bildirimler/tumu-oku')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-outline btn-sm" type="submit" <?= $unread > 0 ? '' : 'disabled' ?>>
                        <?= e(__('Tümünü okundu işaretle')) ?>
                    </button>
                </form>
                <a class="btn btn-outline btn-sm" href="<?= e(url('/panel')) ?>">← <?= e(t('nav.dashboard')) ?></a>
            </div>
        </div>

        <?php require BASE_PATH . '/app/Views/partials/panel_nav.php'; ?>

        <?php if ($tabKinds !== []): ?>
            <nav class="inbox-filter" aria-label="<?= e(t('inbox.title')) ?>">
                <a class="inbox-filter__tab<?= $kind === '' ? ' is-active' : '' ?>" href="<?= e(url('/panel/bildirimler')) ?>">
                    <span><?= e(t('inbox.all')) ?></span>
                    <?php if ($unread > 0): ?><span class="inbox-filter__count"><?= (int) $unread ?></span><?php endif; ?>
                </a>
                <?php foreach ($tabKinds as $tabKind => $row): ?>
                    <a class="inbox-filter__tab<?= $kind === $tabKind ? ' is-active' : '' ?>" href="<?= e(url('/panel/bildirimler?tur=' . $tabKind)) ?>">
                        <span aria-hidden="true"><?= InboxService::kindIcon($tabKind) ?></span>
                        <span><?= e(InboxService::kindLabel($tabKind)) ?></span>
                        <?php if ((int) $row['unread'] > 0): ?><span class="inbox-filter__count"><?= (int) $row['unread'] ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

        <?php if (!empty($pushEnabled) && !empty($pushKey)): ?>
            <div class="card device-push mb-3" data-device-push
                 data-key="<?= e((string) $pushKey) ?>"
                 data-sw="<?= e(url('/sw.js')) ?>"
                 data-subscribe="<?= e(url('/panel/bildirimler/cihaz')) ?>"
                 data-unsubscribe="<?= e(url('/panel/bildirimler/cihaz/kapat')) ?>"
                 data-csrf="<?= e(Csrf::token()) ?>">
                <div class="row row--between" style="align-items:flex-start;gap:12px;flex-wrap:wrap">
                    <div style="flex:1;min-width:240px">
                        <h2 style="font-size:1.05rem">📱 <?= e(__('Cihaz bildirimleri')) ?></h2>
                        <p class="muted mb-0" style="font-size:13.5px" data-push-status>
                            <?= e(__('Bu cihazı bağlayın; yeni duyurular ve evrak hatırlatmaları telefonunuza veya bilgisayarınıza anında bildirim olarak düşsün.')) ?>
                        </p>
                    </div>
                    <span class="badge badge--info" data-push-badge style="white-space:nowrap"><?= e(__('Kapalı')) ?></span>
                </div>
                <div class="row mt-2" style="gap:8px;flex-wrap:wrap">
                    <button type="button" class="btn btn-primary btn-sm" data-push-enable>
                        <?= e(__('Bu cihazda bildirimleri aç')) ?>
                    </button>
                    <button type="button" class="btn btn-outline btn-sm" data-push-disable hidden>
                        <?= e(__('Bu cihazda kapat')) ?>
                    </button>
                    <span class="muted" style="font-size:12.5px;align-self:center" data-push-devices>
                        <?= e(__('Bu hesaba bağlı cihaz: ') . (int) ($pushDevices ?? 0)) ?>
                    </span>
                </div>
                <p class="hint mb-0 mt-2" style="font-size:12px">
                    <?= e(__('Bildirim izni tarayıcıya özeldir; her cihazda ayrı açmanız gerekir. iPhone/iPad’de Safari → Paylaş → “Ana Ekrana Ekle” ile siteyi ekledikten sonra bildirimler görünür (iOS 16.4+).')) ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if (($rows ?? []) === []): ?>
            <div class="card center">
                <div class="feature-icon" style="margin-inline:auto">📭</div>
                <h2 style="font-size:1.15rem"><?= e($feedEmptyTitle) ?></h2>
                <p class="muted mb-0"><?= e(__('Evraklarınızın süresi yaklaştığında ve yeni duyurular yayınlandığında burada görürsünüz.')) ?></p>
            </div>
        <?php else: ?>
            <div class="inbox-feed" data-inbox-feed>
                <?php foreach ($rows as $row): ?>
                    <?php $isUnread = ($row['read_at'] ?? null) === null; ?>
                    <article class="inbox-card<?= $isUnread ? ' is-unread' : '' ?>" data-inbox-card data-id="<?= (int) $row['id'] ?>">
                        <?php if (!empty($row['image_path'])): ?>
                            <a class="inbox-card__media" href="<?= e($linkFor($row)) ?>">
                                <img src="<?= e((string) InboxService::imageUrl((string) $row['image_path'])) ?>" alt="" loading="lazy">
                            </a>
                        <?php else: ?>
                            <span class="inbox-card__icon" aria-hidden="true"><?= InboxService::kindIcon((string) $row['kind']) ?></span>
                        <?php endif; ?>

                        <div class="inbox-card__body">
                            <div class="inbox-card__top">
                                <span class="inbox-card__kind"><?= e(InboxService::kindLabel((string) $row['kind'])) ?> · <?= e(time_ago((string) $row['created_at'])) ?></span>
                                <?php if ($isUnread): ?><span class="badge badge--warn"><?= e(__('Yeni')) ?></span><?php endif; ?>
                            </div>
                            <h2 class="inbox-card__title"><?= e((string) $row['title']) ?></h2>
                            <?php if (($row['body'] ?? '') !== ''): ?>
                                <p class="inbox-card__text"><?= nl2br(e((string) $row['body'])) ?></p>
                            <?php endif; ?>
                            <div class="inbox-card__actions">
                                <?php if (($row['link_url'] ?? '') !== ''): ?>
                                    <a class="btn btn-accent btn-sm" href="<?= e($linkFor($row)) ?>"
                                       data-inbox-follow data-id="<?= (int) $row['id'] ?>">
                                        <?= e((string) ($row['link_label'] ?: __('Detayı gör'))) ?>
                                    </a>
                                <?php endif; ?>
                                <?php if ($isUnread): ?>
                                    <button type="button" class="btn btn-outline btn-sm" data-inbox-mark data-id="<?= (int) $row['id'] ?>">
                                        <?= e(__('Okundu işaretle')) ?>
                                    </button>
                                <?php endif; ?>
                                <span class="muted" style="font-size:12px"><?= e(format_date((string) $row['created_at'], 'd.m.Y H:i')) ?></span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($pages > 1): ?>
                <nav class="row mt-3" style="gap:8px;justify-content:center" aria-label="<?= e(__('Sayfalar')) ?>">
                    <?php $pageQuery = $kind !== '' ? 'tur=' . rawurlencode($kind) . '&' : ''; ?>
                    <?php if ($page > 1): ?>
                        <a class="btn btn-outline btn-sm" href="<?= e(url('/panel/bildirimler?' . $pageQuery . 'sayfa=' . ($page - 1))) ?>">← <?= e(__('Önceki')) ?></a>
                    <?php endif; ?>
                    <span class="muted" style="align-self:center;font-size:13px"><?= e($page . ' / ' . $pages) ?></span>
                    <?php if ($page < $pages): ?>
                        <a class="btn btn-outline btn-sm" href="<?= e(url('/panel/bildirimler?' . $pageQuery . 'sayfa=' . ($page + 1))) ?>"><?= e(__('Sonraki')) ?> →</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<script nonce="<?= e(Security::nonce()) ?>">
(function () {
    var csrf = <?= json_encode(Csrf::token(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var readUrl = <?= json_encode(url('/panel/bildirimler/oku'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var feed = document.querySelector('[data-inbox-feed]');

    function post(id) {
        var data = new FormData();
        data.set('_token', csrf);
        data.set('id', id);
        return fetch(readUrl, {
            method: 'POST', body: data, keepalive: true, credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
    }

    function markLocally(card) {
        if (!card) { return; }
        card.classList.remove('is-unread');
        var badge = card.querySelector('.badge--warn');
        if (badge) { badge.remove(); }
        var btn = card.querySelector('[data-inbox-mark]');
        if (btn) { btn.remove(); }
    }

    if (feed) {
        feed.addEventListener('click', function (e) {
            var markBtn = e.target.closest('[data-inbox-mark]');
            if (markBtn) {
                e.preventDefault();
                var card = markBtn.closest('[data-inbox-card]');
                post(markBtn.getAttribute('data-id')).then(function () { markLocally(card); }).catch(function () {});
                return;
            }
            var follow = e.target.closest('[data-inbox-follow]');
            if (follow) {
                var card2 = follow.closest('[data-inbox-card]');
                if (card2 && card2.classList.contains('is-unread')) {
                    post(follow.getAttribute('data-id')).catch(function () {});
                }
            }
        });
    }
})();
</script>
<script nonce="<?= e(Security::nonce()) ?>">
(function () {
    var box = document.querySelector('[data-device-push]');
    if (!box) { return; }

    var T = <?= json_attr([
        'on' => __('Açık'),
        'blocked' => __('İzin verilmedi'),
        'error' => __('Kullanılamıyor'),
        'off' => __('Kapalı'),
        'devices' => __('Bu hesaba bağlı cihaz: :count'),
        'generic' => __('İşlem tamamlanamadı.'),
        'unsupported' => __('Bu tarayıcı cihaz bildirimlerini desteklemiyor. Güncel Chrome, Edge, Firefox veya Safari kullanın.'),
        'denied' => __('Bu tarayıcıda bildirim izni engellenmiş. Adres çubuğundaki kilit simgesinden izin verip sayfayı yenileyin.'),
        'connected' => __('Bu cihaz bağlı. Yeni duyurular ve hatırlatmalar burada anında görünecek.'),
        'start_fail' => __('Bildirim servisi başlatılamadı. Sayfayı yenileyip tekrar deneyin.'),
        'requesting' => __('Bildirim izni isteniyor…'),
        'denied_again' => __('Bildirim izni verilmedi. Tarayıcı ayarlarından bu siteye izin verip tekrar deneyin.'),
        'connected_again' => __('Bu cihaz bağlandı. Yeni duyurular ve hatırlatmalar anında bildirim olarak düşecek.'),
        'connect_fail' => __('Cihaz bağlanamadı. Lütfen tekrar deneyin.'),
        'closing' => __('Bu cihazdaki bildirimler kapatılıyor…'),
        'closed' => __('Bu cihazda bildirimler kapatıldı. İstediğiniz zaman yeniden açabilirsiniz.'),
        'closed_plain' => __('Bu cihazda bildirimler kapatıldı.'),
    ]) ?>;

    var key = box.getAttribute('data-key') || '';
    var swUrl = box.getAttribute('data-sw') || '/sw.js';
    var subscribeUrl = box.getAttribute('data-subscribe');
    var unsubscribeUrl = box.getAttribute('data-unsubscribe');
    var csrf = box.getAttribute('data-csrf') || '';
    var badge = box.querySelector('[data-push-badge]');
    var status = box.querySelector('[data-push-status]');
    var devices = box.querySelector('[data-push-devices]');
    var enableBtn = box.querySelector('[data-push-enable]');
    var disableBtn = box.querySelector('[data-push-disable]');
    var registration = null;
    var busy = false;

    function paint(state, message) {
        if (badge) {
            badge.textContent = state === 'on' ? T.on : (state === 'blocked' ? T.blocked : (state === 'error' ? T.error : T.off));
            badge.className = 'badge ' + (state === 'on' ? 'badge--ok' : (state === 'error' ? 'badge--warn' : 'badge--info'));
        }
        if (status && message) { status.textContent = message; }
        if (enableBtn) { enableBtn.hidden = state === 'on' || state === 'error' || busy; }
        if (disableBtn) { disableBtn.hidden = state !== 'on' || busy; }
    }

    function paintDevices(count) {
        if (devices) { devices.textContent = T.devices.replace(':count', Number(count || 0)); }
    }

    function urlBase64ToBytes(value) {
        var padding = '='.repeat((4 - (value.length % 4)) % 4);
        var base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
        var raw = window.atob(base64);
        var bytes = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) { bytes[i] = raw.charCodeAt(i); }
        return bytes;
    }

    function post(url, data) {
        var body = new FormData();
        body.set('_token', csrf);
        Object.keys(data).forEach(function (name) { body.set(name, data[name] == null ? '' : data[name]); });
        return fetch(url, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (json) {
                if (!response.ok || !json.ok) {
                    throw new Error(json.error || T.generic);
                }
                return json;
            });
        });
    }

    function save(subscription) {
        var json = subscription.toJSON();
        return post(subscribeUrl, {
            endpoint: subscription.endpoint,
            p256dh: json.keys ? json.keys.p256dh : '',
            auth: json.keys ? json.keys.auth : '',
            user_agent: navigator.userAgent
        }).then(function (json) {
            paintDevices(json.devices);
            return json;
        });
    }

    function current() {
        return registration ? registration.pushManager.getSubscription() : Promise.resolve(null);
    }

    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
        paint('error', T.unsupported);
        return;
    }

    navigator.serviceWorker.register(swUrl, { scope: swUrl.replace(/sw\.js.*$/, '') })
        .then(function (reg) { registration = reg; return reg.ready; })
        .then(current)
        .then(function (subscription) {
            if (!subscription) {
                if (Notification.permission === 'denied') {
                    paint('blocked', T.denied);
                } else {
                    paint('off');
                }
                return;
            }
            paint('on', T.connected);
            // Sunucudaki kayıt silinmiş olabilir: sessizce yeniden eşitle.
            save(subscription).catch(function () {});
        })
        .catch(function () {
            paint('error', T.start_fail);
        });

    if (enableBtn) {
        enableBtn.addEventListener('click', function () {
            if (busy) { return; }
            busy = true;
            paint('off', T.requesting);

            Notification.requestPermission().then(function (permission) {
                if (permission !== 'granted') {
                    paint('blocked', T.denied_again);
                    return null;
                }
                return registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToBytes(key)
                }).then(save).then(function () {
                    paint('on', T.connected_again);
                });
            }).catch(function (error) {
                paint('error', error && error.message ? error.message : T.connect_fail);
            }).then(function () {
                busy = false;
                var on = badge && badge.textContent === T.on;
                if (enableBtn) { enableBtn.hidden = !!on; }
                if (disableBtn) { disableBtn.hidden = !on; }
            });
        });
    }

    if (disableBtn) {
        disableBtn.addEventListener('click', function () {
            if (busy) { return; }
            busy = true;
            paint('on', T.closing);
            current().then(function (subscription) {
                if (!subscription) { return null; }
                var endpoint = subscription.endpoint;
                return subscription.unsubscribe().then(function () {
                    return post(unsubscribeUrl, { endpoint: endpoint });
                });
            }).then(function (json) {
                if (json) { paintDevices(json.devices); }
                paint('off', T.closed);
            }).catch(function () {
                paint('off', T.closed_plain);
            }).then(function () {
                busy = false;
                paint('off');
            });
        });
    }
})();
</script>
