<?php
use App\Core\Settings;
$brand = (string) Settings::get('brand.title', 'Evrako');
$email = (string) Settings::get('brand.email', '');
$address = trim((string) Settings::get('brand.address', '') . ' ' . (string) Settings::get('brand.city', ''));
$updated = '14.10.2026';
?>
<section class="hero">
    <div class="container">
        <span class="badge badge--info"><?= e(__('KVKK & Gizlilik')) ?></span>
        <h1 class="mt-2"><?= e(t('footer.privacy')) ?></h1>
        <p class="lead"><?= e(__('Kişisel verilerinizi hangi amaçla topladığımızı, nasıl koruduğumuzu ve haklarınızı bu sayfada açıkça bulabilirsiniz.')) ?></p>
        <p class="muted mb-0" style="font-size:13.5px"><?= e(__('Son güncelleme: ') . $updated) ?></p>
    </div>
</section>

<section class="section--tight">
    <div class="container">
        <div class="split">
            <div class="stack">
                <div class="card">
                    <h2 style="font-size:1.15rem"><?= e(__('1. Veri sorumlusu')) ?></h2>
                    <p class="mb-0"><?= e(__(':brand, hizmetlerini sunabilmek için sınırlı sayıda kişisel veri işler. Sorularınız için:', ['brand' => $brand])) ?></p>
                    <?php if ($email !== ''): ?><p class="mb-0"><strong>E-posta:</strong> <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></p><?php endif; ?>
                    <?php if ($address !== ''): ?><p class="mb-0"><strong>Adres:</strong> <?= e($address) ?></p><?php endif; ?>
                </div>

                <div class="card">
                    <h2 style="font-size:1.15rem"><?= e(__('2. İşlediğimiz veriler')) ?></h2>
                    <ul class="list-check">
                        <li><?= e(__('Hesap bilgileri: ad soyad, e-posta, telefon (isteğe bağlı), şifre (geri döndürülemez biçimde hash\'lenir).')) ?></li>
                        <li><?= e(__('Araç ve evrak bilgileri: plaka, araç türü, marka/model, evrak türü, başlangıç/bitiş tarihleri, poliçe numarası.')) ?></li>
                        <li><?= e(__('Yüklediğiniz belgeler: ruhsat, poliçe fotoğrafları veya PDF dosyaları.')) ?></li>
                        <li><?= e(__('Ödeme bilgileri: havale/EFT bildirimleri ve faturalandırma kayıtları (kart bilgisi saklanmaz).')) ?></li>
                        <li><?= e(__('Teknik kayıtlar: IP adresi (hash\'lenmiş), oturum çerezleri ve güvenlik günlükleri.')) ?></li>
                    </ul>
                </div>

                <div class="card">
                    <h2 style="font-size:1.15rem"><?= e(__('3. İşleme amaçları ve hukuki dayanak')) ?></h2>
                    <p><?= e(__('Verileriniz; üyelik sözleşmesinin kurulması ve ifası, evrak bitiş tarihlerinde hatırlatma göndermek, destek taleplerini yanıtlamak, kötüye kullanım ve dolandırıcılığı önlemek ve yasal yükümlülükleri yerine getirmek amaçlarıyla işlenir.')) ?></p>
                    <p class="mb-0"><?= e(__('Ticari ileti gönderimi yalnızca açık rızanız (kayıt sırasındaki onay kutusu) varsa yapılır; rızanızı dilediğiniz an geri alabilirsiniz.')) ?></p>
                </div>

                <div class="card">
                    <h2 style="font-size:1.15rem"><?= e(__('4. Hatırlatma bildirimleri')) ?></h2>
                    <p class="mb-0"><?= e(__('Evrak bitiş tarihlerinde sistem varsayılan olarak 7, 3 ve 1 gün önce e-posta ile hatırlatma gönderir. SMS, WhatsApp ve Telegram kanalları yalnızca siz etkinleştirdiğinizde kullanılır. Bildirim tercihlerinizi panelden dilediğiniz an değiştirebilir veya kapatabilirsiniz.')) ?></p>
                </div>

                <div class="card">
                    <h2 style="font-size:1.15rem"><?= e(__('5. Çerezler ve ölçümleme')) ?></h2>
                    <p><?= e(__('Sitenin çalışması için zorunlu olan oturum ve güvenlik çerezleri kullanılır (örn. giriş durumu, dil seçimi, tema). Zorunlu olmayan analitik çerezler yalnızca çerez bandından onay verdiğinizde etkinleşir.')) ?></p>
                    <p class="mb-0"><?= e(__('Çerez tercihinizi tarayıcı ayarlarınızdan da temizleyebilirsiniz; bu durumda bant yeniden görünür.')) ?></p>
                </div>

                <div class="card">
                    <h2 style="font-size:1.15rem"><?= e(__('6. Saklama ve aktarım')) ?></h2>
                    <p><?= e(__('Verileriniz hizmetin sunulduğu veri merkezlerinde saklanır. Üçüncü taraf sağlayıcılar (e-posta gönderim servisi, barındırma) yalnızca hizmetin gerektirdiği ölçüde veriye erişir ve sözleşmeyle gizliliğe bağlıdır.')) ?></p>
                    <p class="mb-0"><?= e(__('Ticari defterlerin saklanması gibi yasal zorunluluklar dışında veriler, hesabınızı kapatmanızdan sonra en fazla 30 gün içinde silinir.')) ?></p>
                </div>

                <div class="card">
                    <h2 style="font-size:1.15rem"><?= e(__('7. Haklarınız')) ?></h2>
                    <ul class="list-check">
                        <li><?= e(__('İşlenen verileriniz hakkında bilgi talep etme')) ?></li>
                        <li><?= e(__('Eksik veya yanlış verilerin düzeltilmesini isteme')) ?></li>
                        <li><?= e(__('Verilerinizin silinmesini veya anonimleştirilmesini isteme')) ?></li>
                        <li><?= e(__('Ticari ileti onayını geri alma')) ?></li>
                        <li><?= e(__('Verilerinizin taşınabilir bir kopyasını isteme')) ?></li>
                    </ul>
                    <p class="mb-0"><?= e(__('Talebinizi panelden veya iletişim formundan iletebilirsiniz; en geç 30 gün içinde yanıtlanır.')) ?></p>
                </div>

                <div class="card">
                    <h2 style="font-size:1.15rem"><?= e(__('8. Güvenlik')) ?></h2>
                    <p class="mb-0"><?= e(__('Şifreler güçlü algoritmalarla hash\'lenir, tüm form gönderimleri CSRF korumalıdır, dosyalar kod çalıştırılamayacak biçimde sunulur ve erişimler kayıt altına alınır.')) ?></p>
                </div>
            </div>

            <div class="stack">
                <div class="card card--hover">
                    <h3><?= e(__('Özetle')) ?></h3>
                    <ul class="list-check mb-0">
                        <li><?= e(__('Verinizi satmıyoruz.')) ?></li>
                        <li><?= e(__('Yalnızca hatırlatma ve hizmet için kullanıyoruz.')) ?></li>
                        <li><?= e(__('Dilediğiniz an silinmesini talep edebilirsiniz.')) ?></li>
                        <li><?= e(__('Analitik çerezler onayınıza bağlı.')) ?></li>
                    </ul>
                </div>
                <div class="card card--hover">
                    <h3><?= e(__('İlgili sayfalar')) ?></h3>
                    <ul class="mb-0">
                        <li><a href="<?= e(url('/kullanim-kosullari')) ?>"><?= e(t('footer.terms')) ?></a></li>
                        <li><a href="<?= e(url('/iletisim')) ?>"><?= e(t('nav.contact')) ?></a></li>
                    </ul>
                </div>
                <div class="cta-band">
                    <h2 style="font-size:1.25rem"><?= e(__('Sorunuz mu var?')) ?></h2>
                    <p><?= e(__('Veri işleme hakkında bize yazın.')) ?></p>
                    <a class="btn btn-accent" href="<?= e(url('/iletisim')) ?>"><?= e(t('nav.contact')) ?></a>
                </div>
            </div>
        </div>
    </div>
</section>
