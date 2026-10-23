<?php
use App\Core\Settings;
use App\Services\CurrencyService;

$siteTitle = (string) Settings::get('brand.title', 'Evrako');
$kenburns = (string) Settings::get('slider.kenburns', '1') === '1';
$autoplay = (int) Settings::get('slider.autoplay', '6500');
?>
<?php if (!empty($slides)): ?>
<section class="slider" data-slider data-autoplay="<?= $autoplay ?>"<?= $kenburns ? ' data-kenburns="1"' : '' ?> aria-label="<?= e(__('Tanıtım')) ?>" aria-roledescription="carousel">
    <div class="slider__track">
        <?php foreach ($slides as $index => $slide): ?>
            <?php $position = (string) ($slide['text_position'] ?? 'left'); ?>
            <div class="slide slide--<?= e($position) ?>" role="group" aria-roledescription="slide" aria-label="<?= e(($index + 1) . ' / ' . count($slides)) ?>">
                <?php if (!empty($slide['image_path'])): ?>
                    <img class="slide__media"
                         src="<?= e(media_url((string) $slide['image_path'])) ?>"
                         alt="<?= e(__((string) ($slide['title'] ?? ''))) ?>"
                         <?= $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async">
                <?php endif; ?>
                <div class="slide__overlay" style="--overlay: <?= (int) ($slide['overlay_opacity'] ?? 35) / 100 ?>"></div>
                <div class="slide__content">
                    <?php if (!empty($slide['title'])): ?><h1><?= e(__((string) $slide['title'])) ?></h1><?php endif; ?>
                    <?php if (!empty($slide['subtitle'])): ?><h3 class="slide__subtitle"><?= e(__((string) $slide['subtitle'])) ?></h3><?php endif; ?>
                    <?php if (!empty($slide['description'])): ?><p><?= e(__((string) $slide['description'])) ?></p><?php endif; ?>
                    <div class="slide__actions">
                        <?php if (!empty($slide['button_text'])): ?>
                            <a class="btn btn-accent"
                               href="<?= e((string) ($slide['button_url'] ?? '#')) ?>"
                               target="<?= e((string) ($slide['button_target'] ?? '_self')) ?>"
                               rel="noopener"><?= e(__((string) $slide['button_text'])) ?></a>
                        <?php endif; ?>
                        <a class="btn btn-ghost-light" href="<?= e(url('/nasil-calisir')) ?>"><?= e(t('nav.how')) ?></a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="slider__progress" aria-hidden="true"><span></span></div>
</section>

<div class="trust-strip">
    <div class="container trust-strip__inner">
        <span class="trust-item"><b>7 / 3 / 1</b> <?= e(__('gün önce otomatik uyarı')) ?></span>
        <span class="trust-item"><b>6</b> <?= e(__('dilde arayüz')) ?></span>
        <span class="trust-item"><b>∞</b> <?= e(__('araç ve evrak geçmişi')) ?></span>
        <span class="trust-item"><b><?= e(__('Kuzey Kıbrıs')) ?></b> <?= e(__('için özel tasarım')) ?></span>
    </div>
</div>
<?php else: ?>
<section class="hero">
    <div class="container">
        <span class="badge badge--ok">🇨🇾 <?= e(__('Kıbrıs için özel tasarlandı')) ?></span>
        <h1 class="mt-2"><?= e($siteTitle) ?> — <?= e(__((string) Settings::get('brand.slogan', ''))) ?></h1>
        <p class="lead"><?= e(__((string) Settings::get('seo.meta_description', ''))) ?></p>
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
            <h2><?= e(__('Bir kez kaydet, gerisini biz hatırlatalım')) ?></h2>
            <p><?= e(__('Muayene, sigorta, seyrüsefer ve diğer tüm evraklarınızın bitiş tarihini takip edin; süresi dolmadan haberdar olun.')) ?></p>
        </div>
        <div class="grid grid-3">
            <div class="card card--hover">
                <div class="feature-icon">🛡️</div>
                <h3><?= e(__('Süresi dolmadan uyarı')) ?></h3>
                <p class="muted mb-0"><?= e(__('7, 3 ve 1 gün önce otomatik e-posta. Tarihi bir kez girmeniz yeterli.')) ?></p>
            </div>
            <div class="card card--hover">
                <div class="feature-icon">🚗</div>
                <h3><?= e(__('Sınırsız araç takibi')) ?></h3>
                <p class="muted mb-0"><?= e(__('Otomobil, motosiklet, kamyonet, traktör, tekne… Tüm araçlarınız tek panelde.')) ?></p>
            </div>
            <div class="card card--hover">
                <div class="feature-icon">📄</div>
                <h3><?= e(__('Belgeler arşivde')) ?></h3>
                <p class="muted mb-0"><?= e(__('Ruhsat ve poliçe fotoğraflarını yükleyin; yenileme tarihçesi kaybolmasın.')) ?></p>
            </div>
            <div class="card card--hover">
                <div class="feature-icon">📱</div>
                <h3><?= e(__('Çok kanallı bildirim')) ?></h3>
                <p class="muted mb-0"><?= e(__('E-posta, SMS, WhatsApp ve Telegram ile dilediğiniz kanaldan hatırlatma alın.')) ?></p>
            </div>
            <div class="card card--hover">
                <div class="feature-icon">👨‍👩‍👧</div>
                <h3><?= e(__('Aile ve filo desteği')) ?></h3>
                <p class="muted mb-0"><?= e(__('Birden fazla kullanıcı aynı araçları yönetebilir; kurumsal filo hesapları mevcuttur.')) ?></p>
            </div>
            <div class="card card--hover">
                <div class="feature-icon">🌍</div>
                <h3><?= e(__('6 dilde kullanım')) ?></h3>
                <p class="muted mb-0"><?= e(__('Türkçe, İngilizce, Rusça, Arapça, Farsça ve Yunanca arayüz.')) ?></p>
            </div>
        </div>
    </div>
