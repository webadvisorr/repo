# evrako — Yol Haritası ve Durum

Son güncelleme: Faz 0–4 ve Faz 6 tamamlandı (Faz 5 kısmi).

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

## ✅ Üyelik e-posta doğrulama (6 haneli kod)
- Kayıt sonrası 6 haneli kod e-posta ile gönderilir; **2 dakika** geçerlidir ve yalnızca hash'lenmiş olarak saklanır.
- En fazla **5 hatalı deneme**, **60 saniye** yeniden gönderme bekleme süresi; yeni kod eskisini geçersiz kılar.
- Doğrulanmamış müşteri `/panel` ve alt sayfalarına giremez; girişte kod ekranına yönlendirilir.
- Site marka renkleri, logosu ve responsive tablo düzeniyle **6 dilde** (tr/en/ru/ar/fa/el) markalı doğrulama e-postası; şablon panelden düzenlenebilir.
- Doğrulama: **40 kontrol** (kod üretimi/hash, TTL, hatalı kod, süre dolması, yeniden gönderim, SMTP teslimi, doğrulanmamış giriş/panel engeli).

## ✅ Faz 2 — Araç & Evrak Modülü (çekirdek tamamlandı)

**Bu sürümde eklenen (müşteri paneli):**
- **Araç CRUD**: tür bazlı liste, plaka (kurum içinde benzersiz), marka/model/yıl/VIN/motor/renk/km/sürücü, not, durum (aktif/satıldı/arşiv)
- **Plaka tekrar engeli**; arşivlenmiş aynı plaka yeniden eklenirse kayıt geri getirilir ve güncellenir
- **Plan limiti** (`plans.max_vehicles`): araç ekleme ve `/yeni` formu limit doluyken engellenir
- **Evrak CRUD**: tür, kurum/sigorta, poliçe no, başlangıç/bitiş, ücret, hatırlatma günleri, not
- **Evrak türü varsayılan geçerlilik süresi** ve türe göre filtre (applies_vehicle_types)
- **Belge arşivi**: ruhsat/poliçe fotoğrafı & PDF yükleme (`document_files` + `media`), indirme ve silme
- **"Yenilendi" işaretleme + tarih geçmişi**: eski kayıt `renewed` olur, yeni dönem kaydı `renewed_from_id` ile ona bağlanır
- **Trafik ışığı** (PRD): yeşil 30+ gün, sarı <30, turuncu <7, kırmızı süresi geçti; liste ve detayda özet ışık
- **Üyelik yetkileri**: `can_manage_vehicles` / `can_manage_documents` bayrakları ve rol kontrolü (owner/manager/viewer)
- **Evrak limiti** (`plans.max_documents_per_vehicle`) kontrolü
- Rotalar: `/panel/araclar`, `/panel/araclar/{id}`, `/panel/evrak/{id}/...`, panel üst menüsü ve 6 dilli sözlük (`vehicles.*`, `documents.*`)
- Doğrulama: 92 PHP dosyası lint temiz; gerçek MariaDB + HTTP ile 21 kontrol geçti (CRUD, çift plaka, CSVF 419, yetki 302, plan limiti, kırmızı/sarı ışık, yenileme zinciri, dosya yükleme)

**Bu sürümde eklenen (Faz 2 tamamlanması):**
- **Aylık takvim görünümü** (`/panel/takvim`): hangi araçta hangi evrak hangi gün bitiyor; ay ileri/geri gezinme, bugün vurgusu, evrak türü renk şeritleri, araca tıklayarak gidiş
- **.ics indirme** (`/panel/evrak/{id}/takvim.ics`, RFC 5545, tüm gün etkinlik + VALARM hatırlatma) ve **Google Takvim** ekleme bağlantısı
- **Yazdırılabilir evrak dökümü raporu** (`/panel/araclar/{id}/rapor`): marka başlıklı, yazdırma/PDF kaydetme düğmeli sayfa (tarayıcı "PDF olarak kaydet" ile)
- **Filo / Ekip** (`/panel/ekip`): e-posta ile **alt kullanıcı daveti** (`auth_tokens.invite`), davet kabul akışı (`/davet` — ad + şifre belirleme), **yetki matrisi** (rol + araç/evrak/ödeme izinleri), üye güncelleme/kaldırma, **sahip koruması**
- **max_users plan limiti** davette zorunlu; e-posta sunucusu tanımlı değilse davet bağlantısı sahibine gösterilir (kopyalama için)
- Yetkiler uygulanır: yalnız evrak izni olan üye araç ekleyemez, evrak ekleyebilir
- Doğrulama: 98 PHP dosyası lint temiz; gerçek MariaDB + HTTP ile **41 kontrol** geçti (takvim, .ics başlığı/gövdesi, rapor, davet→kabul, viewer yetki engeli, max_users limiti, izin matrisi kaydı, üye kaldırma, sahip koruması, CSRF 419)

