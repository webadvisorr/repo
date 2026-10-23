<?php
use App\Core\Settings;
$brand = (string) Settings::get('brand.title', 'Evrako');
$email = (string) Settings::get('brand.email', '');
$updated = '14.10.2026';
?>
<section class="hero">
    <div class="container">
        <span class="badge badge--info"><?= e(__('Kullanım Sözleşmesi')) ?></span>
        <h1 class="mt-2"><?= e(t('footer.terms')) ?></h1>
        <p class="lead"><?= e(__(':brand hizmetini kullanmadan önce lütfen aşağıdaki koşulları okuyun. Hesap oluşturduğunuzda bu koşulları kabul etmiş sayılırsınız.', ['brand' => $brand])) ?></p>
        <p class="muted mb-0" style="font-size:13.5px"><?= e(__('Son güncelleme: ') . $updated) ?></p>
    </div>
</section>

<section class="section--tight">
    <div class="container">
        <div class="split">
            <div class="stack">
                <div class="card">
                    <h2 style="font-size:1.15rem"><?= e(__('1. Hizmetin tanımı')) ?></h2>
                    <p class="mb-0"><?= e(__('Hizmet; araçlara ait muayene, sigorta, seyrüsefer gibi evrakların bitiş tarihlerini kaydetmenizi, takip etmenizi ve bitişten belirli gün önce hatırlatma almanızı sağlayan bulut tabanlı bir takip sistemidir.')) ?></p>
                </div>

                <div class="card">
                    <h2 style="font-size:1.15rem"><?= e(__('2. Hesap ve güvenlik')) ?></h2>
                    <ul class="list-check">
                        <li><?= e(__('Kayıt sırasında doğru ve güncel bilgi vermekle yükümlüsünüz.')) ?></li>
                        <li><?= e(__('Şifrenizin gizliliğinden ve hesabınız üzerinden yapılan işlemlerden siz sorumlusunuz.')) ?></li>
                        <li><?= e(__('Yetkisiz kullanım şüphesinde durumu derhal bize bildirmelisiniz.')) ?></li>
                        <li><?= e(__('Hesabınızı başkasına devredemez, ticari olarak yeniden satamazsınız.')) ?></li>
                    </ul>
                </div>

                <div class="card">
                    <h2 style="font-size:1.15rem"><?= e(__('3. Kullanıcı yükümlülükleri')) ?></h2>
                    <ul class="list-check">
                        <li><?= e(__('Yalnızca yetkili olduğunuz araçların bilgilerini girmelisiniz.')) ?></li>
                        <li><?= e(__('Yüklediğiniz belgelerin hukuka uygun olduğundan siz sorumlusunuz.')) ?></li>
                        <li><?= e(__('Sistemi bozacak, otomatik aşırı yük bindirecek veya başka kullanıcılara zarar verecek davranışta bulunamazsınız.')) ?></li>
                        <li><?= e(__('Girdiğiniz verilerin doğruluğu hatırlatma kalitesini belirler.')) ?></li>
                    </ul>
                </div>

                <div class="card">
                    <h2 style="font-size:1.15rem"><?= e(__('4. Hatırlatmalar ve sorumluluk sınırı')) ?></h2>
                    <p><?= e(__('Hatırlatmalar bilgilendirme amaçlıdır ve girdiğiniz tarihlere dayanır. Bildirimlerin zamanında iletilmesi için makul çaba gösterilir; ancak e-posta/SMS operatörü, spam filtresi veya internet kesintisi gibi dış etkenlerden kaynaklanan gecikmelerden dolayı doğacak zararlardan hizmet sağlayıcı sorumlu tutulamaz.')) ?></p>
                    <p class="mb-0"><?= e(__('Resmî işlemlerinizden önce evrak tarihlerini kendiniz de teyit etmelisiniz.')) ?></p>
                </div>

                <div class="card">
                    <h2 style="font-size:1.15rem"><?= e(__('5. Ücretlendirme ve iptal')) ?></h2>
                    <p><?= e(__('Ücretsiz plan sınırlı sayıda araç ve kanal içerir. Ücretli planlar panelde belirtilen tutar ve dönem üzerinden faturalandırılır. Havale/EFT ödemeleri onaylandığında kredi veya aboneliğiniz hesabınıza tanımlanır.')) ?></p>
                    <p class="mb-0"><?= e(__('Aboneliğinizi dilediğiniz an iptal edebilirsiniz; iptal, mevcut dönemin sonunda geçerli olur. Kullanılmamış dönem ücreti iade edilmez.')) ?></p>
                </div>

                <div class="card">
                    <h2 style="font-size:1.15rem"><?= e(__('6. Fikri mülkiyet')) ?></h2>
                    <p class="mb-0"><?= e(__('Arayüz, yazılım ve marka unsurları hizmet sağlayıcıya aittir. Sizin yüklediğiniz belgeler ve girdiğiniz veriler size aittir; hizmeti sunmak için gerekli teknik işleme dışında kullanılmaz.')) ?></p>
                </div>

                <div class="card">
                    <h2 style="font-size:1.15rem"><?= e(__('7. Hizmet sürekliliği ve fesih')) ?></h2>
                    <p class="mb-0"><?= e(__('Bakım, güncelleme veya teknik zorunluluk hâlinde hizmete kısa süreli ara verilebilir. Koşullara aykırı kullanım tespit edilirse hesabınız askıya alınabilir. Hesabınızı kapatmak istediğinizde verilerinizin silinmesini talep edebilirsiniz.')) ?></p>
                </div>

                <div class="card">
                    <h2 style="font-size:1.15rem"><?= e(__('8. Uygulanacak hukuk ve değişiklikler')) ?></h2>
                    <p class="mb-0"><?= e(__('Bu koşullar KKTC hukukuna tabidir. Koşullarda yapılacak önemli değişiklikler yayına alındığı tarihte geçerli olur; siteyi kullanmaya devam etmeniz güncel koşulları kabul ettiğiniz anlamına gelir.')) ?></p>
                </div>
            </div>

            <div class="stack">
                <div class="card card--hover">
                    <h3><?= e(__('Kısa özet')) ?></h3>
                    <ul class="list-check mb-0">
                        <li><?= e(__('Doğru bilgi girin, hesabınızı koruyun.')) ?></li>
                        <li><?= e(__('Hatırlatmalar yardımcıdır, resmî teyit sizin sorumluluğunuzdadır.')) ?></li>
                        <li><?= e(__('Verileriniz size ait.')) ?></li>
                        <li><?= e(__('İstediğiniz an iptal edebilirsiniz.')) ?></li>
                    </ul>
                </div>
                <div class="card card--hover">
                    <h3><?= e(__('İlgili sayfalar')) ?></h3>
                    <ul class="mb-0">
                        <li><a href="<?= e(url('/gizlilik')) ?>"><?= e(t('footer.privacy')) ?></a></li>
                        <?php if ($email !== ''): ?><li><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li><?php endif; ?>
                    </ul>
                </div>
                <div class="cta-band">
                    <h2 style="font-size:1.25rem"><?= e(__('Koşulları kabul ediyor musunuz?')) ?></h2>
                    <p><?= e(__('Ücretsiz hesapla hemen başlayın.')) ?></p>
                    <a class="btn btn-accent" href="<?= e(url('/kayit')) ?>"><?= e(t('auth.register')) ?></a>
                </div>
            </div>
        </div>
    </div>
</section>
