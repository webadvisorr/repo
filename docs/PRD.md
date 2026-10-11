# Araç Evrak Takip Sistemi (Kıbrıs) — Ürün Gereksinim Dokümanı (PRD v1)

Geliştirici: webadvisor — webadvisor.com.tr
Durum: TASLAK (onay bekliyor)

---

## 1. Proje Özeti

Kıbrıs'ta araç sahiplerinin **muayene (emüasyon), sigorta, yol vergisi, kasko, servis** gibi
evraklarının bitiş tarihlerini takip eden, süresi dolmadan **otomatik mail ile hatırlatan**,
çoklu araç destekli, SaaS (abonelik) yönetimli bir web platformu.

**Problem:** İnsanlar dalgın; bir kişide birden fazla araç olabiliyor. Evrak bitiş tarihlerini
kaçırınca ceza, trafikten men, kaza anında mağduriyet oluşuyor.

**Çözüm:** Aracı bir kez kaydet → sistem 7 / 3 / 1 gün önce ve bitiş günü otomatik mail atar.
Panelde tüm araçlar trafik ışığı (yeşil/sarı/kırmızı) ile görünür.

**Değer önerisi:** "Aracının evraklarını bir daha asla kaçırma. Bir kez kaydet, gerisini biz hatırlatalım."

**Marka / isim:** Henüz yok — logo da yok. Aşağıda isim ve logo önerileri var (Bölüm 12).

---

## 2. Kullanıcı Rolleri

| Rol | Yetki |
|---|---|
| **Ziyaretçi** | Landing page, "Nasıl çalışır", üyelik anlatımı, hızlı kayıt |
| **Müşteri (üye)** | Kendi araçlarını ve evraklarını yönetir, hatırlatma ayarlarını değiştirir, mail tercihleri |
| **Kurumsal / Filo (B2B)** | Birden fazla kullanıcı ve yüzlerce araç, alt kullanıcı yetkilendirme *(önerim)* |
| **Süper Admin (biz)** | Tüm site ayarları, üyeler, araçlar, mail şablonları, gönderim günlüğü, istatistik, yedekleme |
| **Editör (opsiyonel)** | Sadece içerik (slider, blog, metinler) — sistem ayarlarına erişemez *(önerim)* |

---

## 3. Yönetim Paneli — Site Kimliği Ayarları

Her öğe için **ekle / değiştir / kaldır (CRUD)**. Değişiklikler önbellek temizlenerek anında yansır,
önceki sürüm 1 adım geri alınabilir (versiyon/backup) *(önerim)*.

| # | Öğe | Detay |
|---|---|---|
| 3.1 | **Logo** | Yükle / değiştir / kaldır. PNG, SVG, WEBP, JPG; açık & koyu tema için ayrı logo; boyut sınırı + otomatik optimize |
| 3.2 | **Favicon** | Yükle / değiştir / kaldır. ICO, PNG, SVG + apple-touch-icon, 32/180/512 px otomatik üretim |
| 3.3 | **Site başlığı + slogan** | Ekle / değiştir / kaldır, çoklu dil alanları |
| 3.4 | **Meta başlık + meta açıklama** | Sayfa bazlı (ana sayfa, hakkımızda, giriş...) + varsayılan |
| 3.5 | **Preloader** | Resim / GIF / SVG / WebP / APNG — tüm formatlar; tip seçimi: görsel veya animasyon; **arka plan rengi**; süre/gecikme; "sayfa hazır olunca gizle" |
| 3.6 | **Site rengi** | Birincil (primary), ikincil, vurgu (accent), link rengi; **dark/light için ayrı palet**; canlı önizleme; CSS değişkenlerine (custom properties) yazılır |
| 3.7 | **OpenGraph** | og:title, og:description, og:image (+ varsayılan paylaşım görseli), og:type, og:url, og:site_name, Twitter/X card. Admin panelden önizleme |
| 3.8 | *(önerim)* **Ek SEO** | robots.txt, sitemap.xml, canonical, hreflang, JSON-LD (Organization + Product), Google Analytics / Meta Pixel ID alanı |
| 3.9 | *(önerim)* **İletişim & kurumsal bilgiler** | Adres, telefon, WhatsApp, e-posta, çalışma saati, sosyal medya linkleri, KVKK/çerez metinleri |
| 3.10 | *(önerim)* **Bakım modu** | Siteyi geçici kapat + "bakımdayız" sayfası |

---

## 4. E-posta Sistemi