**Faz 2 kalanı (opsiyonel iyileştirme):**
- Gerçek **PDF dosyası** üretimi (şu an yazdır→PDF); Türkçe karakterler için gömülü TTF alt kümesi gerekir
- Takvimde haftalık/günlük görünüm ve toplu (.ics) dışa aktarma

## ✅ Faz 3 — Hatırlatma Motoru & Premium Mail Şablonu (bu sürümde tamamlandı)

**Hatırlatma motoru** (`app/Services/ReminderService.php` + `cron/reminders.php`)
- Günlük tarama: `reminders.offsets` (varsayılan **7/3/1/0 gün**) ile bitişi yaklaşan evraklar kuyruğa alınır
- **İdempotent gönderim**: her bildirim `notifications.dedupe_key` (alıcı + evrak + tetik günü + bitiş tarihi) ile tekilleştirilir — aynı hatırlatma iki kez kuyruğa girmez, kayıt yenilenip yeni dönem açıldığında yeniden gönderilir
- **Kuyruk + geri çekilmeli yeniden deneme**: `queued/sent/failed/skipped/cancelled`, `attempts`, `error`, `scheduled_for`; `reminders.max_attempts` sonrası kalıcı hata
- **Günlük limit** (`reminders.daily_limit`) ve **parti boyutu** (`reminders.batch`) ile SMTP yükü sınırlanır; gönderim saati (`reminders.hour`), motor aç/kapa (`reminders.enabled`)
- Trafik ışığı rengi + **6 dilde** "kalan gün" ve çoğul biçimlendirmesi (tr/en/ru/ar/fa/el)
- Aynı taramada yan etki: **süresi geçen abonelikler** ve **süresi geçen havale talepleri** kapatılır
- Çalıştırma: **CLI** `php cron/reminders.php` (çıkış kodu 0/2) veya SSH'sız **web cron** `GET/POST /cron-hatirlatma?token=...` (token `security.cron_token`; Sistem sayfasında görünür ve tek tıkla tetiklenir)

**Premium responsive HTML mail şablonu** (`app/Services/MailTemplateService.php`)
- Marka logosu, **durum şeridi** (trafik ışığı rengi/etiketi), **araç kartı** (plaka, marka/model, evrak, bitiş, kalan süre), **CTA düğmesi** + yedek bağlantı, şirket/KVKK satırı, **abonelikten çıkma** bağlantısı
- Tablo tabanlı + satır içi CSS, koyu mod uyumu, **RTL (ar/fa)** hizalama desteği; her mail için **HTML + düz metin** gövde
- **Değişken motoru**: `{ad} {plaka} {arac} {evrak} {bitis} {kalan_gun} {kalan_gun_metin} {durum_etiket} {durum_renk} {link} {marka_adi} {logo_url} {site_url} {sirket} {adres} {yil} {abonelikten_cik}`

**Yönetim paneli**
- **Mail şablon editörü** (`/yonetim/sablonlar`, `/yonetim/sablonlar/{key}/{locale}`): 6 dil, değişken sözlüğü, **önizleme**, **test gönderimi**, **sıfırla**; kod varsayılanları paneldeki düzenlemeleri ezmez
- **Bildirim günlüğü** (`/yonetim/bildirimler`): gönderildi / açıldı / hata, konu, alıcı, deneme sayısı, hata metni, detay ekranı; **yeniden dene**, **başarısızları dene**, **iptal**, **kuyruğu işle**, **tara**
- **Ayarlar → Hatırlatma** sekmesi (motor, günler, saat, günlük limit, parti, deneme) ve Sistem sayfasında kuyruk özeti + cron tetikleme

