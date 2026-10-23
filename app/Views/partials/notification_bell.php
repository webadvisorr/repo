<?php
/**
 * Site içi bildirim zili (sekmeli bildirim merkezi).
 * Yalnızca giriş yapmış kullanıcılar için site başlığında gösterilir.
 *
 * Sekmeler yalnızca bildirim bulunan türler için çizilir (hatırlatma, duyuru,
 * destek, mesaj, sistem). Bir sekme açıldığında o türdeki bildirimler okundu
 * sayılır ve sekmenin sayaçı kaybolur.
 */

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Security;
use App\Services\InboxService;

$inboxUser = Auth::user();
if ($inboxUser === null || !InboxService::available()) {
    return;
}
$inboxUserId = (int) $inboxUser['id'];
$inboxUnread = InboxService::unreadCount($inboxUserId);
$inboxCounts = InboxService::categoryCounts($inboxUserId);
$inboxTabs = array_filter($inboxCounts, static fn (array $row): bool => $row['total'] > 0);
$inboxItems = InboxService::latest($inboxUserId, 40);
$inboxActive = (string) (array_key_first($inboxTabs) ?? '');
$itemHref = static function (array $row): string {
    $link = (string) ($row['link_url'] ?? '');
    if (preg_match('~^https?://~i', $link) === 1) {
        return $link;
    }
    return url($link !== '' ? $link : '/panel/bildirimler');
};
?>
<div class="inbox" data-inbox>
    <button type="button" class="inbox__bell" data-inbox-toggle
            aria-controls="inbox-panel" aria-expanded="false"
            aria-label="<?= e(t('inbox.title') . ($inboxUnread > 0 ? ' (' . t('inbox.unread_count', ['count' => $inboxUnread]) . ')' : '')) ?>">
        <span aria-hidden="true">🔔</span>
        <span class="inbox__badge" data-inbox-badge <?= $inboxUnread > 0 ? '' : 'hidden' ?>><?= $inboxUnread > 99 ? '99+' : $inboxUnread ?></span>
    </button>

    <section class="inbox-panel" id="inbox-panel" data-inbox-panel hidden aria-label="<?= e(t('inbox.title')) ?>">
        <header class="inbox-panel__head">
            <div>
                <strong><?= e(t('inbox.title')) ?></strong>
                <span class="inbox-panel__count" data-inbox-count>
                    <?= $inboxUnread > 0 ? e(t('inbox.unread_count', ['count' => $inboxUnread])) : e(t('inbox.all_read')) ?>
                </span>
            </div>
            <div class="inbox-panel__actions">
                <button type="button" class="inbox-panel__link" data-inbox-read-all <?= $inboxUnread > 0 ? '' : 'disabled' ?>>
                    <?= e(t('inbox.read_all')) ?>
                </button>
                <button type="button" class="inbox-panel__close" data-inbox-close aria-label="<?= e(t('common.close')) ?>">×</button>
            </div>
        </header>

        <?php if ($inboxTabs !== []): ?>
            <nav class="inbox-panel__tabs" role="tablist" aria-label="<?= e(t('inbox.title')) ?>">
                <?php foreach ($inboxTabs as $kind => $row): ?>
                    <button type="button" role="tab"
                            class="inbox-tab<?= $kind === $inboxActive ? ' is-active' : '' ?>"
                            data-inbox-tab="<?= e($kind) ?>"
                            aria-selected="<?= $kind === $inboxActive ? 'true' : 'false' ?>">
                        <span aria-hidden="true"><?= InboxService::kindIcon($kind) ?></span>
                        <span class="inbox-tab__label"><?= e(InboxService::kindLabel($kind)) ?></span>
                        <span class="inbox-tab__count" data-inbox-tab-count="<?= e($kind) ?>" <?= $row['unread'] > 0 ? '' : 'hidden' ?>><?= (int) $row['unread'] ?></span>
                    </button>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

        <div class="inbox-panel__list" data-inbox-list aria-live="polite">
            <?php if ($inboxItems === []): ?>
                <div class="inbox-empty" data-inbox-empty>
                    <span aria-hidden="true">📭</span>
                    <p><?= e(t('inbox.empty_title')) ?></p>
                    <small><?= e(t('inbox.empty_text')) ?></small>
                </div>
            <?php else: ?>
                <?php foreach ($inboxItems as $item): ?>
                    <?php $unread = ($item['read_at'] ?? null) === null; ?>
                    <a class="inbox-item<?= $unread ? ' is-unread' : '' ?>"
                       href="<?= e($itemHref($item)) ?>"
                       data-inbox-item data-kind="<?= e((string) $item['kind']) ?>"
                       data-id="<?= (int) $item['id'] ?>"
                       <?= (string) $item['kind'] === $inboxActive ? '' : 'hidden' ?>>
                        <?php if (!empty($item['image_path'])): ?>
                            <img class="inbox-item__thumb" src="<?= e((string) InboxService::imageUrl((string) $item['image_path'])) ?>" alt="" loading="lazy">
                        <?php else: ?>
                            <span class="inbox-item__icon" aria-hidden="true"><?= InboxService::kindIcon((string) $item['kind']) ?></span>
                        <?php endif; ?>
                        <span class="inbox-item__body">
                            <span class="inbox-item__top">
                                <strong><?= e((string) $item['title']) ?></strong>
                                <small><?= e(time_ago((string) $item['created_at'])) ?></small>
                            </span>
                            <span class="inbox-item__text"><?= e(mb_strimwidth((string) ($item['body'] ?? ''), 0, 110, '…')) ?></span>
                            <span class="inbox-item__meta">
                                <span class="inbox-item__kind"><?= e(InboxService::kindLabel((string) $item['kind'])) ?></span>
                                <?php if (!empty($item['link_label'])): ?>
                                    <span class="inbox-item__cta"><?= e((string) $item['link_label']) ?> →</span>
                                <?php endif; ?>
                            </span>
                        </span>
                        <?php if ($unread): ?><i class="inbox-item__dot" aria-label="<?= e(t('inbox.new')) ?>"></i><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <footer class="inbox-panel__foot">
            <a href="<?= e(url('/panel/bildirimler')) ?>"><?= e(t('inbox.view_all')) ?></a>
        </footer>
    </section>