</section>

<section class="section" style="background: color-mix(in srgb, var(--c-primary) 4%, transparent)">
    <div class="container">
        <div class="split split--reverse">
            <div>
                <div class="mock">
                    <div class="mock__bar"><i></i><i></i><i></i></div>
                    <div class="mock__body">
                        <div class="mock__row"><span class="mock__plate">AA 123 A</span><span class="muted"><?= e(__('Trafik sigortası')) ?></span><span class="badge badge--danger"><?= e(__('3 gün kaldı')) ?></span></div>
                        <div class="mock__row"><span class="mock__plate">AB 456 B</span><span class="muted"><?= e(__('Muayene')) ?></span><span class="badge badge--warn"><?= e(__('18 gün kaldı')) ?></span></div>
                        <div class="mock__row"><span class="mock__plate">KC 789 C</span><span class="muted"><?= e(__('Seyrüsefer')) ?></span><span class="badge badge--ok"><?= e(__('6 ay kaldı')) ?></span></div>
                        <div class="mock__row"><span class="mock__plate">ND 321 D</span><span class="muted"><?= e(__('Kasko')) ?></span><span class="badge badge--info"><?= e(__('2 ay kaldı')) ?></span></div>
                    </div>
                </div>
            </div>
            <div>
                <h2><?= e(__('Hayat artık daha kolay')) ?></h2>
                <p class="muted"><?= e(__('Evrak tarihçesi, hatırlatma planı ve tüm belgeleriniz tek ekranda toplanır. Ceza, trafikten men veya kaza anında mağduriyet riski ortadan kalkar.')) ?></p>
                <ul class="list-check">
                    <li><?= e(__('Hangi araçta ne zaman ne var — tek bakışta görün')) ?></li>
                    <li><?= e(__('Belge yenilendiğinde tek tıkla güncelle, tarih geçmişi kalsın')) ?></li>
                    <li><?= e(__('Takviminize otomatik ekleyin, PDF rapor alın')) ?></li>
                </ul>
                <a class="btn btn-primary mt-3" href="<?= e(url('/nasil-calisir')) ?>"><?= e(__('Nasıl çalıştığını gör')) ?></a>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head">
            <h2><?= e(__('Sistem nasıl çalışır?')) ?></h2>
            <p><?= e(__('Üç adımda kurulum, yıllarca güvenli takip.')) ?></p>
        </div>
        <div class="steps">
            <div class="step"><h3><?= e(__('Aracını ekle')) ?></h3><p><?= e(__('Araç türünü seç, plaka ve marka bilgisini gir. 30 saniye sürer.')) ?></p></div>
            <div class="step"><h3><?= e(__('Evrak tarihlerini gir')) ?></h3><p><?= e(__('Muayene, sigorta, seyrüsefer… bitiş tarihlerini kaydet.')) ?></p></div>
            <div class="step"><h3><?= e(__('Gerisini bırak')) ?></h3><p><?= e(__('Sistem süresi dolmadan e-posta, SMS veya WhatsApp ile haber verir.')) ?></p></div>
        </div>
    </div>
</section>