**Takip & KVKK**
- **Açılma takibi**: `/mail-piksel/{id}/{imza}.gif` 1x1 saydam GIF; açılma `notifications.opened_at` alanına yazılır (imzalı, oturum gerektirmez)
- **Abonelikten çıkma** (`/abonelikten-cikar`): imzalı bağlantı, onay ekranı, **tek tık** (RFC 8058) ve **yeniden açma**; `unsubscribes` tablosu (marketing/all kapsamı)
- Mail başlıklarına **`List-Unsubscribe` + `List-Unsubscribe-Post`** eklenir

**Doğrulama (bu sürüm):** 139 hatırlatma/mail kontrolü (idempotent tekrar engeli, geri çekilmeli yeniden deneme, günlük limit, 6 dil şablonu, önizleme/test gönderimi, günlük kuyruk, hata senaryosu, piksel ile açılma kaydı, web cron token koruması, ayarlar sekmesi) + **129 gerileme kontrolü** (Faz 2 + Faz 6) gerçek MariaDB + HTTP + yerel SMTP alıcısı ile geçti; **122 PHP dosyası** lint temiz.

## ✅ Faz 4 — Reklam/Kampanya Maili (kampanya modülü tamamlandı)

**Kampanya modülü** (`app/Services/CampaignService.php`, `/yonetim/kampanya`)
- **Liste ekranı**: durum rozetleri (taslak / planlı / gönderiliyor / gönderildi / iptal), alıcı-gönderim-açılma-hata sayaçları, kuyruk ve günlük limit özeti, SMTP hazır mı uyarısı
- **Hedef kitle filtresi**: plan, kurum, dil ve **yalnız pazarlama izni verenler** (varsayılan açık); `notify_email=0` ve **abonelikten çıkmış** adresler her zaman hariç; formda **canlı alıcı sayısı**
- **İçerik + değişken motoru**: `{ad} {eposta} {kurum} {plan} {marka_adi} {site_url} {yil} {abonelikten_cik}`; konu ve gövde alıcı bazında işlenir
- **Önizleme**: kaydetmeden HTML önizleme + değişken sözlüğü; **test gönderimi** (`[TEST]` konu öneki, kuyruğa yazılmaz)
- **Planlı gönderim**: `scheduled_at` verilirse kampanya `scheduled` olur, cron zamanı gelince kuyruğa alıp gönderir
- **Kuyruk entegrasyonu**: gönderim mevcut `notifications` kuyruğunu kullanır (template_key `campaign`) — **idempotent dedupe**, geri çekilmeli yeniden deneme, günlük limit/parti, Bildirim Günlüğü görünürlüğü hatırlatma motoruyla ortak
- **İstatistik**: `campaign_recipients` alıcı bazlı durum + **açılma zamanı**; mevcut `/mail-piksel` ucu kampanya pikselini de işleyip kampanya `open_count` ve alıcı `opened_at` alanlarını günceller
- **İptal / sil**: iptal kuyruktaki bekleyen bildirimleri `cancelled` yapar; gönderilmiş kampanya silinemez
- Mail gövdesine otomatik **abonelikten çıkma alt bilgisi** ve **açılma pikseli** eklenir; alıcının diline göre alt bilgi (6 dil)
- Yönetim menüsüne **Kampanyalar** girdisi; 6 dilli sözlükte `admin.campaigns`

**Doğrulama (bu sürüm):** 52 kampanya kontrolü (izin/abonelik filtreleri, taslak→planlı→gönderildi durumları, test gönderimi, dedupe ile ikinci gönderim engeli, açılma pikseli sayacı, cron ile planlı gönderim, iptal, yönetici olmayan erişim 403) yerel SMTP alıcısı + gerçek MariaDB + HTTP ile geçti.