### 4.1 Otomatik Hatırlatma
- Motor her gün (ör. 08:00 Kıbrıs saati) tarama yapar.
- Varsayılan uyarı aralıkları: **7 gün, 3 gün, 1 gün önce + bitiş günü (0 gün)** = 4 mail.
- Kullanıcı aralıkları **kendisi değiştirebilir** (ör. 30/14/7/3/1/0) *(önerim)*.
- Aynı evrak için aynı gün **tek mail** (idempotent gönderim) — çift mail gitmez.
- Gönderim kaydı (log): hangi mail, kime, ne zaman, açıldı mı, hata mı.
- Hata olursa yeniden deneme (retry) kuyruğu *(önerim)*.
- Mail içinde: araç plakası, evrak türü, bitiş tarihi, kalan gün sayısı,
  **"Panele git / Yenile" butonu**, yapılmazsa doğacak risk hatırlatması.

### 4.2 Premium Mail Şablonu
- Şık, canlı, responsive (mobil uyumlu), koyu/açık uyumlu HTML e-posta.
- Üstte logo, renkli durum şeridi (🟡 7 gün / 🟠 3 gün / 🔴 1 gün / ⛔ süresi geçti).
- Büyük CTA butonu, araç kartı görünümü, alt bilgi (firma bilgileri, sosyal medya, KVKK, abonelikten çık).
- Admin panelden **düzenlenebilir şablon** (WYSIWYG + değişkenler: `{ad}`, `{plaka}`, `{evrak}`,
  `{bitis_tarihi}`, `{kalan_gun}`, `{link}`).

### 4.3 Reklam / Kampanya Maili
- Admin "Kampanya" oluşturur → şablon seçer → hedef kitle (tüm üyeler / filtrelenmiş / seçili) → gönderir.
- Ön izleme + test maili + **planlanmış gönderim**.
- Toplu gönderimde kuyruk ve günlük limit (sağlayıcı limitine takılmamak için).
- **Zorunlu:** "abonelikten çık (unsubscribe)" linki + kimlere gönderilmeyeceği listesi (KVKK/GDPR).

### 4.4 Teslimat Altyapısı (teknik)
- SMTP/API sağlayıcı (değerlendirme: Resend / Brevo / Postmark / Amazon SES — Bölüm 11).
- **SPF, DKIM, DMARC** kayıtları zorunlu; DMARC izleme.
- Bounce (geri dönen) ve şikâyet (spam) webhook'ları → otomatik liste temizliği.
- Abonelik: tek tıklama onay (double opt-in) — reklam maillerinde spam'e düşmemek için *(önerim)*.

---

## 5. Üyelik / Hesap

- **Kısa kayıtlı üyelik:** E-posta + şifre (+ ad soyad, telefon, açık rıza onayı). Minimum alan.
- E-posta doğrulama (aktivasyon linki).
- **Giriş / Çıkış** — müşteri hesabı; oturum yönetimi, "beni hatırla".
- Şifremi unuttum / şifre sıfırlama (tek kullanımlık, süreli token).
- *(önerim)* **Magic link** ile şifresiz giriş ve **Google ile giriş** — dönüşümü artırır.
- *(önerim)* **İki adımlı doğrulama (2FA)** — özellikle kurumsal hesaplarda.
- *(önerim)* Hesap silme / verilerimi indir (KVKK hakkı).
- *(önerim)* Deneme süresi (ör. 14 gün ücretsiz) ve ücretsiz plan (1 araç).

---

## 6. Araç (Vasıta) Yönetimi

