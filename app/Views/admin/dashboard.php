<?php use App\Core\Auth; ?>
<div class="row row--between mb-3">
    <div>
        <h1 style="font-size:1.6rem;margin-bottom:4px"><?= e(t('admin.welcome', ['name' => (string) (Auth::user()['full_name'] ?? '')])) ?></h1>
        <p class="muted mb-0"><?= e(__('Sistemin genel durumu aşağıda.')) ?></p>
    </div>
    <div class="row" style="gap:8px;flex-wrap:wrap">
        <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/mesajlar?durum=okunmamis')) ?>">📬 <?= e(__('Okunmamış mesaj')) ?> (<?= (int) $stats['messages'] ?>)</a>
        <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/evraklar?vade=30')) ?>">⏰ <?= e(__('30 günde bitecek')) ?> (<?= (int) $stats['expiring30'] ?>)</a>
    </div>
</div>

<?php if (empty($smtpConfigured)): ?>
    <div class="alert alert--error">
        <span>⚠️</span>
        <div>
            <strong><?= e(__('E-posta gönderimi yapılandırılmadı.')) ?></strong><br>
            <?= e(__('Hatırlatma e-postaları gönderilemez. Kurumsal mail sunucu bilgilerinizi girin.')) ?>
            <div class="mt-2"><a class="btn btn-primary btn-sm" href="<?= e(url('/yonetim/ayarlar/mail')) ?>"><?= e(__('SMTP ayarlarını aç')) ?></a></div>
        </div>
    </div>
<?php endif; ?>