<section class="section" id="uyelik" style="background: color-mix(in srgb, var(--c-primary) 4%, transparent)">
    <div class="container">
        <div class="section-head">
            <h2><?= e(__('Üyelik nasıl olunuyor?')) ?></h2>
            <p><?= e(__('Kısa formla hesap açın, ilk aracınızı hemen kaydedin. Kredi kartı istemiyoruz.')) ?></p>
        </div>
        <div class="grid grid-3">
            <div class="card">
                <span class="badge badge--info">1</span>
                <h3 class="mt-2"><?= e(__('Sağ üstten "Giriş / Üyelik"')) ?></h3>
                <p class="muted mb-0"><?= e(__('Sayfanın sağ üstündeki butona basın, yan panel açılır. "Üyelik" sekmesine geçin.')) ?></p>
            </div>
            <div class="card">
                <span class="badge badge--info">2</span>
                <h3 class="mt-2"><?= e(__('Ad, e-posta, şifre')) ?></h3>
                <p class="muted mb-0"><?= e(__('Yalnızca üç alan yeterli. Telefon ve WhatsApp alanları isteğe bağlıdır.')) ?></p>
            </div>
            <div class="card">
                <span class="badge badge--info">3</span>
                <h3 class="mt-2"><?= e(__('Aracını kaydet')) ?></h3>
                <p class="muted mb-0"><?= e(__('Panele girin, aracınızı ve evraklarını ekleyin. Hatırlatmalar otomatik başlar.')) ?></p>
            </div>
        </div>
        <div class="center mt-3">
            <a class="btn btn-accent" href="<?= e(url('/kayit')) ?>"><?= e(__('Ücretsiz hesap aç')) ?></a>
        </div>
    </div>
</section>

<?php if (!empty($plans)): ?>
<section class="section">
    <div class="container">
        <div class="section-head"><h2><?= e(t('nav.pricing')) ?></h2></div>
        <div class="grid grid-2 pricing-plans">
            <?php foreach ($plans as $index => $plan): ?>
                <?php
                $labels = json_decode((string) $plan['features_json'], true);
                $names = $labels['labels'] ?? [];
                $bullets = $labels['bullets'] ?? [];
                $life = \App\Services\CurrencyService::planPriceFor($plan, 'lifetime');
                $monthly = \App\Services\CurrencyService::planPriceFor($plan, 'month');
                $planPrice = $life ?? $monthly;
                $isFeatured = (string) $plan['key'] === 'standard';
                ?>
                <div class="card plan <?= $isFeatured ? 'plan--featured' : '' ?>">
                    <?php if ($isFeatured): ?><span class="badge badge--ok"><?= e(__('En çok tercih edilen')) ?></span><?php endif; ?>
                    <h3 class="mb-0"><?= e((string) ($names[\App\Core\Translator::locale()] ?? $plan['name'])) ?></h3>
                    <div class="plan__price">
                        <?= $planPrice !== null ? e($planPrice) : e(__('Ücretsiz')) ?>
                        <?php if ($life !== null): ?>
                            <small>/ <?= e(__('tek seferlik')) ?></small>
                        <?php elseif ($monthly !== null): ?>
                            <small>/ <?= e(t('plans.per_month')) ?></small>
                        <?php else: ?>
                            <small>/ <?= e(__('6 ay')) ?></small>
                        <?php endif; ?>
                    </div>
                    <ul>
                        <?php if ($bullets !== []): ?>
                            <?php foreach (array_slice($bullets, 0, 5) as $bullet): ?>
                                <li><?= e(__((string) $bullet)) ?></li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li><?= (int) $plan['max_vehicles'] >= 250 ? e(__('Sınırsız araç')) : e(__(':count araç', ['count' => (int) $plan['max_vehicles']])) ?></li>
                            <li><?= e(__('Tüm evraklar ücretsiz')) ?></li>
                        <?php endif; ?>
                    </ul>
                    <a class="btn <?= $isFeatured ? 'btn-accent' : 'btn-outline' ?> btn-block mt-2" href="<?= e(url('/fiyatlar')) ?>"><?= e($isFeatured ? 'Paketi incele' : 'Ücretsiz başla') ?></a>
                </div>
            <?php endforeach; ?>
        </div>
        <?php $currencyNote = CurrencyService::note(); ?>
        <?php if ($currencyNote !== ''): ?>
            <p class="center muted mt-3" style="font-size:13px"><?= e($currencyNote) ?></p>
        <?php endif; ?>
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
            <h2><?= e(__('Evrak bitişini bir daha kaçırmayın')) ?></h2>
            <p><?= e(__('Aracınızı kaydedin, hatırlatmayı bize bırakın.')) ?></p>
            <div class="row" style="justify-content:center">
                <a class="btn btn-accent" href="<?= e(url('/kayit')) ?>"><?= e(t('auth.register')) ?></a>
                <a class="btn btn-light" href="<?= e(url('/fiyatlar')) ?>"><?= e(t('nav.pricing')) ?></a>
            </div>
        </div>
    </div>
</section>