- Araç türüne göre **açılır liste** (dropdown) → seçime göre form alanları değişir.
- Önerilen tür listesi (Kıbrıs):
  - Otomobil (binek)
  - Motosiklet / Scooter
  - Kamyonet / Pickup
  - Kamyon / Ağır vasıta
  - Minibüs / Otobüs
  - Traktör / Tarım aracı
  - ATV / Quad
  - Ticari taksi / Kiralık araç
  - Karavan / Römork
  - Tekne / Deniz aracı *(önerim — Kıbrıs'ta yaygın)*
  - Diğer
- Araç alanları: tür, marka, model, yıl, **plaka**, şasi (VIN) no, motor no, renk, km, not.
- *(önerim)* Plaka/Kıbrıs formatı doğrulaması ve **aynı plaka tekrar kaydını engelleme**.
- *(önerim)* **Birden fazla araç** (plan limitine göre: Ücretsiz 1, Pro 5, Filo 25+) ve araçları klasör/etiketle gruplama.

### 6.1 Evrak Türleri (her araç için ayrı takip)
| Evrak | Tipik geçerlilik | Not |
|---|---|---|
| Muayene / Emüasyon testi | 1–2 yıl | Yaşa göre değişir |
| Trafik sigortası (zorunlu) | 1 yıl | Zorunlu |
| Kasko | 1 yıl | Opsiyonel |
| Yol vergisi / Motorlu araçlar vergisi | 1 yıl | |
| Ehliyet yenileme | 5–10 yıl | Sürücü bazlı *(önerim)* |
| Servis / periyodik bakım | km veya tarih | *(önerim)* |
| Lastik / akü | tarih | *(önerim)* |
| Kiralama ruhsatı / işletme izni | yıllık | Ticari araç *(önerim)* |

### 6.2 Evrak Kaydı
- Evrak türü, veren kurum/şirket, başlangıç tarihi, **bitiş tarihi**, ücret, not.
- *(önerim)* **Evrak fotoğrafı / PDF yükleme** (ruhsat, poliçe arşivi) — dosyalar özel klasörde.
- *(önerim)* **Yenilendi olarak işaretle** → tek tıkla yeni tarih gir, eski kayıt geçmişe düşer.
- *(önerim)* Bitiş tarihi, evrak türüne göre otomatik öneri (ör. sigorta = +1 yıl).

---

## 7. Müşteri Paneli (Dashboard)

- Üstte özet: yaklaşan evraklar, süresi geçenler, toplam araç sayısı.
- **Renk kodlu liste:** yeşil (30+ gün), sarı (30 günden az), turuncu (7 günden az), kırmızı (süresi geçti).
- Araç detay sayfası: tüm evraklar, kalan gün sayacı, yüklü belgeler, mail geçmişi.
- *(önerim)* **Takvim görünümü** — aylık hangi araçta ne var.
- *(önerim)* **.ics / Google Takvim bağlantısı** ve **PDF rapor** (araç evrak dökümü) — müşteriye satışta güçlü.
- *(önerim)* Bildirim tercihleri: mail + WhatsApp/SMS + tarayıcı bildirimi.
- *(önerim)* Yakıt/bakım masraf kaydı → araç başına maliyet raporu (uzun vadeli değer).

---

## 8. Site Arayüzü (Public)

| # | Özellik | Detay |
|---|---|---|
| 8.1 | **Dark / Light mode** | Sistem tercihine göre otomatik + kullanıcı anahtarı; seçim çerezde saklanır; renkler admin panelindeki paletten gelir |
| 8.2 | **Yandan kayan panel (off-canvas drawer)** | Sağdan kayar; içinde **Giriş** ve **Üyelik (Kayıt)** sekmeleri; şifremi unuttum linki; mobil uyumlu |
| 8.3 | **Slider** | Çoklu slayt, otomatik geçiş, oklar + nokta göstergesi, mobil sürükleme; her slayta **başlık + açıklama + buton metni + buton linki** (hedef: aynı sekme / yeni sekme) |
| 8.4 | **"Nasıl çalışıyor?" bölümü** | İkon/görsel + metin destekli, ilgi çekici, animasyonlu 3–4 adım: Kaydet → Tarihleri gir → Sistem hatırlatsın → Hayat kolay |
| 8.5 | **"Üyelik nasıl olunuyor?" bölümü** | Görsel + metin anlatım; adım adım ekran görüntüleri; hızlı kayıt butonu |
| 8.6 | *(önerim)* Faydalar / ceza maliyeti karşılaştırması | "Kaçırdığın evrak sana kaça mal olur?" — ikna edici bölüm |
| 8.7 | *(önerim)* Fiyatlandırma tablosu | Ücretsiz / Aile / Filo planları |
| 8.8 | *(önerim)* SSS (Sıkça sorulan sorular) | Hem destek yükünü azaltır hem SEO |
| 8.9 | *(önerim)* İletişim / WhatsApp butonu | Kıbrıs'ta WhatsApp yaygın — dönüşüm için şart |
| 8.10 | *(önerim)* Çerez bildirimi + KVKK metinleri | Yasal zorunluluk |
| 8.11 | *(önerim)* Çoklu dil: TR / EN (opsiyonel GR) | Kıbrıs'ta EN gerekli |
| 8.12 | *(önerim)* Blog / içerik modülü | SEO ile ücretsiz müşteri toplama (senin uzun vadeli planın için kritik) |
| 8.13 | *(önerim)* Mail bülten aboneliği formu | Topladığın kişiler ileride reklam maili kitlesi olur |

---

## 9. Güvenlik (Saldırı ve İstismar Önlemi)

> Not: "SQL injection" gibi açıklardan korunmak için **hazırlanmış sorgular (prepared statements)** şart.

- **Şifreler:** `bcrypt` / `argon2` ile hash; düz metin asla saklanmaz.
- **SQL Injection:** tüm sorgular parameterized (prepared statement); ORM/query builder.
- **XSS:** çıktı kaçışlama (HTML escape), zengin metinlerde sanitize (DOMPurify benzeri), **CSP** başlığı.
- **CSRF:** tüm form/POST işlemlerinde token, `SameSite` çerez.
- **Oturum:** güvenli (httpOnly, secure, SameSite) çerez; oturum sabitleme (fixation) koruması; çıkışta token iptali.
- **Brute force:** giriş ve şifre sıfırlama için deneme sınırı + artan bekleme (rate limit) + CAPTCHA.
- **Dosya yükleme:** tür/uzantı/MIME doğrulama, boyut sınırı, **web kökü dışında** saklama, `php/js` gibi çalıştırılabilir dosya reddi, yeniden adlandırma.
- **Yol/parametre:** dosya adı ve ID'lerde doğrulama; **IDOR** (başka kullanıcının ID'sine erişim) engeli → her kayıt kullanıcıya bağlı sorgulanır.
- **Yetkilendirme:** her API uç noktasında rol kontrolü (müşteri yalnız kendi verisini görür).
- **Başlıklar:** Helmet benzeri — HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy.
- **HTTPS/TLS** zorunlu, otomatik yenileme; admin panelde opsiyonel IP kısıtı.
- **İki adımlı doğrulama (2FA)** — admin için zorunlu.
- **Denetim kaydı (audit log):** kim ne zaman ne değiştirdi.
- **Yedekleme:** günlük otomatik MySQL yedeği + dosya yedeği, test edilmiş **geri yükleme**.
- **Loglama & izleme:** hatalar (Sentry benzeri), şüpheli istek uyarısı, mail gönderim hatası alarmı.
- **Gizli anahtarlar:** `.env` / secret store; repoya asla girmez, panelde gösterilmez.
- **E-posta kötüye kullanımı:** gönderim limiti, hız sınırı, unsubscribe, spam şikâyet yönetimi.
- *(önerim)* **2FA, CAPTCHA (Turnstile/reCAPTCHA), DDoS için Cloudflare**, güvenlik başlıkları testi (securityheaders).

