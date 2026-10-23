<?php
/** Kampanya detayı. $campaign, $audience, $stats, $recipients, $smtpReady, $queue */
use App\Core\Translator;

$campaignId = (int) $campaign['id'];
$status = (string) $campaign['status'];
$badge = [
    'draft'     => ['badge--info', 'Taslak'],
    'scheduled' => ['badge--warn', 'Planlandı'],
    'sending'   => ['badge--info', 'Gönderiliyor'],
    'sent'      => ['badge--ok', 'Gönderildi'],
    'cancelled' => ['badge--danger', 'İptal'],
][$status] ?? ['badge--info', $status];

$audienceText = [];
if ($audience['plan_key'] !== '') {
    $audienceText[] = 'plan: ' . $audience['plan_key'];
}
if ($audience['org_id'] > 0) {
    $audienceText[] = 'kurum #' . $audience['org_id'];
}
if ($audience['locale'] !== '') {
    $audienceText[] = 'dil: ' . (Translator::NAMES[$audience['locale']] ?? $audience['locale']);
}
if ($audience['only_marketing_opt_in']) {
    $audienceText[] = 'yalnız pazarlama izni olanlar';
}
$audienceLabel = $audienceText === [] ? 'Tüm uygun alıcılar' : implode(' · ', $audienceText);

$recipientBadge = [
    'pending'      => 'badge--warn',
    'sent'         => 'badge--ok',
    'failed'       => 'badge--danger',
    'unsubscribed' => 'badge--info',
];
?>
<div class="row row--between mb-3">
    <div>
        <h1 style="font-size:1.5rem;margin:0 0 4px"><?= e((string) $campaign['name']) ?></h1>
        <p class="muted mb-0">
            <span class="badge <?= e($badge[0]) ?>"><?= e($badge[1]) ?></span>
            · #<?= $campaignId ?>
            · <?= e(__('oluşturma: ')) ?><?= e(format_date((string) $campaign['created_at'], 'd.m.Y H:i')) ?>
            <?php if ($campaign['scheduled_at']): ?>· <?= e(__('planlanan: ')) ?><?= e(format_date((string) $campaign['scheduled_at'], 'd.m.Y H:i')) ?><?php endif; ?>
        </p>
    </div>
    <div class="row">
        <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/kampanya')) ?>"><?= e(__('Listeye dön')) ?></a>
        <?php if (!in_array($status, ['sending', 'sent'], true)): ?>
            <a class="btn btn-accent btn-sm" href="<?= e(url('/yonetim/kampanya/' . $campaignId . '/duzenle')) ?>"><?= e(__('Düzenle')) ?></a>
        <?php endif; ?>
    </div>
</div>

<?php if (!$smtpReady): ?>
    <div class="alert alert--error"><?= e(__('SMTP tanımlı değil — gönderim yapılamaz. Ayarlar → E-posta bölümünü doldurun.')) ?></div>
<?php endif; ?>

<div class="grid grid-4 mb-3">
    <div class="card stat"><div class="stat__value"><?= (int) $stats['total'] ?></div><div class="stat__label"><?= e(__('Alıcı')) ?></div></div>
    <div class="card stat"><div class="stat__value"><?= (int) $stats['sent'] ?></div><div class="stat__label"><?= e(__('Gönderilen')) ?></div></div>
    <div class="card stat"><div class="stat__value"><?= (int) $stats['opened'] ?></div><div class="stat__label"><?= e(__('Açılan')) ?></div></div>
    <div class="card stat"><div class="stat__value"><?= (int) $stats['failed'] ?></div><div class="stat__label"><?= e(__('Hata')) ?></div></div>
</div>

