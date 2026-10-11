<?php
use App\Core\Settings;
use App\Core\Translator;

$siteTitle = (string) Settings::get('brand.title', 'evrako');
$accent = (string) (Settings::get('theme.colors', [])['light']['accent'] ?? '#00C48C');
?>
<?php if (!empty($slides)): ?>
<section class="slider" data-slider data-autoplay="6500" aria-label="Tanıtım">
    <div class="slider__track">
        <?php foreach ($slides as $slide): ?>
            <div class="slide slide--<?= e((string) ($slide['text_position'] ?? 'left')) ?>">
                <?php if (!empty($slide['image_path'])): ?>
                    <img class="slide__media" src="<?= e(media_url((string) $slide['image_path'])) ?>" alt="<?= e((string) ($slide['title'] ?? '')) ?>">
                <?php endif; ?>
                <div class="slide__overlay" style="--overlay: <?= (int) ($slide['overlay_opacity'] ?? 35) / 100 ?>"></div>
                <div class="slide__content">
                    <?php if (!empty($slide['title'])): ?><h1><?= e((string) $slide['title']) ?></h1><?php endif; ?>
                    <?php if (!empty($slide['subtitle'])): ?><h3 style="font-weight:600;opacity:.92"><?= e((string) $slide['subtitle']) ?></h3><?php endif; ?>
                    <?php if (!empty($slide['description'])): ?><p><?= e((string) $slide['description']) ?></p><?php endif; ?>
                    <?php if (!empty($slide['button_text'])): ?>
                        <a class="btn btn-accent mt-2"
                           href="<?= e((string) $slide['button_url']) ?>"
                           target="<?= e((string) ($slide['button_target'] ?? '_self')) ?>"
                           rel="noopener"><?= e((string) $slide['button_text']) ?></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php else: ?>
<section class="hero">
    <div class="container">
        <span class="badge badge--ok">🇨🇾 <?= e('Kıbrıs için özel tasarlandı') ?></span>
        <h1 class="mt-2"><?= e($siteTitle) ?> — <?= e((string) Settings::get('brand.slogan', '')) ?></h1>
        <p class="lead"><?= e((string) Settings::get('seo.meta_description', '')) ?></p>
        <div class="hero__cta">
            <button class="btn btn-accent" data-open-drawer="register"><?= e(t('auth.register')) ?></button>
            <a class="btn btn-outline" href="<?= e(url('/nasil-calisir')) ?>"><?= e(t('nav.how')) ?></a>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section">
    <div class="container">
        <div class="section-head">
            <h2><?= e('Bir kez kaydet, gerisini biz hatırlatalım') ?></h2>
            <p><?= e('Muayene, sigorta, yol vergisi ve diğer tüm evraklarınızın bitiş tarihini takip edin; süresi dolmadan haberdar olun.') ?></p>
        </div>
        <div class="grid grid-3">
            <div class="card card--hover">
                <div class="feature-icon">🛡️</div>
                <h3><?= e('Süresi dolmadan uyarı') ?></h3>
                <p class="muted mb-0"><?= e('7, 3 ve 1 gün önce otomatik e-posta. İsterseniz süreleri kendiniz belirleyin.') ?></p>
            </div>
            <div class="card card--hover">
                <div class="feature-icon">🚗</div>
                <h3><?= e('Sınırsız araç takibi') ?></h3>
                <p class="muted mb-0"><?= e('Otomobil, motosiklet, kamyonet, traktör, tekne… Tüm araçlarınız tek panelde.') ?></p>
            </div>
            <div class="card card--hover">
                <div class="feature-icon">📄</div>
                <h3><?= e('Belgeler arşivde') ?></h3>
                <p class="muted mb-0"><?= e('Ruhsat ve poliçe fotoğraflarını yükleyin; yenileme tarihçesi kaybolmasın.') ?></p>
            </div>
            <div class="card card--hover">
                <div class="feature-icon">📱</div>
                <h3><?= e('Çok kanallı bildirim') ?></h3>
                <p class="muted mb-0"><?= e('E-posta, SMS, WhatsApp ve Telegram ile dilediğiniz kanaldan hatırlatma alın.') ?></p>
            </div>
            <div class="card card--hover">
                <div class="feature-icon">👨‍👩‍👧</div>
                <h3><?= e('Aile ve filo desteği') ?></h3>
                <p class="muted mb-0"><?= e('Birden fazla kullanıcı aynı araçları yönetebilir; kurumsal filo hesapları mevcuttur.') ?></p>
            </div>
            <div class="card card--hover">
                <div class="feature-icon">🌍</div>
                <h3><?= e('6 dilde kullanım') ?></h3>
                <p class="muted mb-0"><?= e('Türkçe, İngilizce, Rusça, Arapça, Farsça ve Yunanca arayüz.') ?></p>
            </div>
        </div>
    </div>