---

## 10. Teknoloji Kararı (ÖNEMLİ — onay gerekli)

İsteğin: **MySQL** + CSS/JS/HTML + "diğer dillerle güçlendir".

| Katman | Öneri |
|---|---|
| Frontend | HTML5 + CSS3 (değişken tabanlı tema) + Vanilla JS (veya hafif framework); mobil öncelikli, responsive |
| Backend | **PHP 8.2+ (Laravel)** veya **Node.js (Express/Fastify)** — karar bekliyor |
| Veritabanı | **MySQL 8 / MariaDB** (senin isteğin) — şema + migration dosyaları |
| E-posta | SMTP/API sağlayıcı (Bölüm 11) |
| Zamanlanmış görev | cron job (günlük hatırlatma taraması) |
| Sunucu | Paylaşımlı hosting veya VPS (Nginx + PHP-FPM / Node + PM2) |

> **Not:** Sen "SaaS yönetimli" dedin → panel üzerinden site kimliği yönetimi + çoklu müşteri +
> abonelik/plan altyapısı. Ödeme altyapısı (kredi kartı) istenirse ayrıca eklenir — şu an
> isteklerinde **ödeme** geçmiyor; **onaylarsan** plan/ödeme modülü (ör. iyzico / Stripe) eklerim.

---

## 11. Açık Kararlar (Senin onayın gereken)

1. **Backend dili:** PHP (Laravel) mi, Node.js mi?
2. **Hosting:** Paylaşımlı hosting (cPanel, ucuz, kolay) mi, VPS (esnek) mi? MySQL'e ikisi de uyar.
3. **Marka adı ve logo:** Hazır mı, ben mi önereyim? (Bölüm 12)
4. **Ödeme/abonelik:** Şimdi gerekli mi? (Gerekliyse sağlayıcı: iyzico / Stripe)
5. **Mail sağlayıcı:** Resend / Brevo / Postmark / Amazon SES — hangisini kullanacağız?
6. **Çoklu dil:** Sadece TR mi, TR + EN mi?
7. **Ek kanallar:** Telegram / WhatsApp / SMS bildirimi istiyor musun?
8. **Kurumsal (filo) plan** ve **çoklu kullanıcı** istiyor musun?

---

## 12. Benim Önerilerim (Sende olmayan fikirler)

### Ürün / iş modeli
1. **Marka adı önerileri:** EvrakTakip, PlakaNöbetçi, AraçKalkan, NotifyCar, VadeTakiP,
   Kıbrıs Evrak Hatırlat, EvrakGuard, MotorTakip, PlateCare. (Logo: plaka + tik/kalkan ikonu)
