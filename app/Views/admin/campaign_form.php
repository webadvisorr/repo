<?php
/** Kampanya formu. $campaign (?array), $audience, $options (plans, organizations, all, current), $stats (?array) */
use App\Core\Settings;
use App\Core\Translator;
use App\Services\CampaignService;

$isEdit = $campaign !== null;
$campaignId = $isEdit ? (int) $campaign['id'] : 0;
$planKey = (string) ($audience['plan_key'] ?? '');
$orgId = (int) ($audience['org_id'] ?? 0);
$localeFilter = (string) ($audience['locale'] ?? '');
$optIn = !empty($audience['only_marketing_opt_in']);
?>
<div class="row row--between mb-3">
    <div>
        <h1 style="font-size:1.5rem;margin:0 0 4px"><?= e($isEdit ? 'Kampanyayı düzenle' : 'Yeni kampanya') ?></h1>
        <p class="muted mb-0"><?= e($isEdit ? (string) $campaign['name'] : 'Hedef kitleyi seçin, içeriği yazın; önizleyip test ettikten sonra gönderin.') ?></p>
    </div>
    <div class="row">
        <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/kampanya')) ?>"><?= e(__('Listeye dön')) ?></a>
        <?php if ($isEdit): ?>
            <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/kampanya/' . $campaignId)) ?>"><?= e(__('Detay')) ?></a>
        <?php endif; ?>
    </div>
</div>

