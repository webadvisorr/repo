<div class="row row--between mb-3">
    <div>
        <h1 style="font-size:1.6rem;margin:0 0 4px"><?= e(t('admin.system')) ?></h1>
        <p class="muted mb-0"><?= e('Veritabanı güncellemeleri, cron durumu ve sunucu bilgisi.') ?></p>
    </div>
</div>

<div class="grid grid-4 mb-3">
    <div class="card stat"><div class="stat__value" style="font-size:1.15rem"><?= e($phpVersion) ?></div><div class="stat__label">PHP <?= e('sürümü') ?></div></div>
    <div class="card stat"><div class="stat__value" style="font-size:1.15rem"><?= e(strtok($mysqlVersion, '-') ?: $mysqlVersion) ?></div><div class="stat__label">MySQL / MariaDB</div></div>
    <div class="card stat"><div class="stat__value" style="font-size:1.15rem"><?= e(format_bytes((int) $diskFree)) ?></div><div class="stat__label"><?= e('Boş disk alanı') ?></div></div>
    <div class="card stat"><div class="stat__value" style="font-size:1.15rem"><?= e(format_bytes($storageSize + $logSize)) ?></div><div class="stat__label"><?= e('storage kullanımı') ?></div></div>
</div>

<div class="grid grid-2 mb-3">
    <div class="card">
        <h2 style="font-size:1.05rem">🧩 <?= e('Veritabanı güncellemeleri (migration)') ?></h2>
        <?php if ($pending === []): ?>
            <div class="alert alert--success"><span>✅</span><div><?= e('Veritabanı güncel. Uygulanacak güncelleme yok.') ?></div></div>
        <?php else: ?>
            <div class="alert alert--info">
                <span>ℹ️</span>
                <div>
                    <strong><?= count($pending) ?> <?= e('güncelleme bekliyor') ?></strong>
                    <div class="mono" style="font-size:12px;margin-top:4px"><?= e(implode(', ', $pending)) ?></div>
                </div>
            </div>
        <?php endif; ?>
        <p class="muted" style="font-size:13.5px"><?= e('Yeni sürüm yüklediğinizde buradan tek tıkla veritabanını güncelleyin (SSH gerekmez).') ?></p>
        <form method="post" action="<?= e(url('/yonetim/sistem/migrate')) ?>" data-confirm="<?= e('Veritabanı güncellenecek. Yedek aldınız mı?') ?>">
            <?= csrf_field() ?>
            <button class="btn btn-primary" type="submit"><?= e('Güncellemeleri uygula') ?></button>
        </form>

        <h3 class="mt-3" style="font-size:1rem"><?= e('Uygulanmış güncellemeler') ?></h3>
        <div class="mono muted" style="font-size:12.5px;line-height:1.9">
            <?php foreach (array_reverse($applied) as $file): ?>
                ✅ <?= e($file) ?><br>
            <?php endforeach; ?>
            <?php if ($applied === []): ?><?= e('Kayıt yok.') ?><?php endif; ?>
        </div>
    </div>

    <div class="card">
        <h2 style="font-size:1.05rem">⏰ <?= e('Hatırlatma motoru (cron)') ?></h2>
        <p class="muted" style="font-size:13.5px">
            <?= e('Son cron çalışması:') ?> <strong><?= e($lastCron ? format_date($lastCron, 'd.m.Y H:i') : '—') ?></strong>
        </p>
        <div class="alert alert--info">
            <span>ℹ️</span>
            <div style="font-size:13.5px">
                <?= e('cPanel → Cron Jobs bölümüne günlük olarak şu komutu ekleyin:') ?>
                <div class="mono" style="font-size:12px;margin-top:6px;word-break:break-all">
                    /usr/local/bin/php <?= e(BASE_PATH) ?>/cron/reminders.php
                </div>
                <div style="margin-top:8px">
                    <?= e('Web cron alternatifi:') ?>
                    <div class="mono" style="font-size:12px;word-break:break-all">
                        <?= e(url('/cron/reminders.php?token=' . $cronToken)) ?>
                    </div>
                </div>
            </div>
        </div>
        <form method="post" action="<?= e(url('/yonetim/sistem/cron')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-outline" type="submit"><?= e('Şimdi çalıştır (manuel)') ?></button>
        </form>

        <h3 class="mt-3" style="font-size:1rem"><?= e('Tablo istatistikleri') ?></h3>
        <div class="table-wrap" style="max-height:280px;overflow-y:auto">
            <table class="data">
                <thead><tr><th><?= e('Tablo') ?></th><th><?= e('Satır (yaklaşık)') ?></th><th><?= e('Boyut') ?></th></tr></thead>
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
