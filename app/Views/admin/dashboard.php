<?php use App\Core\Auth; ?>
<div class="row row--between mb-3">
    <div>
        <h1 style="font-size:1.6rem;margin-bottom:4px"><?= e(t('admin.welcome', ['name' => (string) (Auth::user()['full_name'] ?? '')])) ?></h1>
        <p class="muted mb-0"><?= e('Sistemin genel durumu aşağıda.') ?></p>
    </div>
</div>

<?php if (empty($smtpConfigured)): ?>
    <div class="alert alert--error">
        <span>⚠️</span>
        <div>
            <strong><?= e('E-posta gönderimi yapılandırılmadı.') ?></strong><br>
            <?= e('Hatırlatma e-postaları gönderilemez. Kurumsal mail sunucu bilgilerinizi girin.') ?>
            <div class="mt-2"><a class="btn btn-primary btn-sm" href="<?= e(url('/yonetim/ayarlar/mail')) ?>"><?= e('SMTP ayarlarını aç') ?></a></div>
        </div>
    </div>
<?php endif; ?>

<div class="grid grid-4 mb-3">
    <div class="card stat"><div class="stat__value"><?= (int) $stats['customers'] ?></div><div class="stat__label"><?= e('Müşteri') ?></div></div>
    <div class="card stat"><div class="stat__value"><?= (int) $stats['vehicles'] ?></div><div class="stat__label"><?= e('Araç') ?></div></div>
    <div class="card stat"><div class="stat__value"><?= (int) $stats['documents'] ?></div><div class="stat__label"><?= e('Aktif evrak') ?></div></div>
    <div class="card stat"><div class="stat__value"><?= (int) $stats['expiring30'] ?></div><div class="stat__label"><?= e('30 günde bitecek') ?></div></div>
    <div class="card stat"><div class="stat__value" style="color:var(--c-danger)"><?= (int) $stats['expired'] ?></div><div class="stat__label"><?= e('Süresi geçmiş') ?></div></div>
    <div class="card stat"><div class="stat__value"><?= (int) $stats['mailsToday'] ?></div><div class="stat__label"><?= e('Bugün gönderilen mail') ?></div></div>
    <div class="card stat"><div class="stat__value" style="color:var(--c-warning)"><?= (int) $stats['mailFailed'] ?></div><div class="stat__label"><?= e('Başarısız bildirim') ?></div></div>
    <div class="card stat"><div class="stat__value"><?= (int) $stats['messages'] ?></div><div class="stat__label"><?= e('Okunmamış mesaj') ?></div></div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2 style="font-size:1.1rem"><?= e('Yaklaşan evrak bitişleri') ?></h2>
        <?php if (empty($upcoming)): ?>
            <p class="muted mb-0"><?= e('Henüz araç/evrak kaydı yok. Araç ve evrak modülü sonraki adımda eklenecek.') ?></p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th><?= e('Plaka') ?></th><th><?= e('Evrak') ?></th><th><?= e('Bitiş') ?></th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($upcoming as $row): ?>
                        <?php $days = days_left((string) $row['end_date']); ?>
                        <tr>
                            <td class="mono"><?= e((string) $row['plate']) ?></td>
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
        <h2 style="font-size:1.1rem"><?= e('Son işlemler (denetim kaydı)') ?></h2>
        <?php if (empty($audit)): ?>
            <p class="muted mb-0"><?= e('Kayıt yok.') ?></p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th><?= e('Kullanıcı') ?></th><th><?= e('İşlem') ?></th><th><?= e('Zaman') ?></th></tr></thead>
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
