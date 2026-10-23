<?php
/**
 * Yeni duyuru formu.
 * $options: plans, organizations, all ; $values: eski form değerleri
 */
use App\Core\Security;

$plans = $options['plans'] ?? [];
$organizations = $options['organizations'] ?? [];
$audienceAll = (int) ($options['all'] ?? 0);
$val = static fn (string $key, string $default = ''): string => (string) old($key, $values[$key] ?? $default);
?>
<div class="row row--between mb-3">
    <div>
        <h1 style="font-size:1.5rem;margin:0 0 4px">📢 <?= e(__('Yeni bildirim')) ?></h1>
        <p class="muted mb-0"><?= e(__('Bildirim, müşteri panelindeki zil menüsünde ve Bildirimler sayfasında görünür; cihaz bildirimlerini açan müşterilere telefon/bilgisayar bildirimi olarak da gider.')) ?></p>
    </div>
    <a class="btn btn-outline btn-sm" href="<?= e(url('/yonetim/duyurular')) ?>">← <?= e(__('Bildirim')) ?></a>
</div>

<?php if ($audienceAll === 0): ?>
    <div class="alert alert--error"><?= e(__('Bildirim gönderilebilecek aktif kullanıcı yok. Önce üye kaydı oluşturun.')) ?></div>
<?php endif; ?>