</section>

<section class="section" style="background: color-mix(in srgb, var(--c-primary) 4%, transparent)">
    <div class="container">
        <div class="split split--reverse">
            <div>
                <div class="mock" aria-hidden="true">
                    <div class="mock__bar"><i></i><i></i><i></i></div>
                    <div class="mock__body">
                        <div class="mock__row"><span class="mock__plate">ABC 123</span><span class="muted">Trafik sigortası</span><span class="badge badge--danger">3 gün kaldı</span></div>
                        <div class="mock__row"><span class="mock__plate">KLM 456</span><span class="muted">Muayene</span><span class="badge badge--warn">18 gün kaldı</span></div>
                        <div class="mock__row"><span class="mock__plate">XYZ 789</span><span class="muted">Yol vergisi</span><span class="badge badge--ok">6 ay kaldı</span></div>
                        <div class="mock__row"><span class="mock__plate">DEF 321</span><span class="muted">Kasko</span><span class="badge badge--info">2 ay kaldı</span></div>
                    </div>
                </div>
            </div>
            <div>
                <h2><?= e('Hayat artık daha kolay') ?></h2>
                <p class="muted"><?= e('Evrak tarihçesi, hatırlatma planı ve tüm belgeleriniz tek ekranda toplanır. Ceza, trafikten men veya kaza anında mağduriyet riski ortadan kalkar.') ?></p>
                <ul class="list-check">
                    <li><?= e('Hangi araçta ne zaman ne var — tek bakışta görün') ?></li>
                    <li><?= e('Belge yenilendiğinde tek tıkla güncelle, tarih geçmişi kalsın') ?></li>
                    <li><?= e('Takviminize otomatik ekleyin, PDF rapor alın') ?></li>
                </ul>
                <a class="btn btn-primary mt-3" href="<?= e(url('/nasil-calisir')) ?>"><?= e('Nasıl çalıştığını gör') ?></a>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head">
            <h2><?= e('Sistem nasıl çalışır?') ?></h2>
            <p><?= e('Dört adımda kurulum, yıllarca güvenli takip.') ?></p>
        </div>
        <div class="steps">
            <div class="step"><h3><?= e('Aracını ekle') ?></h3><p><?= e('Araç türünü seç, plaka ve marka bilgisini gir. 30 saniye sürer.') ?></p></div>
            <div class="step"><h3><?= e('Evrak tarihlerini gir') ?></h3><p><?= e('Muayene, sigorta, yol vergisi… bitiş tarihlerini kaydet.') ?></p></div>
            <div class="step"><h3><?= e('Hatırlatma planını seç') ?></h3><p><?= e('7 / 3 / 1 gün önce otomatik uyarı. Süreleri kendin belirle.') ?></p></div>
            <div class="step"><h3><?= e('Gerisini bırak') ?></h3><p><?= e('Sistem süresi dolmadan e-posta, SMS veya WhatsApp ile haber verir.') ?></p></div>
        </div>
    </div>
</section>