**Faz 4 kalanı:**
- ~~Zorunlu abonelikten çık (unsubscribe)~~ → **Faz 3'te teslim edildi** (imzalı bağlantı + tek tık + yeniden açma); kalanı: double opt-in ve bounce yönetimi
- SMS / WhatsApp / Telegram gönderim sürücüleri (sağlayıcı bilgileri panelden)
- Tıklama (click) takibi ve A/B konu testi
- SPF/DKIM/DMARC kontrol rehberi

## 🟡 Faz 5 — Abonelik & Ödeme (kısmen tamamlandı)

**Bu sürümde eklenen — Havale/EFT ile self-servis ödeme (kapalı devre çalışıyor):**
- **Ödeme Merkezi** (`/panel/odeme`): müşteri kendi kredi yükler veya abonelik satın alır/yeniler
- **Havale niyeti + ödeme kodu**: her talep için `HV-YYYY-XXXXXX` kodu üretilir (`payments.reference`, benzersiz)
- **Talimat sayfası** (`/panel/odeme/talep/{id}`): IBAN listesi, açıklama uyarısı, geçerlilik süresi, talep geçmişi
- **"Ödemeyi yaptım" bildirimi**: gönderen/banka/tarih/not → yönetici kuyruğuna düşer
- **Yönetici onay kuyruğu** (`/yonetim/odemeler`): havale bildirimlerini gör, tutarı düzelt, **Onayla** / **Reddet**
- Onayda otomatik uygulama: **kredi yükleme** veya **abonelik başlatma / süre uzatma**; **çift onay engeli** (idempotent)
- Süresi geçen (bildirilmemiş) talepler cron ile kapanır (`PaymentService::expireStale`)
- İzlenebilirlik: `payment_events` olay günlüğü (niyet, bildirim, onay, red, iptal) + audit kaydı
- Ayarlar: **çoklu IBAN hesabı editörü**, minimum yükleme, hızlı tutarlar, geçerlilik süresi, havale aç/kapa
- Müşteri panelinde: kredi bakiyesi, bekleyen talepler, ödeme geçmişi (`/panel/odemelerim`)
- Ödeme sayfası (iyzico/PayTR) için `payments.gateway_token` / `gateway_url` alanları **hazır** — sonraki sürümde aynı akışa bağlanacak

