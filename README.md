# evrako — Araç Evrak Takip Sistemi (Kıbrıs)

Muayene (emüasyon), trafik sigortası, kasko, yol vergisi gibi araç evraklarının bitiş tarihlerini
takip eden; süresi dolmadan **e-posta / SMS / WhatsApp / Telegram** ile otomatik hatırlatan,
çoklu araç ve çoklu kullanıcı (filo) destekli SaaS platformu.

Geliştirici: **webadvisor** — https://webadvisor.com.tr

---

## 1. Teknoloji

| Katman | Teknoloji |
|---|---|
| Sunucu tarafı | **Saf PHP 8.0+** (framework yok, Composer yok — paylaşımlı hosting/SSH'sız çalışır) |
| Veritabanı | **MySQL 5.7+ / MariaDB 10.2+** (PDO, prepared statements) |
| Arayüz | HTML5 + CSS3 (değişken tabanlı tema) + saf JavaScript |
| E-posta | Kendi SMTP istemcimiz (SmtpSsl 465 / STARTTLS 587) — kurumsal mail hesabınızla |
| Zamanlanmış görev | cPanel cron (hatırlatma motoru) |

## 2. Klasör yapısı

```
/                     ← site kökü (public_html)
├─ index.php          ← site ön kontrolcüsü
├─ yonetim/index.php  ← yönetim paneli ( /yonetim )
├─ install.php        ← kurulum sihirbazı (kurulumdan sonra SİLİN)
├─ app/
│  ├─ Core/           ← çekirdek: Router, DB, Auth, Mailer, Security, Uploader, i18n...
│  ├─ Controllers/    ← Site/ ve Admin/ kontrolcüleri
│  ├─ Models/         ← (sonraki faz)
│  ├─ Services/       ← (sonraki faz: hatırlatma motoru, ödeme, bildirim)
│  ├─ Views/          ← şablonlar (layout + partial + site + admin)
│  └─ Database/       ← schema.sql, seed.php, default_settings.php
├─ config/config.php  ← KURULUMDA ÜRETİLİR (gizli; yedekleyin)
├─ lang/              ← tr, en, ru, ar, fa, el
├─ assets/            ← css, js, marka dosyaları
├─ storage/           ← uploads, logs, cache (web erişimine kapalı)
├─ cron/              ← zamanlanmış görevler (sonraki faz)
└─ docs/              ← PRD, BRAND, ROADMAP
```

## 3. Kurulum (cPanel / paylaşımlı hosting)

1. **Yükleyin:** tüm dosyaları `public_html` (veya alt alan adı klasörüne) FTP/File Manager ile atın.
2. **Veritabanı oluşturun:** cPanel → MySQL Databases → veritabanı + kullanıcı oluşturun, tüm yetkileri verin.
3. **İzinler:** `config/` ve `storage/` klasörleri yazılabilir olmalı (755/775).
4. **Tarayıcıda açın:** `https://alanadiniz.com/install.php`
5. Sihirbazda veritabanı bilgilerini, site adını ve yönetici hesabını girip **Kurulumu başlat**.
6. Kurulum biterken çıkan **Güvenlik listesini uygulayın**:
   - `install.php` dosyasını silin
   - `storage/installed.lock` dosyasını silin
   - `config/config.php` yedeğini güvenli bir yere alın
7. **cron görevi** (cPanel → Cron Jobs), günde 1 kez:
   ```
   /usr/local/bin/php /home/KULLANICI/public_html/cron/reminders.php
   ```
8. Panele girip **Ayarlar → Marka**, **Meta & OpenGraph**, **Renkler**, **Preloader** bölümlerini doldurun;
   **E-posta (SMTP)** bölümüne kurumsal mail sunucu bilgilerinizi yazın ve **test maili** gönderin.

> Yerel geliştirme: `php -S 127.0.0.1:8080 -t . dev-router.php` (bu dosya canlıda gerekmez).

## 4. Yönetim paneli özellikleri (hazır)

| Bölüm | Durum |
|---|---|
| Marka: logo (açık/koyu), favicon, apple icon, başlık, slogan, iletişim, sosyal | ✅ |
| Meta & OpenGraph: meta başlık/açıklama/keywords, og:*, twitter card, robots, canonical | ✅ |
| Renkler: açık + koyu tema için 10 renk, köşe yuvarlaklığı, varsayılan tema | ✅ |
| Preloader: görsel (png/gif/svg/webp/apng/ico) veya spinner, arka plan rengi (açık/koyu), süre sınırları | ✅ |
| Site: bakım modu, kayıt aç/kapat, deneme süresi, varsayılan hatırlatma günleri | ✅ |
| E-posta (SMTP): sunucu, port, şifreleme, kullanıcı, şifre, gönderen adı + **test maili** | ✅ |
| Bildirim kanalları: Telegram / WhatsApp / SMS aç-kapa ve sağlayıcı alanları | ✅ |
| Ödeme: sağlayıcı (iyzico / PayTR / havale), para birimi, anahtarlar, IBAN | ✅ |
| Denetim kaydı (audit log), giriş denemeleri, medya kütüphanesi | ✅ |

### Üye · Kredi · Abonelik yönetimi (yeni)

| Özellik | Nerede |
|---|---|
| Üye listesi: arama (ad/e-posta/telefon/plaka/kurum), durum & plan filtresi, sayfalama | `/yonetim/uyeler` |
| Üye detayı: profil, kredi bakiyesi, abonelik, ödemeler, araçlar, denetim geçmişi | `/yonetim/uyeler/{id}` |
| **Kredi ekleme** (neden + not ile) ve **kredi düşme/düzeltme** | üye detayı |
| Kredi hareket defteri (her hareket: tutar, kalan bakiye, yetkili, gerekçe) | üye detayı |
| **Abonelik atama / plan değiştirme** (süre, deneme süresi, ödeme kaydı) | üye detayı |
| Süre uzatma, dönem sonunda iptal, hemen iptal, iptali geri alma | üye detayı |
| **Kredi ile abonelik ödeme** (bakiyeden düşer, dönemi uzatır) | üye detayı |
| Hesap durumu (aktif/pasif/askıya alındı), profil ve bildirim tercihleri, şifre sıfırlama bağlantısı | üye detayı |
| Plan yönetimi: fiyat, periyot, araç/kullanıcı/evrak limiti, bildirim kanalları, abone sayısı, tahsilat | `/yonetim/planlar` |
| Ödemeler: havale/EFT, nakit, kart kredi kartı kaydı, kredi yüklemesi, durum onaylama/iade | `/yonetim/odemeler` |
| Sistem: tek tıkla **veritabanı güncellemesi (migration)**, cron durumu, tablo istatistikleri | `/yonetim/sistem` |

> Kredi ve abonelik işlemleri **tek transaction + satır kilidi** ile yapılır: eşzamanlı iki işlem bakiyeyi bozamaz,
> bakiye asla eksiye düşmez, her hareket denetim kaydına ve kredi defterine yazılır.

Her alan **ekle / değiştir / kaldır** destekler. Şifre alanları boş bırakılırsa mevcut değer korunur ve
hiçbir zaman HTML'e basılmaz.

## 5. Site arayüzü özellikleri (hazır)

- Dark / light mode (sistem tercihi + hafızalı anahtar, yanıp sönme olmadan)
- Sağdan kayan panel içinde **Giriş + Üyelik** sekmeleri
- Slider (görsel + başlık + metin + buton + link + açılış hedefi) — panelden yönetilir
- "Sistem nasıl çalışır?" ve "Üyelik nasıl olunuyor?" görsel+metin anlatımları
- Fiyatlandırma tablosu, SSS, iletişim formu, dil seçici (6 dil, Arapça/Farsça RTL)
- Preloader (tüm görsel formatları), OpenGraph paylaşım kartı, geliştirici imzası

## 6. Güvenlik önlemleri (uygulanmış)

- **Şifreler:** `password_hash` (bcrypt) + otomatik yeniden hashleme
- **SQL injection:** tüm sorgular prepared statement; tablo/kolon adları beyaz liste
- **XSS:** çıktı kaçışlama (`e()`), panelden gelen HTML sanitize, **CSP** (nonce ile)
- **CSRF:** tüm POST isteklerinde token + SameSite çerez + aynı origin kontrolü
- **Oturum:** httpOnly/secure/SameSite çerez, girişte oturum kimliği yenileme (fixation koruması)
- **Brute force:** IP ve e-posta bazlı hız sınırı + 5 hatalı denemede 15 dakika kilit
- **Dosya yükleme:** uzantı + gerçek MIME (finfo) + boyut kontrolü, PHP/HTML uzantı reddi,
  SVG içinde script/olay yakalayıcı taraması, dosyalar web kökü dışında (`storage/`) saklanır
- **Yetkilendirme:** rol kontrolü (admin/editor/customer), müşteri yalnız kendi verisini görür (IDOR)
- **Güvenlik başlıkları:** HSTS, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, COOP
- **Denetim kaydı:** kim, ne zaman, neyi değiştirdi; giriş denemeleri; şifre alanları maskelenir
- **Yol gezinmesi:** `/media/` ucu yalnızca kayıtlı dosyaları, `storage/` dışına çıkmadan servis eder
- `.htaccess`: app/, config/, lang/, storage/, cron/, docs/ klasörlerine doğrudan erişim kapalı

## 7. Sonraki adımlar

Ayrıntılı yol haritası: `docs/ROADMAP.md`