<style>
    .dash-widgets { position: relative; }
    .dash-widgets > summary { list-style: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
    .dash-widgets > summary::-webkit-details-marker { display: none; }
    .dash-widgets[open] > summary { border-color: var(--c-accent); }
    .dash-widgets__panel { position: absolute; inset-inline-end: 0; top: calc(100% + 8px); z-index: 70; width: min(580px, calc(100vw - 32px)); max-height: min(72vh, 560px); overflow: auto; box-shadow: var(--shadow-lg); }
    .dash-widgets__list { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 6px 12px; margin-top: 10px; }
    .dash-widgets__list label { display: flex; align-items: center; gap: 8px; font-size: 13.5px; padding: 6px 8px; border-radius: 8px; cursor: pointer; }
    .dash-widgets__list label:hover { background: color-mix(in srgb, var(--c-primary) 6%, transparent); }
    .dash-widgets__list input { width: auto; margin: 0; }
    .dash-widgets__list small { color: var(--c-muted); font-size: 11.5px; }
    @media (max-width: 720px) {
        .dash-widgets__panel { position: fixed; inset-inline: 12px; top: auto; bottom: 12px; width: auto; max-height: 70vh; }
    }
</style>

<div class="row row--between mb-2" style="align-items:center;gap:10px;flex-wrap:wrap">
    <p class="muted mb-0" style="font-size:13px"><?= e(__('Panelde görmek istediğiniz kutucukları seçin; seçiminiz bu hesaba kaydedilir.')) ?></p>
    <details class="dash-widgets">
        <summary class="btn btn-outline btn-sm" aria-label="<?= e(__('Kutucuk ayarları')) ?>">⚙️ <?= e(__('Kutucuk ayarları')) ?></summary>
        <form method="post" action="<?= e(url('/yonetim/panel/kutucuklar')) ?>" class="card dash-widgets__panel">
            <?= csrf_field() ?>
            <strong style="font-family:var(--font-head)"><?= e(__('Panelde görünecek kutucuklar')) ?></strong>
            <p class="hint mb-0"><?= e(__('İşaretlediğiniz kutucuklar ana sayfada görünür. İstediğiniz zaman değiştirebilirsiniz.')) ?></p>
            <div class="dash-widgets__list">
                <?php foreach ($catalogue as $key => $widget): ?>
                    <label>
                        <input type="checkbox" name="widgets[]" value="<?= e((string) $key) ?>"
                            <?= array_key_exists((string) $key, $widgets) ? 'checked' : '' ?>>
                        <span aria-hidden="true"><?= e((string) $widget['icon']) ?></span>
                        <span><?= e((string) $widget['label']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="row mt-2" style="gap:10px;align-items:center;flex-wrap:wrap">
                <button class="btn btn-primary btn-sm" type="submit"><?= e(__('Seçimi kaydet')) ?></button>
                <span class="muted" style="font-size:12.5px"><?= e(count($widgets) . ' kutucuk seçili') ?></span>
            </div>
        </form>
    </details>
</div>

<div class="grid grid-4 mb-3">
    <?php if ($widgets === []): ?>
        <div class="card" style="grid-column:1/-1">
            <p class="muted mb-0"><?= e(__('Hiç kutucuk seçilmedi. Sağ üstteki “Kutucuk ayarları” ile istediklerinizi işaretleyin.')) ?></p>
        </div>
    <?php endif; ?>
    <?php foreach ($widgets as $key => $widget): ?>
        <a class="card stat stat--tone-<?= (int) $widget['tone'] ?> card--hover"
           style="text-decoration:none;color:inherit" href="<?= e(url((string) $widget['href'])) ?>">
            <div class="stat__value">
                <?php if ($key === 'online'): ?>
                    <span class="live-dot" aria-hidden="true"></span><span data-live-count><?= e((string) $widget['value']) ?></span>
                <?php elseif ($key === 'online_today'): ?>
                    <span data-live-today><?= e((string) $widget['value']) ?></span>
                <?php else: ?>
                    <?= e((string) $widget['value']) ?>
                <?php endif; ?>
            </div>
            <div class="stat__label"><span aria-hidden="true"><?= e((string) $widget['icon']) ?></span> <?= e((string) $widget['label']) ?></div>
        </a>
    <?php endforeach; ?>
</div>

<div class="grid grid-2 mb-3" id="canli-ziyaretciler">
    <div class="card">
        <div class="row row--between mb-2" style="align-items:flex-start;gap:10px;flex-wrap:wrap">
            <div>
                <h2 style="font-size:1.1rem;margin:0 0 2px"><span class="live-dot" aria-hidden="true"></span> <?= e(__('Canlı ziyaretçiler')) ?></h2>
                <p class="muted mb-0" style="font-size:13px"><?= e(__('Son 5 dakikada siteyi görüntüleyenler')) ?></p>
            </div>
            <div class="row" style="gap:6px">
                <span class="badge badge--ok"><span data-live-badge><?= (int) $stats['online'] ?></span> <?= e(__('çevrimiçi')) ?></span>
                <span class="badge badge--info"><span data-live-today><?= (int) $stats['onlineToday'] ?></span> <?= e(__('bugün tekil')) ?></span>
            </div>
        </div>
        <?php if (empty($visitors)): ?>
            <p class="muted mb-0"><?= e(__('Şu anda siteyi görüntüleyen kimse yok.')) ?></p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th><?= e(__('Ziyaretçi')) ?></th><th><?= e(__('Sayfa')) ?></th><th><?= e(__('Son görülme')) ?></th></tr></thead>
                    <tbody data-live-visitors>
                    <?php foreach ($visitors as $visitor): ?>
                        <tr>
                            <td>
                                <?php if (!empty($visitor['user_id'])): ?>
                                    <a href="<?= e(url('/yonetim/uyeler/' . (int) $visitor['user_id'])) ?>"><?= e((string) ($visitor['full_name'] ?: $visitor['email'])) ?></a>
                                <?php else: ?>
                                    <span class="muted"><?= e(__('Ziyaretçi')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="mono" style="font-size:12.5px"><?= e((string) ($visitor['path'] ?: '/')) ?></td>
                            <td class="muted" style="font-size:12.5px"><?= e(time_ago((string) $visitor['last_seen'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="row row--between mb-2" style="align-items:flex-start;gap:10px;flex-wrap:wrap">
            <div>
                <h2 style="font-size:1.1rem;margin:0 0 2px">🎧 <?= e(__('Destek talepleri')) ?></h2>
                <p class="muted mb-0" style="font-size:13px"><?= e(__('Müşteri panelinden açılan talepler')) ?></p>
            </div>
            <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/destek')) ?>"><?= e(__('Tümünü yönet')) ?> →</a>
        </div>
        <div class="grid grid-3" style="gap:8px">
            <a class="card stat stat--tone-4 card--hover" style="text-decoration:none;color:inherit;padding:12px" href="<?= e(url('/yonetim/destek?durum=open')) ?>"><div class="stat__value" style="font-size:1.35rem"><?= (int) ($support['open'] ?? 0) ?></div><div class="stat__label"><?= e(__('Açık')) ?></div></a>
            <a class="card stat stat--tone-3 card--hover" style="text-decoration:none;color:inherit;padding:12px" href="<?= e(url('/yonetim/destek?durum=answered')) ?>"><div class="stat__value" style="font-size:1.35rem"><?= (int) ($support['answered'] ?? 0) ?></div><div class="stat__label"><?= e(__('Yanıtlandı')) ?></div></a>
            <a class="card stat stat--tone-1 card--hover" style="text-decoration:none;color:inherit;padding:12px" href="<?= e(url('/yonetim/destek')) ?>"><div class="stat__value" style="font-size:1.35rem"><?= (int) ($support['total'] ?? 0) ?></div><div class="stat__label"><?= e(__('Toplam')) ?></div></a>
        </div>
        <p class="muted mb-0 mt-2" style="font-size:13px">
            <?php if ((int) ($support['unread'] ?? 0) > 0): ?>
                <span class="badge badge--danger"><?= (int) $support['unread'] ?> <?= e(__('yeni müşteri mesajı')) ?></span>
            <?php else: ?>
                <?= e(__('Bekleyen yeni müşteri mesajı yok.')) ?>
            <?php endif; ?>
        </p>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2 style="font-size:1.1rem"><?= e(__('Yaklaşan evrak bitişleri')) ?></h2>
        <?php if (empty($upcoming)): ?>
            <p class="muted mb-0"><?= e(__('Henüz araç/evrak kaydı yok.')) ?> <a href="<?= e(url('/yonetim/araclar')) ?>"><?= e(__('Araçları gör')) ?></a></p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th><?= e(__('Plaka')) ?></th><th><?= e(__('Evrak')) ?></th><th><?= e(__('Bitiş')) ?></th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($upcoming as $row): ?>
                        <?php $days = days_left((string) $row['end_date']); ?>
                        <tr>
                            <td class="mono"><?= !empty($row['vehicle_id']) ? '<a href="' . e(url('/yonetim/araclar/' . (int) $row['vehicle_id'])) . '">' . e((string) $row['plate']) . '</a>' : e((string) $row['plate']) ?></td>
                            <td><?= e(label((string) $row['labels_json'])) ?></td>
                            <td><?= e(format_date((string) $row['end_date'])) ?></td>
                            <td>
                                <?php $cls = $days === null || $days < 0 ? 'badge--danger' : ($days <= 7 ? 'badge--warn' : 'badge--ok'); ?>
                                <span class="badge <?= $cls ?>"><?= $days === null ? '-' : ($days < 0 ? e(abs($days) . ' gün geçti') : e($days . ' gün')) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2 style="font-size:1.1rem"><?= e(__('Son işlemler (denetim kaydı)')) ?></h2>
        <?php if (empty($audit)): ?>
            <p class="muted mb-0"><?= e(__('Kayıt yok.')) ?></p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th><?= e(__('Kullanıcı')) ?></th><th><?= e(__('İşlem')) ?></th><th><?= e(__('Zaman')) ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($audit as $row): ?>
                        <tr>
                            <td><?= e((string) ($row['full_name'] ?? $row['email'] ?? '#' . $row['user_id'])) ?></td>
                            <td class="mono" style="font-size:12.5px"><?= e((string) $row['action']) ?></td>
                            <td class="muted"><?= e(format_date((string) $row['created_at'], 'd.m.Y H:i')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script nonce="<?= e(App\Core\Security::nonce()) ?>">
(function () {
    var countEl = document.querySelector('[data-live-count]');
    var badgeEl = document.querySelector('[data-live-badge]');
    var todayEl = document.querySelector('[data-live-today]');
    var rowsEl = document.querySelector('[data-live-visitors]');
    var endpoint = <?= json_encode(url('/yonetim/canli'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var memberBase = <?= json_encode(url('/yonetim/uyeler/'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    if (!rowsEl) { return; }

    function render(visitors) {
        rowsEl.textContent = '';
        if (!visitors || visitors.length === 0) {
            var tr = document.createElement('tr');
            var td = document.createElement('td');
            td.colSpan = 3;
            td.className = 'muted';
            td.textContent = 'Şu anda siteyi görüntüleyen kimse yok.';
            tr.appendChild(td);
            rowsEl.appendChild(tr);
            return;
        }
        visitors.forEach(function (v) {
            var tr = document.createElement('tr');
            var nameTd = document.createElement('td');
            if (v.user_id > 0) {
                var a = document.createElement('a');
                a.href = memberBase + v.user_id;
                a.textContent = v.name;
                nameTd.appendChild(a);
            } else {
                var muted = document.createElement('span');
                muted.className = 'muted';
                muted.textContent = v.name;
                nameTd.appendChild(muted);
            }
            var pathTd = document.createElement('td');
            pathTd.className = 'mono';
            pathTd.style.fontSize = '12.5px';
            pathTd.textContent = v.path;
            var timeTd = document.createElement('td');
            timeTd.className = 'muted';
            timeTd.style.fontSize = '12.5px';
            timeTd.textContent = v.time;
            tr.appendChild(nameTd);
            tr.appendChild(pathTd);
            tr.appendChild(timeTd);
            rowsEl.appendChild(tr);
        });
    }

    function refresh() {
        fetch(endpoint, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                if (!json || !json.ok) { return; }
                if (countEl) { countEl.textContent = String(json.online); }
                if (badgeEl) { badgeEl.textContent = String(json.online); }
                if (todayEl) { todayEl.textContent = String(json.today); }
                render(json.visitors);
            })
            .catch(function () {});
    }

    setInterval(refresh, 25000);
})();
</script>
