# evrako — Yol Haritası ve Durum

Son güncelleme: Faz 0–1 tamamlandı.

## ✅ Faz 0 — Kararlar & Marka (tamamlandı)
- Backend: **saf PHP 8** (Laravel yok — paylaşımlı hosting/SSH yok kısıtı), MySQL, CSS/JS/HTML
- Marka adı **evrako** + logo/favicon (SVG, yazı tipi vektöre çevrildi) + renk paleti → `docs/BRAND.md`
- 6 dil: tr, en, ru, ar, fa, el (218 anahtar/dil, Arapça & Farsça RTL)

## ✅ Faz 1 — Çekirdek + Kurulum + Site Kimliği (tamamlandı)
- MySQL şeması: **27 tablo** (kullanıcı, kurum/filo, araç, evrak, bildirim, kampanya, plan, abonelik, ödeme, ayar, medya, denetim…)
- Çekirdek: Router, PDO katmanı, Auth (bcrypt + kilit), CSRF, Session, RateLimiter, Uploader, **saf PHP SMTP istemcisi**, i18n, Settings, Audit, View, Validator
- Web kurulum sihirbazı (SSH'sız): gereksinim kontrolü → DB testi → `config.php` üretimi → şema + varsayılan veriler → yönetici hesabı
- Admin panel: giriş, kontrol paneli (istatistik + denetim kaydı), **Site Kimliği CRUD**
  (logo, favicon, apple icon, og görseli, başlık, slogan, iletişim, sosyal, meta & OG, 10+10 renk, preloader, bakım modu, SMTP + test maili, kanallar, ödeme)
- Site: dark/light, yandan kayan giriş/üyelik paneli, slider, "nasıl çalışır", "üyelik nasıl olunuyor", fiyatlandırma, SSS, iletişim, üye kaydı (kurum + abonelik ataması), üye paneli
- Doğrulama: 66 PHP dosyası lint temiz; kurulum + giriş + ayar kaydetme + dosya yükleme + CSRF/403/404/419/brute-force akışları gerçek MariaDB üzerinde test edildi

## 🔜 Faz 2 — Araç & Evrak Modülü
- Araç CRUD (tür bazlı liste, plaka tekrar engeli, çoklu araç, plan limiti kontrolü)
- Evrak CRUD (tür, kurum/poliçe no, başlangıç/bitiş, ücret, hatırlatma günleri)
- Belge yükleme (ruhsat/poliçe fotoğrafı & PDF) + "yenilendi" işaretleme + tarih geçmişi
- Müşteri paneli: araç detayı, trafik ışığı durumları (yeşil/sarı/turuncu/kırmızı), takvim görünümü, PDF rapor, .ics/Google Takvim
- Kurum/filo: alt kullanıcı davet etme, yetki matrisi

## 🔜 Faz 3 — Hatırlatma Motoru & Premium Mail Şablonu
- `cron/reminders.php`: günlük tarama (7/3/1/0 gün), **idempotent gönderim** (dedupe_key ile çift mail yok), retry kuyruğu, günlük limit
- Premium responsive HTML mail şablonu (logo, durum şeridi, araç kartı, CTA, KVKK + abonelikten çık)
- Admin: mail şablon editörü (6 dil), değişkenler `{ad} {plaka} {evrak} {bitis} {kalan_gun} {link}`
- Bildirim günlüğü ekranı (gönderildi/açıldı/hata)

## 🔜 Faz 4 — Reklam/Kampanya Maili & Kanallar
- Kampanya modülü: hedef kitle filtresi, önizleme, test, planlı gönderim, istatistik
- Zorunlu abonelikten çık (unsubscribe) + double opt-in + bounce yönetimi
- SMS / WhatsApp / Telegram gönderim sürücüleri (sağlayıcı bilgileri panelden)
- SPF/DKIM/DMARC kontrol rehberi

## 🔜 Faz 5 — Abonelik & Ödeme
- Plan yönetimi, abonelik yaşam döngüsü (trial → active → past_due → cancelled), fatura/ödeme kaydı
- **iyzico** entegrasyonu (hosted Checkout Form + callback doğrulaması) — KKTC/TR için önerilen;
  alternatif PayTR. ⚠️ Sağlayıcı başvurusu ve şirket evrakları **kullanıcı tarafında** gerekir.
- Havale/EFT ile manuel ödeme akışı, admin onayı

## 🔜 Faz 6 — Gelişmiş
- Çoklu dil için sayfa bazlı meta/OG alanları (şu an tek dilde)
- Blog + SEO (sitemap.xml, robots.txt, hreflang, JSON-LD), PWA + web push
- PDF rapor, Excel/CSV dışa aktarma, araç başına maliyet takibi, grafikler
- Yedekleme (otomatik DB + dosya) ve geri yükleme testi, durum izleme

## ⚠️ Kullanıcı tarafında yapılacaklar
1. Alan adı + hosting (cPanel) + SSL
2. MySQL veritabanı oluşturma
3. Kurumsal mail **SMTP bilgileri** (sunucu, port, kullanıcı, şifre) — panele girilecek
4. cPanel cron görevi tanımı
5. iyzico/PayTR başvurusu (ödeme almak için şirket evrakları)
6. WhatsApp Business / SMS sağlayıcı hesabı (opsiyonel)