<form method="post" action="<?= e(url('/yonetim/duyurular')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="grid" style="grid-template-columns:minmax(0,1.35fr) minmax(0,1fr);gap:16px;align-items:start">
        <div>
            <div class="card mb-3">
                <h2 style="font-size:1.05rem" data-hint="Duyuru içeriği|Başlık zil listesinde kalın görünür. Metin en fazla 2000 karakterdir; satır sonları korunur."><?= e(__('İçerik')) ?></h2>
                <div class="field">
                    <label for="ann-title"><?= e(__('Başlık')) ?> <span class="muted">*</span></label>
                    <input id="ann-title" type="text" name="title" maxlength="160" required
                           value="<?= e($val('title')) ?>" placeholder="<?= e(__('Örn. Yeni dönem kampanyası başladı')) ?>"
                           data-ann-preview="title">
                </div>
                <div class="field">
                    <label for="ann-body"><?= e(__('Metin')) ?> <span class="muted">*</span></label>
                    <textarea id="ann-body" name="body" rows="5" maxlength="2000" required
                              placeholder="<?= e(__('Kısa ve net bir açıklama yazın. Örn. 31 Ekim’e kadar tüm paketlerde %20 indirim.')) ?>"
                              data-ann-preview="body"><?= e($val('body')) ?></textarea>
                    <p class="hint"><span data-ann-counter>0</span> / 2000</p>
                </div>
            </div>

            <div class="card mb-3">
                <h2 style="font-size:1.05rem" data-hint="Görsel ve bağlantı|Görsel isteğe bağlıdır (en fazla 3 MB, jpg/png/webp/gif). Bağlantı, bildirimin altında bir buton olarak görünür."><?= e(__('Görsel ve buton')) ?></h2>
                <div class="field">
                    <label for="ann-image"><?= e(__('Görsel (isteğe bağlı)')) ?></label>
                    <input id="ann-image" type="file" name="image" accept=".jpg,.jpeg,.png,.webp,.gif,.avif"
                           data-ann-preview="image">
                    <p class="hint"><?= e(__('Yatay görsel önerilir (16:9). En fazla 3 MB; jpg, png, webp, gif, avif.')) ?></p>
                </div>
                <div class="field-grid">
                    <div class="field">
                        <label for="ann-link-label"><?= e(__('Buton yazısı')) ?></label>
                        <input id="ann-link-label" type="text" name="link_label" maxlength="80"
                               value="<?= e($val('link_label')) ?>" placeholder="<?= e(__('Örn. Kampanyayı gör')) ?>"
                               data-ann-preview="link_label">
                    </div>
                    <div class="field">
                        <label for="ann-link"><?= e(__('Buton bağlantısı')) ?></label>
                        <input id="ann-link" type="text" name="link_url" maxlength="500"
                               value="<?= e($val('link_url')) ?>" placeholder="<?= e('/fiyatlar veya https://…') ?>"
                               data-ann-preview="link">
                        <p class="hint"><?= e(__('Yalnızca / ile başlayan site yolu veya http(s) adresi kabul edilir.')) ?></p>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <h2 style="font-size:1.05rem" data-hint="Hedef kitle|Boş bırakılan filtreler tüm aktif kullanıcıları kapsar. Belirli bir kullanıcı e-postası girildiğinde diğer filtreler yok sayılır."><?= e(__('Hedef kitle')) ?></h2>
                <div class="field-grid">
                    <div class="field">
                        <label for="ann-plan"><?= e(__('Paket')) ?></label>
                        <select id="ann-plan" name="audience_plan_key">
                            <option value=""><?= e(__('Tüm paketler')) ?></option>
                            <?php foreach ($plans as $plan): ?>
                                <option value="<?= e((string) $plan['key']) ?>" <?= $val('audience_plan_key') === (string) $plan['key'] ? 'selected' : '' ?>>
                                    <?= e((string) $plan['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="ann-org"><?= e(__('Kurum')) ?></label>
                        <select id="ann-org" name="audience_org_id">
                            <option value="0"><?= e(__('Tüm kurumlar')) ?></option>
                            <?php foreach ($organizations as $org): ?>
                                <option value="<?= (int) $org['id'] ?>" <?= (int) $val('audience_org_id', '0') === (int) $org['id'] ? 'selected' : '' ?>>
                                    <?= e((string) $org['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field" style="grid-column:1/-1">
                        <label for="ann-user"><?= e(__('Tek kullanıcı (e-posta)')) ?></label>
                        <input id="ann-user" type="email" name="audience_user_email" maxlength="190"
                               value="<?= e($val('audience_user_email')) ?>"
                               placeholder="<?= e(__('Boş bırakılırsa yukarıdaki filtreler uygulanır')) ?>">
                        <p class="hint"><?= e(__('Şu an bildirim alabilecek toplam kullanıcı: ')) ?><strong><?= $audienceAll ?></strong></p>
                    </div>
                </div>
            </div>

            <div class="row" style="gap:10px">
                <button class="btn btn-accent" type="submit"><?= e(__('Bildirimi gönder')) ?></button>
                <a class="btn btn-outline" href="<?= e(url('/yonetim/duyurular')) ?>"><?= e(__('Vazgeç')) ?></a>
            </div>
        </div>

        <aside style="position:sticky;top:16px">
            <div class="card">
                <h2 style="font-size:1rem"><?= e(__('Panelde nasıl görünecek?')) ?></h2>
                <div class="inbox-feed" style="gap:0">
                    <article class="inbox-card is-unread" style="border:0;border-radius:12px;background:color-mix(in srgb,var(--c-accent) 5%,var(--c-card))">
                        <img class="inbox-card__media" data-ann-image-preview src="" alt=""
                             style="display:none;width:96px;height:72px;border-radius:11px;object-fit:cover">
                        <div class="inbox-card__body">
                            <div class="inbox-card__top">
                                <span class="inbox-card__kind"><?= e(__('Duyuru · az önce')) ?></span>
                                <span class="badge badge--warn"><?= e(__('Yeni')) ?></span>
                            </div>
                            <h3 class="inbox-card__title" data-ann-out="title"><?= e($val('title') !== '' ? $val('title') : 'Duyuru başlığı') ?></h3>
                            <p class="inbox-card__text" data-ann-out="body"><?= e($val('body') !== '' ? $val('body') : 'Duyuru metni burada görünür.') ?></p>
                            <div class="inbox-card__actions">
                                <span class="btn btn-accent btn-sm" data-ann-out="link_label" style="<?= $val('link_label') === '' ? 'display:none' : '' ?>"><?= e($val('link_label') !== '' ? $val('link_label') : 'Detayı gör') ?></span>
                            </div>
                        </div>
                    </article>
                </div>
                <p class="hint mt-2"><?= e(__('Bildirim, kullanıcı zili açtığında ve /panel/bildirimler sayfasında listelenir. E-posta gönderilmez.')) ?></p>
            </div>
        </aside>
    </div>
</form>
<script nonce="<?= e(Security::nonce()) ?>">
(function () {
    var title = document.querySelector('[data-ann-preview="title"]');
    var body = document.querySelector('[data-ann-preview="body"]');
    var linkLabel = document.querySelector('[data-ann-preview="link_label"]');
    var image = document.querySelector('[data-ann-preview="image"]');
    var imgPreview = document.querySelector('[data-ann-image-preview]');
    var counter = document.querySelector('[data-ann-counter]');

    function put(name, value, fallback) {
        var el = document.querySelector('[data-ann-out="' + name + '"]');
        if (el) { el.textContent = value && value.trim() !== '' ? value : fallback; }
    }

    function sync() {
        put('title', title && title.value, 'Duyuru başlığı');
        put('body', body && body.value, 'Duyuru metni burada görünür.');
        var out = document.querySelector('[data-ann-out="link_label"]');
        if (out) {
            var label = linkLabel && linkLabel.value.trim();
            out.textContent = label || 'Detayı gör';
            out.style.display = label ? '' : 'none';
        }
        if (counter && body) { counter.textContent = String(body.value.length); }
    }

    [title, body, linkLabel].forEach(function (el) {
        if (el) { el.addEventListener('input', sync); }
    });

    if (image && imgPreview) {
        image.addEventListener('change', function () {
            var file = image.files && image.files[0];
            if (!file) { imgPreview.style.display = 'none'; imgPreview.removeAttribute('src'); return; }
            var reader = new FileReader();
            reader.onload = function () { imgPreview.src = String(reader.result); imgPreview.style.display = ''; };
            reader.readAsDataURL(file);
        });
    }

    sync();
})();
</script>
