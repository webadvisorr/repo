<?php use App\Core\Settings; ?>
<section class="hero">
    <div class="container">
        <h1><?= e(__('Sistem nasıl çalışır?')) ?></h1>
        <p class="lead"><?= e(__('Araç evraklarınızı bir kez kaydedin; sistem süresi dolmadan sizi uyarır.')) ?></p>
    </div>
</section>

<section class="section--tight">
    <div class="container">
        <div class="steps">
            <div class="step"><h3><?= e(__('Aracını ekle')) ?></h3><p><?= e(__('Araç türünü listeden seçin, plaka ve marka bilgisini girin.')) ?></p></div>
            <div class="step"><h3><?= e(__('Evrak tarihlerini gir')) ?></h3><p><?= e(__('Muayene, sigorta, seyrüsefer gibi her evrakın bitiş tarihini kaydedin.')) ?></p></div>
            <div class="step"><h3><?= e(__('Hatırlatmayı sistem ayarlar')) ?></h3><p><?= e(__('Evrakın bitiş tarihini girmeniz yeterli; 7, 3 ve 1 gün kala uyarı otomatik gönderilir. Ek bir ayar yapmanıza gerek yok.')) ?></p></div>
            <div class="step"><h3><?= e(__('Uyarıyı al')) ?></h3><p><?= e(__('E-posta, SMS, WhatsApp veya Telegram ile hatırlatma gelir; panelde de görünür.')) ?></p></div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="split">
            <div>
                <h2><?= e(__('Evraklarınız tek ekranda')) ?></h2>
                <ul class="list-check">
                    <li><?= e(__('Muayene bitiş tarihi')) ?></li>
                    <li><?= e(__('Trafik sigortası ve kasko')) ?></li>
                    <li><?= e(__('Seyrüsefer / motorlu araçlar vergisi')) ?></li>
                    <li><?= e(__('Ehliyet yenileme ve periyodik bakım')) ?></li>
                </ul>
            </div>
            <div class="mock" aria-hidden="true">
                <div class="mock__bar"><i></i><i></i><i></i></div>
                <div class="mock__body">
                    <div class="mock__row"><span class="mock__plate">AA 123 A</span><span class="muted">Muayene</span><span class="badge badge--warn">18 gün</span></div>
                    <div class="mock__row"><span class="mock__plate">AB 456 B</span><span class="muted">Sigorta</span><span class="badge badge--danger">3 gün</span></div>
                    <div class="mock__row"><span class="mock__plate">KC 789 C</span><span class="muted">Seyrüsefer</span><span class="badge badge--ok">6 ay</span></div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section" style="background: color-mix(in srgb, var(--c-primary) 4%, transparent)">
    <div class="container">
        <div class="section-head"><h2><?= e(__('Üyelik nasıl olunuyor?')) ?></h2></div>
        <div class="grid grid-2">
            <div class="card">
                <h3><?= e(__('1. Yan panelden kayıt')) ?></h3>
                <p class="muted"><?= e(__('Sağ üstteki "Giriş / Üyelik" butonuna basın. Panel sağdan kayar; üstteki sekmelerden "Üyelik" sekmesini seçin.')) ?></p>
            </div>
            <div class="card">
                <h3><?= e(__('2. Üç alanı doldurun')) ?></h3>
                <p class="muted"><?= e(__('Ad soyad, e-posta ve şifre yeterlidir. Telefon/WhatsApp isteğe bağlıdır; hatırlatmayı o kanaldan da alabilirsiniz.')) ?></p>
            </div>
            <div class="card">
                <h3><?= e(__('3. Aracınızı kaydedin')) ?></h3>
                <p class="muted"><?= e(__('Panele giriş yaptıktan sonra "Araç ekle" ile plakanızı ve evraklarınızı girin.')) ?></p>
            </div>
            <div class="card">
                <h3><?= e(__('4. Hatırlatmalar başlar')) ?></h3>
                <p class="muted"><?= e(__('Evrak bitişine yaklaştığında sistem otomatik bilgilendirir. Hiçbir ek işlem yapmanız gerekmez.')) ?></p>
            </div>
        </div>
        <div class="center mt-3"><button class="btn btn-accent" data-open-drawer="register"><?= e(__('Ücretsiz hesap aç')) ?></button></div>
    </div>
</section>