2. **Kurumsal (filo) planı:** Sigorta acenteleri, galeriler, kiralık araç firmaları,
   inşaat şirketleri, e-ticaret kurye filoları — yüksek gelirli B2B segment.
3. **Acente/galeri ortaklık programı:** Acente kendi müşterisini sisteme ekler,
   komisyon/indirim alır — size ücretsiz müşteri getirir.
4. **Beyaz etiket (white-label):** Kurumsal müşteri kendi logosu ile kullanır (üst paket).
5. **Ücretsiz plan + kademeli abonelik:** 1 araç ücretsiz → 5 araç → 25 araç → sınırsız.
6. **Yıllık ödeme indirimi** ve **referans (davet) indirimi**.
7. **İlk 100 müşteriye özel "kurucu üye" fiyatı** — gerçek yerel müşteri toplamak için.
8. **Yerel büyüme:** Kıbrıs'ta Facebook grupları, üniversite, esnaf odaları, sigorta acenteleri,
   araba galerileri, şoför grupları ile tanıtım. Blog + Google SEO ile ücretsiz trafik.

### Ürün özellikleri
9. **WhatsApp hatırlatma** (Kıbrıs'ta fiili olarak mailden daha etkili).
10. **Çoklu bildirim kanalı:** SMS, Telegram, tarayıcı push.
11. **Takvim (.ics / Google Takvim)** ve **PDF rapor**.
12. **Evrak belgesi arşivi** (ruhsat/poliçe fotoğrafı) + "yenilendi" işaretleme ve tarih geçmişi.
13. **Araç başına maliyet takibi** (yakıt, servis, sigorta) → yıllık gider raporu.
14. **Aile / firma hesabı:** Bir aracı birden fazla kişi görüntüleyip yönetebilsin.
15. **Şoför & ehliyet takibi** (sürücü bazlı hatırlatma).
16. **Tekneler / tarım araçları** gibi Kıbrıs'a özel türler.
17. **"Süresi geçti" durumunda uyarı seviyesi artırma** ve risk açıklaması.
18. **Dashboard grafikler** ve Excel/CSV dışa aktarma.
19. **Offline/PWA:** telefonda uygulama gibi kurulabilsin, bildirim alabilsin.
20. **Ürün turu (onboarding)** — kayıttan sonra 3 adımda sistemi öğret; kayıt sonrası bırakma oranı düşer.

### Teknik / operasyonel
21. **Idempotent mail gönderimi** (aynı hatırlatma iki kez gitmez) + gönderim logu.
22. **Retry kuyruğu** ve gönderim hata alarmı.
23. **Yedekleme + geri yükleme testi** (en çok ihmal edilen, en kritik madde).
24. **Audit log** ve admin için 2FA.
25. **Analytics:** hangi bölüm dönüşüm getiriyor, kaç kayıt tamamlanıyor.
26. **Durum sayfası / izleme** (site ayakta mı, cron çalıştı mı).

---

## 13. Yol Haritası (önerilen)

| Faz | Kapsam |
|---|---|
| **Faz 0** | Kararlar + marka adı/logo + tasarım sistemi (renk, tipografi, bileşenler) |
| **Faz 1** | Veritabanı şeması, üyelik/giriş, admin panel iskeleti, site kimliği ayarları (logo, favicon, başlık, meta, preloader, renk, OG) |
| **Faz 2** | Araç + evrak modülü, müşteri paneli, dashboard renk durumları |
| **Faz 3** | Hatırlatma motoru + premium mail şablonu + cron + log |
| **Faz 4** | Public site (dark/light, drawer giriş/üyelik, slider, nasıl çalışır, üyelik anlatımı) |
| **Faz 5** | Reklam maili kampanya modülü, güvenlik sertleştirme, yedekleme, SEO |
| **Faz 6** | (Opsiyonel) plan/ödeme, WhatsApp/SMS, takvim, rapor, filo hesabı |

---

## 14. Başarı Kriterleri (ölçülebilir)

- Kayıt olan kullanıcı **ilk 5 dakikada** en az 1 araç ve 1 evrak ekleyebiliyor.
- Hatırlatma maili **planlanan gün ve saatte** gönderiliyor, **tekrar gönderilmiyor**.
- Mail **spam'e düşmeden** inbox'a ulaşıyor (SPF/DKIM/DMARC doğrulaması geçiyor).
- Site mobilde 90+ Lighthouse performansı, dark/light sorunsuz.
- Güvenlik: OWASP Top 10 kontrolleri geçiyor; yükleme, giriş ve yetki testleri başarılı.
