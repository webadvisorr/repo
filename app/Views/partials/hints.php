<?php
/**
 * Panel bilgi balonları.
 * Her sayfanın ana başlığının yanına, o ekranın ne işe yaradığını anlatan
 * küçük bir "i" düğmesi ekler. İçerik yol (path) eşleşmesiyle seçilir; yeni
 * ekranlar için yalnızca bu dosyaya bir kayıt eklemek yeterlidir.
 */

use App\Core\Security;

$path = current_path();
$isAdmin = str_starts_with($path, '/yonetim');
$isPanel = str_starts_with($path, '/panel');
if (!$isAdmin && !$isPanel) {
    return;
}
?>
<style>
    .info-tip { position: relative; display: inline-flex; vertical-align: middle; margin-inline-start: 8px; }
    .info-tip__dot { width: 21px; height: 21px; border-radius: 99px; border: 1.5px solid color-mix(in srgb, var(--c-secondary) 55%, var(--c-border)); background: color-mix(in srgb, var(--c-secondary) 10%, transparent); color: var(--c-secondary); font: 700 12px/1 var(--font-head); display: grid; place-items: center; cursor: help; padding: 0; }
    .info-tip__dot:hover, .info-tip__dot:focus-visible { background: var(--c-secondary); color: #fff; outline: none; }
    .info-tip__bubble { position: absolute; z-index: 160; top: calc(100% + 8px); inset-inline-start: 0; width: max-content; max-width: min(320px, calc(100vw - 40px)); padding: 10px 13px; border-radius: 11px; background: var(--c-primary); color: #fff; font-size: 12.8px; font-weight: 500; line-height: 1.5; box-shadow: var(--shadow-lg); opacity: 0; visibility: hidden; transform: translateY(-4px); transition: opacity .16s ease, transform .16s ease, visibility .16s; pointer-events: none; }
    .info-tip__bubble strong { display: block; font-family: var(--font-head); font-size: 13px; margin-bottom: 3px; color: var(--c-accent); }
    .info-tip__bubble::before { content: ""; position: absolute; top: -5px; inset-inline-start: 7px; width: 10px; height: 10px; background: var(--c-primary); transform: rotate(45deg); border-radius: 2px; }
    .info-tip:hover .info-tip__bubble,
    .info-tip:focus-within .info-tip__bubble,
    .info-tip.is-open .info-tip__bubble { opacity: 1; visibility: visible; transform: none; }
    @media (max-width: 600px) { .info-tip__bubble { max-width: calc(100vw - 32px); } }
</style>
<script nonce="<?= e(Security::nonce()) ?>">
(function () {
    var isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
    var hints = {
        '/yonetim/slider/': ['Slider düzenleme', 'Başlık, alt başlık, açıklama, görsel, buton yazısı ve bağlantısını burada değiştirin. Boş bırakılan alanlar sitede gizlenir.'],
        '/yonetim/slider': ['Slider yönetimi', 'Ana sayfanın üst bölümündeki slaytları yönetir. Aktif slayt yoksa sitenin varsayılan metinli slaytları gösterilir.'],
        '/yonetim/ayarlar/slider': ['Slider ayarları', 'Otomatik geçiş süresini ve Ken Burns (yavaş yakınlaşma) efektini yönetir. Slayt metin ve görsellerini “Slider Yönetimi” ekranından düzenleyin.'],
        '/yonetim/ayarlar/site_assistant': ['Site asistanı', 'Ziyaretçilerle ilgilenen Gemini yapay zekâ asistanını yönetir. Anahtar girilip açıldığında site asistanı sol altta görünür.'],
        '/yonetim/ayarlar/assistant': ['Panel rehberi', 'Yönetim panelinin kullanımını anlatan yardımcı asistanı yapılandırır. API anahtarı boşken yerel, ücretsiz rehber modu çalışır.'],
        '/yonetim/ayarlar/mail': ['E-posta ayarları', 'SMTP sunucu bilgilerini ve gönderen adresini tanımlar. Buradaki test düğmesiyle gerçek gönderim yapabilirsiniz.'],
        '/yonetim/ayarlar/reminders': ['Hatırlatma ayarları', 'Evrak süresi dolmadan kaç gün önce uyarı gönderileceğini ve günlük gönderim sınırlarını belirler.'],
        '/yonetim/ayarlar/payment': ['Ödeme ayarları', 'Para birimi, banka hesapları ve kurları yönetir. Havale/EFT ile ödeme talimatları bu bilgilerden üretilir.'],
        '/yonetim/ayarlar/theme': ['Tema ayarları', 'Site renklerini açık ve koyu tema için ayrı ayrı düzenler. Değişiklikler tüm sayfalara uygulanır.'],
        '/yonetim/ayarlar/seo': ['SEO ayarları', 'Arama motorlarında görünen başlık, açıklama ve paylaşım görselini belirler.'],
        '/yonetim/ayarlar': ['Site ayarları', 'Marka kimliği, iletişim bilgileri, e-posta, tema ve modül ayarlarını sekmeler halinde yönetir.'],
        '/yonetim/uyeler': ['Üye yönetimi', 'Kayıtlı müşterileri, e-posta doğrulama durumlarını ve üyeliklerini görüntüler; plan atar veya hesabı askıya alırsınız.'],
        '/yonetim/araclar': ['Araç yönetimi', 'Tüm müşterilerin araçlarını listeler. Araç detayından evrakları ve hatırlatma geçmişini inceleyebilirsiniz.'],
        '/yonetim/evraklar': ['Evrak yönetimi', 'Sisteme girilen muayene, sigorta, kasko ve diğer evrakları listeler; süresi yaklaşanları takip eder.'],
        '/yonetim/planlar': ['Plan yönetimi', 'Paket fiyatlarını, deneme süresini ve limitleri düzenler. Ek araç indirimleri fiyat sayfasındaki kademelere göre uygulanır.'],
        '/yonetim/odemeler': ['Ödemeler', 'Havale/EFT bildirimlerini ve kredi yüklemelerini onaylar. Onaylanan ödeme müşterinin bakiyesine veya aboneliğine işlenir.'],
        '/yonetim/mesajlar': ['Mesajlar', 'İletişim formundan gelen talepleri görüntüler; yanıt durumunu ve okundu bilgisini yönetir.'],
        '/yonetim/sablonlar': ['E-posta şablonları', 'Hatırlatma ve doğrulama e-postalarının konusunu ve HTML içeriğini düzenler. Değişkenler süslü parantezle yazılır.'],
        '/yonetim/kampanya': ['Kampanyalar', 'Toplu e-posta kampanyası oluşturur, hedef kitleyi seçer ve gönderim sonuçlarını raporlar.'],
        '/yonetim/duyurular/yeni': ['Yeni bildirim', 'Müşteri panelindeki zile resim, metin ve buton bağlantısı içeren özel bildirim gönderir. E-posta göndermez; anında panelde görünür.'],
        '/yonetim/duyurular': ['Bildirim', 'Panelden gönderilen özel bildirimleri, hedef kitleyi ve okunma oranlarını listeler. Hatırlatmalar da kullanıcı zilinde görünür.'],
        '/yonetim/bildirimler': ['E-posta Günlüğü', 'Gönderim kuyruğunu, başarısız hatırlatmaları ve yeniden gönderim işlemlerini yönetir.'],
        '/yonetim/destek': ['Destek talepleri', 'Müşteri panelinden açılan talepleri listeler; konuşmayı görür, yanıt yazar ve talebi kapatırsınız. Tarih aralığına göre filtreleyebilirsiniz.'],
        '/yonetim/arama': ['Hızlı arama', 'Müşteriyi ad, e-posta veya telefonla bulur; sonuçtan üye kaydına, plakadan araç kaydına gidersiniz.'],
        '/panel/destek': ['Destek taleplerim', 'Destek talebi açın, yönetim yanıtlarını okuyun ve aynı konuşmadan yanıt yazın. Kapattığınız talebe yeni mesaj yazılamaz.'],
        '/panel/mesajlar': ['Mesajlarım', 'İletişim formundan gönderdiğiniz mesajları ve yönetim yanıtlarını burada takip edersiniz.'],
        '/yonetim/denetim': ['Denetim kaydı', 'Panelde yapılan tüm kritik işlemleri kim, ne zaman ve hangi veriyle yaptı bilgisiyle listeler.'],
        '/yonetim/sistem': ['Sistem durumu', 'Veritabanı, disk, cron ve e-posta servislerinin sağlığını gösterir; bakım işlemlerini buradan çalıştırırsınız.'],
        '/yonetim/rehber': ['Kullanım rehberi', 'Panelin tüm ekranlarını adım adım anlatan kılavuz. Sağ alttaki “Rehber” düğmesinden de soru sorabilirsiniz.'],
        '/yonetim': ['Yönetim paneli', 'Sitenin genel durumunu özetler: yeni üyeler, yaklaşan evraklar, ödeme talepleri ve son işlemler.'],
        '/yonetim/': ['Yönetim paneli', 'Sitenin genel durumunu özetler: yeni üyeler, yaklaşan evraklar, ödeme talepleri ve son işlemler.'],
        '/panel/araclar': ['Araçlarım', 'Araçlarınızı ve her aracın evrak durumunu gösterir. Trafik ışığı rengi yaklaşan veya geciken evrağı belirtir.'],
        '/panel/odeme': ['Ödeme merkezi', 'Paket satın alır, kredi yükler ve bekleyen havale taleplerinizi görürsünüz.'],
        '/panel/odemelerim': ['Ödeme geçmişim', 'Geçmiş ödemelerinizi, kredi hareketlerinizi ve fatura bilgilerinizi listeler.'],
        '/panel/ayarlar': ['Hesap ayarları', 'Ad, e-posta ve şifre bilgilerinizi güncellersiniz.'],
        '/panel': ['Müşteri paneli', 'Araçlarınızın evrak durumunu, yaklaşan tarihleri ve bildirimlerinizi tek ekranda özetler.'],
        '/panel/': ['Panel', 'Seçili kaydın ayrıntılarını gösterir; düzenleme ve yeni kayıt işlemleri buradan yapılır.']
    };

    function pickHint(path) {
        var keys = Object.keys(hints).sort(function (a, b) { return b.length - a.length; });
        for (var i = 0; i < keys.length; i++) {
            if (path === keys[i] || path.indexOf(keys[i]) === 0) { return hints[keys[i]]; }
        }
        return null;
    }

    function build(hint, target) {
        var wrap = document.createElement('span');
        wrap.className = 'info-tip';
        var dot = document.createElement('button');
        dot.type = 'button';
        dot.className = 'info-tip__dot';
        dot.textContent = 'i';
        dot.setAttribute('aria-label', 'Bu bölüm ne işe yarar?');
        dot.setAttribute('title', hint[0]);
        var bubble = document.createElement('span');
        bubble.className = 'info-tip__bubble';
        bubble.setAttribute('role', 'tooltip');
        var strong = document.createElement('strong');
        strong.textContent = hint[0];
        bubble.appendChild(strong);
        bubble.appendChild(document.createTextNode(hint[1]));
        wrap.appendChild(dot);
        wrap.appendChild(bubble);
        dot.addEventListener('click', function (e) {
            e.preventDefault();
            var open = wrap.classList.contains('is-open');
            document.querySelectorAll('.info-tip.is-open').forEach(function (el) { el.classList.remove('is-open'); });
            if (!open) { wrap.classList.add('is-open'); }
        });
        target.appendChild(wrap);
    }

    function init() {
        var path = window.location.pathname.replace(/\/+$/, '') || '/';
        var page = pickHint(path);
        if (page) {
            var heading = document.querySelector('.admin-body h1, main h1, h1');
            if (heading && !heading.querySelector('.info-tip')) { build(page, heading); }
        }
        document.querySelectorAll('[data-hint]').forEach(function (el) {
            var parts = (el.getAttribute('data-hint') || '').split('|');
            if (parts.length === 2 && !el.querySelector('.info-tip')) { build(parts, el); }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