**Tamamlanan (bu sürüm) — aylık/yıllık paket fiyatları ve kredi ile paket ödemesi:**
- **İki fiyatlı plan modeli**: `plans.price_month` + `plans.price_year`; panelde aylık ve yıllık fiyat ayrı girilir, yıllık indirim yüzdesi otomatik hesaplanır; ömür boyu planlarda tek `price` kullanılır
- **Dönem seçimli satın alma**: site fiyat sayfasında **Aylık / Yıllık** geçişi (tasarruf yüzdesi ile), müşteri ödeme merkezinde plan başına dönem seçimi; abonelik seçilen dönemi (`subscriptions.billing_interval`) ve o günkü fiyatı (`price_at_signup`) saklar — sonraki fiyat değişiklikleri eski aboneliği etkilemez
- **Kredi bakiyesinden paket ödemesi** (`/panel/odeme` → **Kredi ile öde**): bakiye yetersizse hiçbir kayıt oluşmaz, eksik tutar bildirilir ve kredi yükleme alanına yönlendirilir; başarılı ödemede bakiye düşülür (`credit_ledger`) ve `payments` kaydı `credit` yöntemiyle yazılır, uygulama hatasında **otomatik iade**
- **Havale ile dönem seçimi**: ödeme talebi seçilen dönemi taşır (`payments.billing_interval`), onayda doğru süre (30 / 365 / ömür boyu) uygulanır
- Aynı planda aynı dönemle yenileme **yeni abonelik açmadan** dönemi uzatır; farklı dönem/plan seçimi yeni abonelik açar ve eskiyi kapatır
- Yönetim paneli: planlarda aylık/yıllık fiyat rozetleri, **MRR** (yıllık abonelikler 12'ye bölünür), üye detayında **dönem + dönem ücreti** ve plan atarken dönem seçimi
- Müşteri panelinde flash/uyarı mesajları artık görünüyor (site layout düzeltmesi) — ödeme geri bildirimi için gerekliydi
- Doğrulama: **59 yeni kontrol** (plan CRUD fiyatları, fiyat sayfası dönem geçişi/tasarruf, kredi yetersizliği, aylık satın alma, yenileme, yıllık satın alma, havale onayı süresi, ömür boyu plan, ödeme defteri) + **429 regresyon kontrolü** gerçek MariaDB + HTTP ile geçti; 127 PHP dosyası lint temiz.

**Önceki sürümde tamamlanan:**
- Kredi (ön ödemeli bakiye) altyapısı: `organizations.credit_balance` + **kredi hareket defteri** (`credit_ledger`)
- Panelden **kredi ekleme / düşme** (gerekçe + not + izlenebilirlik, negatif koruma, atomik işlem)
- **Abonelik atama / plan değiştirme / süre uzatma / dönem sonunda iptal / hemen iptal / geri alma**
- **Kredi ile abonelik ödeme** (bakiyeden düşer, dönemi uzatır, başarısızlıkta otomatik iade)
- Manuel ödeme kaydı: havale/EFT, nakit, kredi kartı; durum onaylama, iade işaretleme
- Plan CRUD (fiyat, periyot, limitler, kanallar), abone sayısı ve tahsilat raporu
- Abonelik sona erme taraması (`expireOverdue`) ve plan limiti kontrolü (`canAddVehicle`)
- **SSH'sız veritabanı güncelleme**: `/yonetim/sistem` üzerinden tek tıkla migration + cron tetikleme
- Müşteri panelinde kredi bakiyesi, abonelik durumu ve kredi hareket özeti

**Kalan (Faz 5 devamı):**
- **iyzico** / **PayTR** ortak ödeme sayfası entegrasyonu (kullanıcı talebiyle **şimdilik ertelendi**;
  havale akışı hazır, aynı "niyet → ödeme → uygula" hattına bağlanacak)
- **iyzico** hosted Checkout Form entegrasyonu (3D Secure + callback doğrulaması) — KKTC/TR için önerilen;
  alternatif PayTR. ⚠️ Merchant başvurusu + şirket evrakları **kullanıcı tarafında** gerekir.
  Anahtarlar panelden girilecek (`payment.iyzico_api_key` / `payment.iyzico_secret` / ortam adresi).
- Otomatik yenileme (off-session tahsilat) ve dönem sonu hatırlatması (kredi ile paket ödemesi artık self-servis çalışıyor)
- Fatura (PDF) üretimi ve e-arşiv entegrasyonu
- Bakiye otomatik yenileme uyarısı (düşük kredi bildirimi)

## ✅ Faz 6 — SEO, Dönüşüm & Yasal Uyum (bu sürümde eklendi)

**SEO & keşfedilebilirlik**
- **Özel hata sayfaları** 404/403/405/419: site layout'u içinde markalı, kısayol kartları ve CTA ile (önceden layout'suz çıplak HTML veriyordu)
- **Sayfa bazlı meta başlık & açıklama**; canonical artık her sayfada kendi adresi (panel `seo.canonical` yalnızca ana sayfada geçerli)
- **hreflang** alternatifleri (6 dil + x-default), **og:image:alt/width/height**, mutlak OG görseli ve markalı varsayılan (`assets/brand/og-default.svg`)
- **JSON-LD**: Organization, WebSite, FAQPage (SSS varsa), Product/Offer (fiyatlandırma)
- **Dinamik `robots.txt`** (panel robots ayarına uyar, /panel /yonetim /media kapalı) ve **`sitemap.xml`** (yayınlanan sayfalar)
- favicon / apple-touch-icon için yerleşik SVG yedeği