<section class="section" id="uyelik" style="background: color-mix(in srgb, var(--c-primary) 4%, transparent)">
    <div class="container">
        <div class="section-head">
            <h2><?= e('Üyelik nasıl olunuyor?') ?></h2>
            <p><?= e('Kısa formla hesap açın, ilk aracınızı hemen kaydedin. Kredi kartı istemiyoruz.') ?></p>
        </div>
        <div class="grid grid-3">
            <div class="card">
                <span class="badge badge--info">1</span>
                <h3 class="mt-2"><?= e('Sağ üstten "Giriş / Üyelik"') ?></h3>
                <p class="muted mb-0"><?= e('Sayfanın sağ üstündeki butona basın, yan panel açılır. "Üyelik" sekmesine geçin.') ?></p>
            </div>
            <div class="card">
                <span class="badge badge--info">2</span>
                <h3 class="mt-2"><?= e('Ad, e-posta, şifre') ?></h3>
                <p class="muted mb-0"><?= e('Yalnızca üç alan yeterli. Telefon ve WhatsApp alanları isteğe bağlıdır.') ?></p>
            </div>
            <div class="card">
                <span class="badge badge--info">3</span>
                <h3 class="mt-2"><?= e('Aracını kaydet') ?></h3>
                <p class="muted mb-0"><?= e('Panele girin, aracınızı ve evraklarını ekleyin. Hatırlatmalar otomatik başlar.') ?></p>
            </div>
        </div>
        <div class="center mt-3">
            <button class="btn btn-accent" data-open-drawer="register"><?= e('Ücretsiz hesap aç') ?></button>
        </div>
    </div>
</section>

<?php if (!empty($plans)): ?>
<section class="section">
    <div class="container">
        <div class="section-head"><h2><?= e(t('nav.pricing')) ?></h2></div>
        <div class="grid grid-4">
            <?php foreach ($plans as $index => $plan): ?>
                <?php $labels = json_decode((string) $plan['features_json'], true); $names = $labels['labels'] ?? []; ?>
                <div class="card plan <?= $index === 2 ? 'plan--featured' : '' ?>">
                    <h3 class="mb-0"><?= e((string) ($names[Translator::locale()] ?? $plan['name'])) ?></h3>
                    <div class="plan__price">
                        <?= $plan['price'] > 0 ? e(number_format((float) $plan['price'], 0, ',', '.')) : e('Ücretsiz') ?>
                        <?php if ($plan['price'] > 0): ?><small>/ <?= e(t('nav.pricing')) ?></small><?php endif; ?>
                    </div>
                    <ul>
                        <li><?= (int) $plan['max_vehicles'] >= 250 ? e('Sınırsız araç') : e((int) $plan['max_vehicles'] . ' araç') ?></li>
                        <li><?= e((int) $plan['max_users'] . ' kullanıcı') ?></li>
                        <li><?= e(strtoupper(str_replace(',', ', ', (string) $plan['channels']))) ?></li>
                    </ul>
                    <button class="btn btn-outline btn-block mt-2" data-open-drawer="register"><?= e('Başla') ?></button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($faqs)): ?>
<section class="section" style="background: color-mix(in srgb, var(--c-primary) 4%, transparent)">
    <div class="container">
        <div class="section-head"><h2><?= e(t('nav.faq')) ?></h2></div>
        <div class="accordion">
            <?php foreach ($faqs as $faq): ?>
                <details>
                    <summary><?= e((string) $faq['question']) ?></summary>
                    <div class="accordion__body"><?= e((string) $faq['answer']) ?></div>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section">
    <div class="container">
        <div class="cta-band">
            <h2><?= e('Evrak bitişini bir daha kaçırmayın') ?></h2>
            <p><?= e('Aracınızı kaydedin, hatırlatmayı bize bırakın.') ?></p>
            <button class="btn btn-accent mt-2" data-open-drawer="register"><?= e(t('auth.register')) ?></button>
        </div>
    </div>
</section>