<form method="post" action="<?= e(url('/yonetim/kampanya/kaydet')) ?>" class="card mb-3">
    <?= csrf_field() ?>
    <input type="hidden" name="campaign_id" value="<?= $campaignId ?>">

    <h2 style="font-size:1.05rem">1. <?= e(__('İçerik')) ?></h2>
    <div class="field-grid">
        <div class="field">
            <label for="c-name"><?= e(__('Kampanya adı (iç kullanım)')) ?></label>
            <input id="c-name" type="text" name="name" maxlength="160" required value="<?= e((string) ($campaign['name'] ?? old('name'))) ?>">
        </div>
        <div class="field">
            <label for="c-subject"><?= e(__('E-posta konusu')) ?></label>
            <input id="c-subject" type="text" name="subject" maxlength="255" required value="<?= e((string) ($campaign['subject'] ?? old('subject'))) ?>">
        </div>
    </div>

    <div class="field">
        <label for="c-body"><?= e(__('E-posta içeriği (HTML)')) ?></label>
        <textarea id="c-body" name="body_html" rows="12" required style="font-family:ui-monospace,Menlo,monospace;font-size:13px"><?= e((string) ($campaign['body_html'] ?? old('body_html'))) ?></textarea>
        <p class="hint"><?= e(__('Basit HTML kullanabilirsiniz: <p>, <strong>, <a href="...">, <img src="...">. Konuda ve içerikte değişken kullanabilirsiniz.')) ?></p>
    </div>

    <div class="field">
        <label><?= e(__('Kullanılabilir değişkenler')) ?></label>
        <p class="muted mb-0" style="font-size:13px">
            <?php foreach (CampaignService::variables() as $token => $label): ?>
                <span class="mono" style="display:inline-block;margin:2px 8px 2px 0"><?= e($token) ?></span><span class="muted">(<?= e($label) ?>)</span><br>
            <?php endforeach; ?>
        </p>
        <p class="hint"><?= e(__('Abonelikten çıkma bağlantısı, KVKK satırı ve açılma takibi alt bilgisi gönderim anında otomatik eklenir.')) ?></p>
    </div>

    <h2 style="font-size:1.05rem">2. <?= e(__('Hedef kitle')) ?></h2>
    <div class="field-grid">
        <div class="field">
            <label for="c-plan"><?= e(__('Abonelik planı')) ?></label>
            <select id="c-plan" name="audience_plan_key">
                <option value=""><?= e(__('Tüm planlar')) ?></option>
                <?php foreach ($options['plans'] as $plan): ?>
                    <option value="<?= e((string) $plan['key']) ?>" <?= $planKey === (string) $plan['key'] ? 'selected' : '' ?>><?= e((string) $plan['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="c-org"><?= e(__('Kurum')) ?></label>
            <select id="c-org" name="audience_org_id">
                <option value="0"><?= e(__('Tüm kurumlar')) ?></option>
                <?php foreach ($options['organizations'] as $org): ?>
                    <option value="<?= (int) $org['id'] ?>" <?= $orgId === (int) $org['id'] ? 'selected' : '' ?>>#<?= (int) $org['id'] ?> · <?= e((string) $org['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="c-locale"><?= e(__('Dil')) ?></label>
            <select id="c-locale" name="audience_locale">
                <option value=""><?= e(__('Tüm diller')) ?></option>
                <?php foreach (Translator::options() as $code => $name): ?>
                    <option value="<?= e((string) $code) ?>" <?= $localeFilter === (string) $code ? 'selected' : '' ?>><?= e((string) $name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="field check">
        <input id="c-optin" type="checkbox" name="audience_optin" value="1" <?= $optIn ? 'checked' : '' ?>>
        <label for="c-optin"><?= e(__('Yalnızca pazarlama e-postası izni olanlar (önerilir)')) ?></label>
    </div>
    <p class="muted mb-0" style="font-size:13.5px">
        <?= e(__('Tüm uygun alıcı: ')) ?><strong><?= (int) $options['all'] ?></strong>
        · <?= e(__('bu filtrelerle: ')) ?><strong><?= (int) $options['current'] ?></strong>
        <span class="muted"><?= e(__('— aktif, e-posta bildirimi açık ve abonelikten çıkmamış kullanıcılar sayılır.')) ?></span>
    </p>

    <h2 style="font-size:1.05rem">3. <?= e(__('Gönderim zamanı')) ?></h2>
    <div class="field">
        <label for="c-when"><?= e(__('Planlanan gönderim (boş bırakırsanız taslak olarak kalır)')) ?></label>
        <input id="c-when" type="datetime-local" name="scheduled_at"
               value="<?= e($campaign && $campaign['scheduled_at'] ? date('Y-m-d\TH:i', strtotime((string) $campaign['scheduled_at'])) : '') ?>">
        <p class="hint"><?= e(__('Zamanı gelen kampanyalar günlük cron çalıştırmasında (veya kuyruk işlendiğinde) otomatik gönderilir.')) ?></p>
    </div>

    <div class="row">
        <button class="btn btn-accent" type="submit"><?= e($isEdit ? 'Değişiklikleri kaydet' : 'Kampanyayı kaydet') ?></button>
    </div>
</form>

<?php if ($isEdit): ?>
    <?php $status = (string) $campaign['status']; ?>
    <div class="card mb-3">
        <h2 style="font-size:1.05rem"><?= e(__('Gönderim')) ?></h2>
        <p class="muted mb-2" style="font-size:13.5px">
            <?= e(__('Durum: ')) ?><strong><?= e($status) ?></strong>
            <?php if (!empty($stats)): ?>
                · <?= e(__('alıcı: ')) ?><strong><?= (int) $stats['total'] ?></strong>
                · <?= e(__('gönderilen: ')) ?><strong><?= (int) $stats['sent'] ?></strong>
                · <?= e(__('açılan: ')) ?><strong><?= (int) $stats['opened'] ?></strong>
                · <?= e(__('hata: ')) ?><strong><?= (int) $stats['failed'] ?></strong>
                · <?= e(__('izinsiz: ')) ?><strong><?= (int) $stats['unsubscribed'] ?></strong>
            <?php endif; ?>
        </p>
        <div class="row">
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
        </div>
    </div>

    <div class="card">
        <h2 style="font-size:1.05rem"><?= e(__('Önizleme ve test')) ?></h2>
        <p class="muted mb-2" style="font-size:13.5px"><?= e(__('Test e-postası hedef kitleye sayılmaz; yalnızca belirttiğiniz adrese gönderilir.')) ?></p>
        <div class="row">
            <form method="post" action="<?= e(url('/yonetim/kampanya/onizleme')) ?>" target="_blank">
                <?= csrf_field() ?>
                <input type="hidden" name="campaign_id" value="<?= $campaignId ?>">
                <button class="btn btn-outline" type="submit"><?= e(__('Önizle (yeni sekme)')) ?></button>
            </form>
            <form method="post" action="<?= e(url('/yonetim/kampanya/test')) ?>" class="row">
                <?= csrf_field() ?>
                <input type="hidden" name="campaign_id" value="<?= $campaignId ?>">
                <input type="email" name="test_email" required placeholder="test@ornek.com" style="min-width:220px"
                       value="<?= e((string) Settings::get('brand.email', '')) ?>">
                <button class="btn btn-primary" type="submit"><?= e(__('Test gönder')) ?></button>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <p class="muted mb-0"><?= e(__('Önizleme, test gönderimi ve gönderim düğmeleri kampanya kaydedildikten sonra görünür.')) ?></p>
    </div>
<?php endif; ?>