**Dönüşüm & arayüz**
- **Slider yenilendi**: DB'de slayt yoksa çevrilebilir 3 varsayılan slayt; ken-burns (yavaş zoom), ilerleme çubuğu, ok tuşları, dokunmatik kaydırma, hover'da duraklama
- **İlk ekranda CTA** (slider butonu + ikincil bağlantı) ve altında **güven şeridi**
- **Sabit CTA bandı** (misafirler için, kaydırınca görünür, kapatılabilir; panelden yönetilir)
- **Popup/duyuru** (panelden başlık, metin, görsel, buton, gecikme ve sıklık: session/haftalık/her zaman)
- **Çerez banner'ı** + **onaya bağlı analitik** (GA4 / Plausible / özel script); analytics açıkken CSP `script-src`/`connect-src` otomatik genişler
- WhatsApp yüzen buton, yukarı dön, okuma ilerleme çubuğu, `skip-link`
- Footer'da **iletişim bilgileri + sosyal medya**; mobil menüde hızlı iletişim bağlantıları

**Yasal & formlar**
- **Gizlilik & KVKK** ve **Kullanım Koşulları** sayfaları (`/gizlilik`, `/kullanim-kosullari`) — footer bağlantıları artık 404 vermiyor
- **Teşekkürler sayfası** (`/tesekkurler`) — iletişim formu gönderimi sonrası adıyla karşılanır
- **Form hataları**: sunucu tarafı hata gösterimi (`has-error` + `form-error`) + istemci tarafı anlık doğrulama, karakter sayacı, honeypot
- **Yükleniyor durumları**: butonlarda spinner + `data-loading-text`, preloader, form devre dışı bırakma
- **Görseller**: `alt`, `loading="lazy"`, `decoding="async"`, ilk slaytta `fetchpriority="high"`
- Eksik **`assets/css/rtl.css`** oluşturuldu (Arapça/Farsça yön düzeltmeleri)

**Yönetim paneli**
- Yeni ayar sekmeleri: **Analitik**, **Çerez Banner'ı**, **Popup**, **Slider**, **Sabit CTA** (mevcut ekle/değiştir mantığıyla)
- 6 dilli sözlüğe `slider.*`, `http.419` ve tüm yeni ayar etiketleri eklendi

**Doğrulama (bu sürüm):** 53 genel site kontrolü + 18 yönetim paneli kontrolü + 32 Faz 2 regresyon kontrolü geçti (gerçek MariaDB + HTTP); tüm PHP dosyaları lint temiz.

**Tamamlanan (bu sürüm) — iki paketli fiyat modeli, Gemini asistanı ve panel bilgi balonları:**
- **İki paket**: (1) 6 ay ücretsiz deneme — tek araç; (2) Standart Paket — ilk araç için tek seferlik **490 TL**.
- **Ek araç indirimleri**: 2. araç **%20** (392 TL), 3. araç **%30** (343 TL), 4. araç ve sonrası **%30** (343 TL) sabit. Fiyat sayfasında canlı araç sayısı hesaplayıcısı ve kademe tablosu var.
- **Evraklar ücretsiz**: muayene, sigorta, kasko, seyrüsefer ve diğer tüm evraklar için araç/evrak başına ek ücret alınmaz; bu bilgi sitede ve asistan rolünde açıkça tanımlı.
- **Ek araç tahsilatı**: Standart pakette 2. ve sonraki araçlar kredi bakiyesinden düşülür; bakiye yetersizse araç eklenmez ve ödeme merkezine yönlendirilir. İlk araç paket fiyatına dahildir.
- **Göç**: `2026_10_20_009_two_plans.sql` + `010_retire_legacy_plans.sql` eski paketleri satıştan kaldırır (aboneliği olanlar silinmez).
- **Gemini site asistanı**: sol altta “Asistana sor” widget’ı; halkla ilişkiler/müşteri danışmanı rolü, iki paket ve evrak ücretsizliği bilgisi, fiyat uydurmama kuralı, oturum başına hız sınırı. Anahtar panelden (`Ayarlar → Site Asistanı`) girilir; anahtar yokken widget görünmez.
- **Panel bilgi balonları**: yönetim ve müşteri panelindeki her sayfa başlığının yanında “i” düğmesi; slider formunda bölüm bazlı açıklamalar.
- **Doğrulama**: fiyat (21), asistan/balon (32) yeni kontrol; e-posta doğrulama (40), slider/panel modülleri (92), hatırlatma (151), kampanya (52), plan dönemleri (59), genel site (147) ve diğer paketler yeniden geçti.