<div class="card mb-3">
    <h2 style="font-size:1.05rem"><?= e(__('Kampanya bilgileri')) ?></h2>
    <p class="mb-1"><strong><?= e(__('Konu:')) ?></strong> <?= e((string) $campaign['subject']) ?></p>
    <p class="muted mb-1" style="font-size:13.5px"><strong><?= e(__('Hedef kitle:')) ?></strong> <?= e($audienceLabel) ?></p>
    <p class="muted mb-1" style="font-size:13.5px">
        <?= e(__('Bekleyen: ')) ?><?= (int) $stats['pending'] ?>
        · <?= e(__('pazarlama izni olmadığı için atlanan: ')) ?><?= (int) $stats['unsubscribed'] ?>
        · <?= e(__('kuyrukta bekleyen toplam bildirim: ')) ?><?= (int) $queue ?>
    </p>
    <?php if ($campaign['started_at']): ?>
        <p class="muted mb-0" style="font-size:13px"><?= e(__('Başlangıç: ')) ?><?= e(format_date((string) $campaign['started_at'], 'd.m.Y H:i')) ?><?= $campaign['finished_at'] ? ' · ' . e(__('bitiş: ')) . e(format_date((string) $campaign['finished_at'], 'd.m.Y H:i')) : '' ?></p>
    <?php endif; ?>

    <div class="row mt-3">
        <?php if (in_array($status, ['draft', 'scheduled', 'sending'], true)): ?>
            <form method="post" action="<?= e(url('/yonetim/kampanya/gonder')) ?>" data-confirm="<?= e(__('Kampanya şimdi gönderilecek. Onaylıyor musunuz?')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="campaign_id" value="<?= $campaignId ?>">
                <button class="btn btn-accent" type="submit"><?= e(__('Kuyruğa al ve gönder')) ?></button>
            </form>
            <form method="post" action="<?= e(url('/yonetim/kampanya/kuyruk')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="campaign_id" value="<?= $campaignId ?>">
                <button class="btn btn-outline" type="submit"><?= e(__('Yalnızca kuyruğa al')) ?></button>
            </form>
            <form method="post" action="<?= e(url('/yonetim/kampanya/iptal')) ?>" data-confirm="<?= e(__('Kampanya iptal edilecek ve kuyruktaki e-postalar durdurulacak. Onaylıyor musunuz?')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="campaign_id" value="<?= $campaignId ?>">
                <button class="btn btn-outline" type="submit" style="color:var(--c-danger);border-color:var(--c-danger)"><?= e(__('İptal et')) ?></button>
            </form>
        <?php endif; ?>
        <?php if (in_array($status, ['draft', 'scheduled', 'cancelled'], true)): ?>
            <form method="post" action="<?= e(url('/yonetim/kampanya/sil')) ?>" data-confirm="<?= e(__('Kampanya silinecek. Onaylıyor musunuz?')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="campaign_id" value="<?= $campaignId ?>">
                <button class="btn btn-outline" type="submit" style="color:var(--c-danger);border-color:var(--c-danger)"><?= e(__('Sil')) ?></button>
            </form>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/yonetim/kampanya/onizleme')) ?>" target="_blank">
            <?= csrf_field() ?>
            <input type="hidden" name="campaign_id" value="<?= $campaignId ?>">
            <button class="btn btn-outline" type="submit"><?= e(__('Önizle')) ?></button>
        </form>
        <a class="btn btn-outline" href="<?= e(url('/yonetim/bildirimler?' . http_build_query(['q' => (string) $campaign['subject']]))) ?>"><?= e(__('Bildirim günlüğünde gör')) ?></a>
    </div>
</div>

<div class="card">
    <h2 style="font-size:1.05rem"><?= e(__('Alıcılar')) ?> <span class="muted" style="font-size:13px">(<?= count($recipients) ?> / <?= (int) $stats['total'] ?>)</span></h2>
    <?php if ($recipients === []): ?>
        <p class="muted mb-0"><?= e(__('Kampanya henüz kuyruğa alınmadı; alıcı listesi gönderim başladığında oluşur.')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr>
                    <th><?= e(__('Alıcı')) ?></th>
                    <th><?= e(__('Kurum')) ?></th>
                    <th><?= e(__('Durum')) ?></th>
                    <th><?= e(__('Gönderim')) ?></th>
                    <th><?= e(__('Açılma')) ?></th>
                    <th><?= e(__('Not')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($recipients as $recipient): ?>
                    <tr>
                        <td>
                            <?= e((string) $recipient['email']) ?>
                            <div class="muted" style="font-size:12.5px"><?= e((string) ($recipient['full_name'] ?? '')) ?></div>
                        </td>
                        <td class="mono">#<?= (int) $recipient['user_id'] ?></td>
                        <td><span class="badge <?= e($recipientBadge[(string) $recipient['status']] ?? 'badge--info') ?>"><?= e((string) $recipient['status']) ?></span></td>
                        <td class="muted" style="font-size:13px"><?= $recipient['sent_at'] ? e(format_date((string) $recipient['sent_at'], 'd.m.Y H:i')) : '—' ?></td>
                        <td class="muted" style="font-size:13px"><?= $recipient['opened_at'] ? e(format_date((string) $recipient['opened_at'], 'd.m.Y H:i')) : '—' ?></td>
                        <td class="muted" style="font-size:12.5px"><?= e((string) ($recipient['error'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