</div>
<script nonce="<?= e(Security::nonce()) ?>">
(function () {
    var root = document.querySelector('[data-inbox]');
    if (!root) { return; }
    var toggle = root.querySelector('[data-inbox-toggle]');
    var panel = root.querySelector('[data-inbox-panel]');
    var list = root.querySelector('[data-inbox-list]');
    var badge = root.querySelector('[data-inbox-badge]');
    var countEl = root.querySelector('[data-inbox-count]');
    var readAllBtn = root.querySelector('[data-inbox-read-all]');
    var closeBtn = root.querySelector('[data-inbox-close]');
    var tabs = Array.prototype.slice.call(root.querySelectorAll('[data-inbox-tab]'));
    var counts = <?= json_encode(array_map(static fn (array $row): array => ['total' => $row['total'], 'unread' => $row['unread']], $inboxCounts), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var activeKind = <?= json_encode($inboxActive, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var csrf = <?= json_encode(Csrf::token(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var summaryUrl = <?= json_encode(url('/panel/bildirimler/ozet'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var readUrl = <?= json_encode(url('/panel/bildirimler/oku'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var readAllUrl = <?= json_encode(url('/panel/bildirimler/tumu-oku'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var readKindUrl = <?= json_encode(url('/panel/bildirimler/oku-tur'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var unreadTemplate = <?= json_encode(t('inbox.unread_count', ['count' => '__COUNT__']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var allReadText = <?= json_encode(t('inbox.all_read'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var loading = false;

    function totalUnread() {
        var sum = 0;
        Object.keys(counts).forEach(function (kind) { sum += Number(counts[kind].unread || 0); });
        return sum;
    }

    function paint() {
        var unread = totalUnread();
        if (badge) {
            badge.textContent = unread > 99 ? '99+' : String(unread);
            badge.hidden = unread < 1;
        }
        if (countEl) {
            countEl.textContent = unread > 0 ? unreadTemplate.replace('__COUNT__', String(unread)) : allReadText;
        }
        if (readAllBtn) {
            readAllBtn.disabled = unread < 1;
        }
        tabs.forEach(function (tab) {
            var kind = tab.getAttribute('data-inbox-tab');
            var count = tab.querySelector('[data-inbox-tab-count]');
            var value = Number((counts[kind] || {}).unread || 0);
            if (count) {
                count.textContent = String(value);
                count.hidden = value < 1;
            }
            tab.classList.toggle('is-active', kind === activeKind);
            tab.setAttribute('aria-selected', kind === activeKind ? 'true' : 'false');
        });
    }

    function showTab(kind) {
        activeKind = kind;
        list.querySelectorAll('[data-inbox-item]').forEach(function (item) {
            item.hidden = item.getAttribute('data-kind') !== kind;
        });
        paint();
    }

    function markKind(kind) {
        if (!counts[kind] || Number(counts[kind].unread || 0) < 1) { return Promise.resolve(); }
        var data = new FormData();
        data.set('_token', csrf);
        data.set('kind', kind);
        return fetch(readKindUrl, {
            method: 'POST', body: data, credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) { return res.json(); }).then(function (json) {
            if (json && json.ok) {
                counts = json.counts || counts;
                list.querySelectorAll('[data-kind="' + kind + '"].is-unread').forEach(function (item) {
                    item.classList.remove('is-unread');
                    var dot = item.querySelector('.inbox-item__dot');
                    if (dot) { dot.remove(); }
                });
                paint();
            }
        }).catch(function () {});
    }

    function refresh() {
        if (loading) { return; }
        loading = true;
        fetch(summaryUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                if (!json || !json.ok) { return; }
                counts = json.counts || counts;
                paint();
            })
            .catch(function () {})
            .finally(function () { loading = false; });
    }

    function markRead(id) {
        var data = new FormData();
        data.set('_token', csrf);
        data.set('id', id);
        return fetch(readUrl, {
            method: 'POST', body: data, keepalive: true, credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) { return res.json(); });
    }

    function open() {
        panel.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        root.classList.add('is-open');
        refresh();
        if (activeKind) { markKind(activeKind); }
    }
    function shut() {
        panel.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
        root.classList.remove('is-open');
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var kind = tab.getAttribute('data-inbox-tab');
            showTab(kind);
            markKind(kind);
        });
    });

    toggle.addEventListener('click', function () { panel.hidden ? open() : shut(); });
    if (closeBtn) { closeBtn.addEventListener('click', shut); }
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) { shut(); } });
    document.addEventListener('click', function (e) {
        if (!panel.hidden && !root.contains(e.target)) { shut(); }
    });

    list.addEventListener('click', function (e) {
        var item = e.target.closest('[data-inbox-item]');
        if (!item) { return; }
        if (!item.classList.contains('is-unread')) { return; }
        var id = item.getAttribute('data-id');
        var href = item.getAttribute('href');
        var kind = item.getAttribute('data-kind');
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) { return; }
        e.preventDefault();
        item.classList.remove('is-unread');
        var dot = item.querySelector('.inbox-item__dot');
        if (dot) { dot.remove(); }
        if (counts[kind]) { counts[kind].unread = Math.max(0, Number(counts[kind].unread || 0) - 1); }
        paint();
        var pending = markRead(id);
        var go = function () { if (href && href !== '#') { window.location.href = href; } };
        if (pending && pending.then) { pending.then(go).catch(go); } else { go(); }
    });

    if (readAllBtn) {
        readAllBtn.addEventListener('click', function () {
            var data = new FormData();
            data.set('_token', csrf);
            fetch(readAllUrl, {
                method: 'POST', body: data, credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (res) { return res.json(); })
              .then(function (json) {
                  if (json && json.ok) {
                      Object.keys(counts).forEach(function (kind) { counts[kind].unread = 0; });
                      paint();
                      list.querySelectorAll('.inbox-item.is-unread').forEach(function (item) {
                          item.classList.remove('is-unread');
                          var dot = item.querySelector('.inbox-item__dot');
                          if (dot) { dot.remove(); }
                      });
                  }
              })
              .catch(function () {});
        });
    }

    paint();
})();
</script>