**Tamamlanan (bu sürüm) — site içi bildirim merkezi ve özel duyurular:**
- **Zil menüsü**: giriş yapmış kullanıcının site başlığında 🔔 düğmesi; okunmamış sayısı rozetle gösterilir. Panel açılınca liste `/panel/bildirimler/ozet` ile tazelenir, bildirime tıklanınca okundu işaretlenir ve bağlantısı açılır.
- **Bildirimler sayfası** (`/panel/bildirimler`): Trendyol/banka tarzı kart listesi (görsel/ikon, başlık, metin, “Yeni” rozeti, buton), sayfalama, “Okundu işaretle” ve “Tümünü okundu işaretle”.
- **Hatırlatmalar artık zile de düşer**: `ReminderService::scan()` e-postanın yanında `user_notifications` kaydı üretir (plaka + evrak + kalan gün + araç bağlantısı). E-posta izni/abonelikten çıkma yalnızca e-postayı etkiler; dedupe e-posta kuyruğundan bağımsızdır.
- **Özel duyuru** (`/yonetim/duyurular`): başlık, metin, **görsel** (en fazla 3 MB), **buton yazısı + bağlantı** (site yolu veya http(s)); canlı önizleme. Hedef kitle: tüm kullanıcılar / paket / kurum / tek kullanıcı (e-posta). Gönderim anında panelde görünür, e-posta göndermez.
- **Raporlama**: duyuru başına ulaşan, okuyan, okunma oranı ve alıcı bazlı okundu bilgisi; duyuru silinince kullanıcı kayıtları da temizlenir.
- **Göç**: `2026_10_22_011_inbox_notifications.sql` (`inbox_broadcasts`, `user_notifications`).
- **Doğrulama**: `.freebuff/e2e_inbox.sh` — 46 kontrol (hatırlatma→zil, dedupe, okundu, CSRF, kullanıcı izolasyonu, görselli duyuru, hedef kitle, silme). Hatırlatma (151), kampanya (52), panel modülleri (92), genel site (147) ve diğer paketler yeniden geçti.

**Tamamlanan (bu sürüm) — canlı ziyaretçi, destek talepleri ve müşteri mesajları:**
- **Canlı ziyaretçi**: yönetim panelinde “Şu an sitede” kutusu (son 5 dakika, tekil oturum) + canlı ziyaretçi listesi (üye adı, sayfa, son görülme) ve 25 saniyede bir JSON ile tazelenen sayaç. Panel istatistik kutuları renklendirildi; “Başarısız bildirim” kutusu kaldırıldı.
- **Tarih aralığı filtreleri**: e-posta günlüğü, denetim kaydı, mesajlar, destek talepleri ve üyeler listelerinde başlangıç/bitiş tarihi.
- **Adlandırma**: “Duyurular” → **Bildirim**; “Bildirim Günlüğü” → **E-posta Günlüğü**.
- **Mesaj takibi**: iletişim formu mesajları giriş yapan müşteriye bağlanır; müşteri panelindeki **Mesajlarım** ekranında konuşma ve “Yanıt yaz” bulunur. Yönetim mesaj detayında aynı konuşma ve panelden yanıt formu vardır (`contact_replies`).
- **Destek talepleri**: müşteri panelinde talep açma, kategori/öncelik, konuşma, yanıt ve kapatma; yönetimde `/yonetim/destek` listesi (durum/arama/tarih filtresi, yeni mesaj rozeti) ve detayda yanıt/durum yönetimi (`support_tickets`, `support_ticket_messages`).
- **Hızlı arama**: yönetim menüsünün en üstünde arama kutusu; ad, e-posta, telefon, plaka ve kurum adıyla arayıp müşteri/araç kaydına doğrudan erişim (`/yonetim/arama`).
- **Göç**: `2026_10_22_012_presence_messages_support.sql`.
- **Doğrulama**: `.freebuff/e2e_support_presence.sh` — 64 kontrol. Önceki paketler (çekirdek 29, genel site 147, panel modülleri 92, hatırlatma 151, kampanya 52, ayarlar 31, asistan 32, bildirim 50) yeniden geçti.

