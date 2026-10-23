<div class="row row--between mb-3">
    <div>
        <h1 style="font-size:1.6rem;margin:0 0 4px"><?= e(t('admin.system')) ?></h1>
        <p class="muted mb-0"><?= e(__('Veritabanı güncellemeleri, cron durumu ve sunucu bilgisi.')) ?></p>
    </div>
</div>

<div class="grid grid-4 mb-3">
    <div class="card stat"><div class="stat__value" style="font-size:1.15rem"><?= e($phpVersion) ?></div><div class="stat__label">PHP <?= e(__('sürümü')) ?></div></div>
    <div class="card stat"><div class="stat__value" style="font-size:1.15rem"><?= e(strtok($mysqlVersion, '-') ?: $mysqlVersion) ?></div><div class="stat__label">MySQL / MariaDB</div></div>
    <div class="card stat"><div class="stat__value" style="font-size:1.15rem"><?= e(format_bytes((int) $diskFree)) ?></div><div class="stat__label"><?= e(__('Boş disk alanı')) ?></div></div>
    <div class="card stat"><div class="stat__value" style="font-size:1.15rem"><?= e(format_bytes($storageSize + $logSize)) ?></div><div class="stat__label"><?= e(__('storage kullanımı')) ?></div></div>
</div>

<div class="grid grid-2 mb-3">
    <div class="card">
        <h2 style="font-size:1.05rem">🧩 <?= e(__('Veritabanı güncellemeleri (migration)')) ?></h2>
        <?php if ($pending === []): ?>
            <div class="alert alert--success"><span>✅</span><div><?= e(__('Veritabanı güncel. Uygulanacak güncelleme yok.')) ?></div></div>
        <?php else: ?>
            <div class="alert alert--info">
                <span>ℹ️</span>
                <div>
                    <strong><?= count($pending) ?> <?= e(__('güncelleme bekliyor')) ?></strong>
                    <div class="mono" style="font-size:12px;margin-top:4px"><?= e(implode(', ', $pending)) ?></div>
                </div>
            </div>
        <?php endif; ?>
        <p class="muted" style="font-size:13.5px"><?= e(__('Yeni sürüm yüklediğinizde buradan tek tıkla veritabanını güncelleyin (SSH gerekmez).')) ?></p>
        <form method="post" action="<?= e(url('/yonetim/sistem/migrate')) ?>" data-confirm="<?= e(__('Veritabanı güncellenecek. Yedek aldınız mı?')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-primary" type="submit"><?= e(__('Güncellemeleri uygula')) ?></button>
        </form>

        <h3 class="mt-3" style="font-size:1rem"><?= e(__('Uygulanmış güncellemeler')) ?></h3>
        <div class="mono muted" style="font-size:12.5px;line-height:1.9">
            <?php foreach (array_reverse($applied) as $file): ?>
                ✅ <?= e($file) ?><br>
            <?php endforeach; ?>
            <?php if ($applied === []): ?><?= e(__('Kayıt yok.')) ?><?php endif; ?>
        </div>
    </div>

    <div class="card">
        <h2 style="font-size:1.05rem">⏰ <?= e(__('Hatırlatma motoru (cron)')) ?></h2>
        <p class="muted" style="font-size:13.5px">
            <?= e(__('Son cron çalışması:')) ?> <strong><?= e($lastCron ? format_date($lastCron, 'd.m.Y H:i') : '—') ?></strong>
        </p>
        <div class="alert alert--info">
            <span>ℹ️</span>
            <div style="font-size:13.5px">
                <?= e(__('cPanel → Cron Jobs bölümüne günlük olarak şu komutu ekleyin:')) ?>
                <div class="mono" style="font-size:12px;margin-top:6px;word-break:break-all">
                    /usr/local/bin/php <?= e(BASE_PATH) ?>/cron/reminders.php
                </div>
                <div style="margin-top:8px">
                    <?= e(__('Web cron alternatifi (günde bir kez çağırın):')) ?>
                    <div class="mono" style="font-size:12px;word-break:break-all">
                        <?= e(url('/cron-hatirlatma?token=' . $cronToken)) ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-2" style="gap:10px;margin:12px 0">
            <div class="card stat"><div class="stat__value" style="font-size:1.1rem"><?= (int) ($reminders['sent'] ?? 0) ?></div><div class="stat__label"><?= e(__('Gönderilen bildirim')) ?></div></div>
            <div class="card stat"><div class="stat__value" style="font-size:1.1rem"><?= (int) $pendingQueue ?></div><div class="stat__label"><?= e(__('Kuyrukta bekleyen')) ?></div></div>
            <div class="card stat"><div class="stat__value" style="font-size:1.1rem"><?= (int) ($reminders['failed'] ?? 0) ?></div><div class="stat__label"><?= e(__('Hatalı')) ?></div></div>
            <div class="card stat"><div class="stat__value" style="font-size:1.1rem"><?= (int) $sentToday ?> / <?= (int) $dailyLimit ?></div><div class="stat__label"><?= e(__('Bugün / günlük limit')) ?></div></div>
        </div>
        <p style="font-size:13px">
            <?php if ($reminderReady): ?>
                <span class="badge badge--ok"><?= e(__('Hatırlatma motoru açık')) ?></span>
            <?php else: ?>
                <span class="badge badge--warn"><?= e(__('Hatırlatma motoru kapalı')) ?></span>
                <a href="<?= e(url('/yonetim/ayarlar/reminders')) ?>"><?= e(__('Ayarlar → Hatırlatma')) ?></a>
            <?php endif; ?>
            <span class="muted">· <?= e(__('Mail şablonu:')) ?> <?= (int) $templateCount ?>/<?= count(\App\Services\MailTemplateService::KEYS) * count(\App\Services\MailTemplateService::LOCALES) ?></span>
            <a href="<?= e(url('/yonetim/bildirimler')) ?>"><?= e(__('E-posta günlüğünü aç')) ?></a>
            <a href="<?= e(url('/yonetim/sablonlar')) ?>"><?= e(__('Mail şablonları')) ?></a>
        </p>
        <form method="post" action="<?= e(url('/yonetim/sistem/cron')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-outline" type="submit"><?= e(__('Şimdi çalıştır (manuel)')) ?></button>
        </form>

        <h3 class="mt-3" style="font-size:1rem"><?= e(__('Tablo istatistikleri')) ?></h3>
        <div class="table-wrap" style="max-height:280px;overflow-y:auto">
            <table class="data">
                <thead><tr><th><?= e(__('Tablo')) ?></th><th><?= e(__('Satır (yaklaşık)')) ?></th><th><?= e(__('Boyut')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($tables as $table): ?>
                    <tr>
                        <td class="mono" style="font-size:12.5px"><?= e((string) $table['name']) ?></td>
                        <td><?= (int) ($table['rows_count'] ?? 0) ?></td>
                        <td class="muted"><?= e(format_bytes((int) (($table['size_kb'] ?? 0) * 1024))) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            </table>
        </div>
    </div>
</div>
