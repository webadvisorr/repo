<?php use App\Core\Settings; ?>
<section class="hero">
    <div class="container">
        <h1><?= e('Sistem nasıl çalışır?') ?></h1>
        <p class="lead"><?= e('Araç evraklarınızı bir kez kaydedin; sistem süresi dolmadan sizi uyarır.') ?></p>
    </div>
</section>

<section class="section--tight">
    <div class="container">
        <div class="steps">
            <div class="step"><h3><?= e('Aracını ekle') ?></h3><p><?= e('Araç türünü listeden seçin, plaka ve marka bilgisini girin.') ?></p></div>
            <div class="step"><h3><?= e('Evrak tarihlerini gir') ?></h3><p><?= e('Muayene, sigorta, yol vergisi gibi her evrakın bitiş tarihini kaydedin.') ?></p></div>
            <div class="step"><h3><?= e('Hatırlatma günlerini seç') ?></h3><p><?= e('Varsayılan 7 / 3 / 1 gün. Dilerseniz 30/14 gibi kendi aralıklarınızı belirleyin.') ?></p></div>
            <div class="step"><h3><?= e('Uyarıyı al') ?></h3><p><?= e('E-posta, SMS, WhatsApp veya Telegram ile hatırlatma gelir; panelde de görünür.') ?></p></div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="split">
            <div>
                <h2><?= e('Evraklarınız tek ekranda') ?></h2>
                <ul class="list-check">
                    <li><?= e('Muayene (emüasyon) bitiş tarihi') ?></li>
                    <li><?= e('Trafik sigortası ve kasko') ?></li>
                    <li><?= e('Yol vergisi / motorlu araçlar vergisi') ?></li>
                    <li><?= e('Ehliyet yenileme ve periyodik bakım') ?></li>
                    <li><?= e('İşletme / kiralama izinleri') ?></li>
                </ul>
            </div>
            <div class="mock" aria-hidden="true">
                <div class="mock__bar"><i></i><i></i><i></i></div>
                <div class="mock__body">
                    <div class="mock__row"><span class="mock__plate">ABC 123</span><span class="muted">Muayene</span><span class="badge badge--warn">18 gün</span></div>
                    <div class="mock__row"><span class="mock__plate">ABC 123</span><span class="muted">Sigorta</span><span class="badge badge--danger">3 gün</span></div>
                    <div class="mock__row"><span class="mock__plate">KLM 456</span><span class="muted">Yol vergisi</span><span class="badge badge--ok">6 ay</span></div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section" style="background: color-mix(in srgb, var(--c-primary) 4%, transparent)">
    <div class="container">
        <div class="section-head"><h2><?= e('Üyelik nasıl olunuyor?') ?></h2></div>
        <div class="grid grid-2">
            <div class="card">
                <h3><?= e('1. Yan panelden kayıt') ?></h3>
                <p class="muted"><?= e('Sağ üstteki "Giriş / Üyelik" butonuna basın. Panel sağdan kayar; üstteki sekmelerden "Üyelik" sekmesini seçin.') ?></p>
            </div>
            <div class="card">
                <h3><?= e('2. Üç alanı doldurun') ?></h3>
                <p class="muted"><?= e('Ad soyad, e-posta ve şifre yeterlidir. Telefon/WhatsApp isteğe bağlıdır; hatırlatmayı o kanaldan da alabilirsiniz.') ?></p>
            </div>
            <div class="card">
                <h3><?= e('3. Aracınızı kaydedin') ?></h3>
                <p class="muted"><?= e('Panele giriş yaptıktan sonra "Araç ekle" ile plakanızı ve evraklarınızı girin.') ?></p>
            </div>
            <div class="card">
                <h3><?= e('4. Hatırlatmalar başlar') ?></h3>
                <p class="muted"><?= e('Evrak bitişine yaklaştığında sistem otomatik bilgilendirir. Hiçbir ek işlem yapmanız gerekmez.') ?></p>
            </div>
        </div>
        <div class="center mt-3"><button class="btn btn-accent" data-open-drawer="register"><?= e('Ücretsiz hesap aç') ?></button></div>
    </div>
</section>