**Tamamlanan (bu sürüm) — cihaz bildirimleri, hesap e-postaları ve panel kutucukları:**
- **Cihaz bildirimleri (Web Push)**: müşteri `/panel/bildirimler` ekranından bu cihazı bağlar (tarayıcı izni + VAPID). Panel zil bildirimleri, özel duyurular ve otomatik evrak hatırlatmaları bağlı telefon/bilgisayarlara da düşer. Service worker `/sw.js`, VAPID anahtarları ilk kullanımda üretilip `settings` tablosunda tutulur; AES-128-GCM (RFC 8291) şifreleme ve ES256 JWT saf PHP/openssl ile yapılır. Gönderim kuyruğu `push_queue`; cron ve “Bekleyen cihaz bildirimlerini gönder” düğmesi işler. Geçersiz (404/410) abonelikler otomatik silinir.
- **Karşılama**: yeni üyeye panel zil bildirimi + markalı “Hoş geldiniz” e-postası (deneme paketi bilgisiyle). Kayıt akışına bağlıdır.
- **Otomatik hesap bildirimleri**: kredi yükleme, havale/EFT onayı, abonelik başlatma ve süre uzatma olaylarında panel bildirimi + e-posta. E-postalar `notifications` günlüğüne (şablon: `account`) yazılır; SMTP yoksa/başarısızsa panel bildirimi yine kalır.
- **Marka adı**: “Evrako KKTC” → **Evrako** (ayar, varsayılanlar ve tüm fallback metinleri). Hukuk metnindeki “KKTC hukuku” ifadesi korunur.
- **Giriş/üyelik butonu**: site yeşili zemin + beyaz yazı (`btn-auth`), başlık, mobil menü ve giriş/kayıt sekmelerinde.
- **Panel kutucukları**: ana sayfada e-posta/WhatsApp/Telegram/SMS hatırlatma gönderim sayıları, günlük/haftalık/aylık yeni üye, araç/evrak, ciro, destek, cihaz ve ziyaretçi sayaçları. Sağ üstteki **⚙️ Kutucuk ayarları** ile görünecekler seçilir; seçim `dashboard.widgets` ayarında saklanır.
- **Panel rehberi**: yanıtlar artık bağımsız inline script ile çalışır (app.js hatası/önbelleği etkilemez); Gemini anahtarı varsa rehber AI modunda yanıtlar, yoksa yerel bilgi tabanı. “Bildirim gönder” konusu ve selamlama eklendi. Mobil menü CSP nedeniyle çalışmıyordu, düzeltildi.
- **Göç**: `2026_10_22_013_web_push.sql` (`push_subscriptions`, `push_queue`) + `2026_10_22_014_brand_evrako.sql` (marka adı).
- **Doğrulama**: `.freebuff/e2e_push.sh` (66 kontrol; RFC 8291 çözme + VAPID imza doğrulaması) ve `.freebuff/e2e_account_dashboard.sh` (38 kontrol; karşılama/kredi/abonelik e-postaları, marka adı, buton, kutucuk seçimi). Rehber (83), e-posta doğrulama (41), genel site (147), panel modülleri (92), hatırlatma (151), plan dönemleri (59), destek (64), bildirim (50) ve asistan (32) paketleri yeniden geçti.

## 🔜 Faz 7 — Gelişmiş
- Çoklu dil için sayfa bazlı meta/OG alanları (şu an marka geneli + sayfa başlığı)
- Blog + SEO, PWA çevrimdışı kabuk
- Arama (site içi), canlı destek/chat widget'ı
- PDF rapor, Excel/CSV dışa aktarma, araç başına maliyet takibi, grafikler
- Yedekleme (otomatik DB + dosya) ve geri yükleme testi, durum izleme

## ⚠️ Kullanıcı tarafında yapılacaklar
1. Alan adı + hosting (cPanel) + SSL
2. MySQL veritabanı oluşturma
3. Kurumsal mail **SMTP bilgileri** (sunucu, port, kullanıcı, şifre) — panele girilecek
4. cPanel cron görevi tanımı
5. iyzico/PayTR başvurusu (ödeme almak için şirket evrakları)
6. WhatsApp Business / SMS sağlayıcı hesabı (opsiyonel)
