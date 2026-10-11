# Marka Kimliği — Araç Evrak Takip Sistemi

## 1. İsim Önerileri

| # | İsim | Neden | Domain önerisi |
|---|---|---|---|
| ✅ **1** | **evrako** | Kısa (6 harf), akılda kalıcı, "evrak" kökünden türetilmiş ama uluslararası okunabilir (ev-ra-ko). TR/EN/RU/AR/FA/EL dillerinde telaffuzu kolay, logoya çok yakışıyor. Marka tescili ve domain bulunabilirliği yüksek. | evrako.com / evrako.com.tr / evrako.app |
| 2 | **PlakaNöbetçi** | Anlamı çok net ve yerel ("plakanın nöbetçisi"). Türkçe pazarda anında anlaşılır. Ancak uzun ve çok dillileştirmek zor. | plakanobetci.com |
| 3 | **VehicleVault** | Uluslararası/İngilizce pazar ve B2B (filo) için güçlü, "araç kasası". Rusça/Arapça pazarda da çalışır. Türkçe pazarda "evrako" kadar sıcak değil. | vehiclevault.io |

**Öneri:** Ana marka **evrako**, slogan **"Evrak bitişini asla kaçırma."**
Alt marka/slogan alternatifleri: *"Plakan güvende."* • *"Hatırlatmak bizim işimiz."* • *"Muayene, sigorta, vergi — hepsi tek yerde."*

## 2. Logo

| Dosya | Kullanım |
|---|---|
| `assets/brand/logo-mark.svg` | Sadece simge (mobil, uygulama ikonu, avatar, e-posta başlığı) |
| `assets/brand/logo-light.svg` | Açık zemin üzerinde tam logo (site header, fatura) |
| `assets/brand/logo-dark.svg` | Koyu zemin üzerinde tam logo (footer, dark mode, e-posta) |
| `assets/brand/favicon.svg` | Favicon (ayrıca 32/180/512 px PNG üretilecek) |

**Sembolün anlamı:** Araç plakası formundaki kart + onay (✓) işareti = "evrak tamam / süresi kontrol altında".
Sembol tek başına 16 pikselde bile okunur (favicon testi yapıldı).

**Logo kullanım kuralları**
- Minimum simge boyutu: 16 px; minimum tam logo genişliği: 120 px.
- Etrafında boşluk: simge yüksekliğinin en az ¼'ü kadar.
- Logoyu kesme, döndürme, gölge/efekt ekleme, oranını bozma.
- Zemin rengi koyuysa `logo-dark`, açıksa `logo-light` kullanılır.
- Yazı tipi vektöre (path) çevrilmiştir → her sunucuda aynı görünür, font yüklemeye gerek yok.

## 3. Renk Paleti

| Rol | Açık tema | Koyu tema | Nerede |
|---|---|---|---|
| Ana (primary) | `#0A1D3A` (lacivert) | `#12325C` | Header, buton metni, başlıklar |
| Vurgu (accent) | `#00C48C` (yeşil) | `#00C48C` | Butonlar, "güvenli" durum, CTA |
| Uyarı | `#F5A524` (turuncu) | `#F5A524` | 7 günden az kaldı |
| Kritik | `#E5484D` (kırmızı) | `#FF6369` | Süresi geçti |
| Bilgi | `#3B82F6` (mavi) | `#60A5FA` | Bilgilendirme |
| Zemin | `#F7F9FC` | `#081426` | Sayfa arka planı |
| Kart | `#FFFFFF` | `#0F2137` | Paneller, kartlar |
| Metin | `#0F172A` | `#E6EDF6` | Gövde metni |
| Soluk metin | `#6B7C93` | `#9DB4D0` | Açıklamalar, alt bilgi |
| Kenarlık | `#E3E9F2` | `#1E3450` | Çizgiler, input kenarları |

> Bu palet **varsayılan**dır. Panelden site rengi değiştirildiğinde CSS değişkenleri (`--brand-*`, `--accent-*`)
> üzerine yazılır ve açık/koyu tema için ayrı ayrı özelleştirilebilir.

## 4. Tipografi

| Kullanım | Yazı tipi | Ağırlık |
|---|---|---|
| Başlıklar | **Poppins** (logo ile aynı) | 600 / 700 |
| Gövde metni | **Inter** | 400 / 500 |
| Sayısal veri (tarih, plaka) | Inter / sistem mono | 500 |

- Tümü **SIL Open Font License** ile ücretsiz, ticari kullanıma açık.
- Fontlar sunucuda barındırılır (Google Fonts'a dış çağrı yok → KVKK/GDPR ve hız avantajı).
- Arapça/Farsça için RTL desteği + uygun Arapça font yedeklemesi tanımlanır.

## 5. Ton ve Dil

- **Sıcak, güven veren, sade.** Korkutucu resmî dil yok; "sistem senin yerine hatırlar" hissi.
- Kısa cümleler, net fiiller: "Aracını ekle", "Tarihleri gir", "Gerisini biz hatırlatalım."
- Terimler 6 dilde sözlükle sabitlenir (muayene, sigorta, yol vergisi, ruhsat).
- Kritik uyarılarda suçlayıcı değil, çözüm odaklı: "Sigortan 3 gün sonra bitiyor. Yenileme linki burada."

## 6. Marka Varlıkları (üretilecekler)

- `logo-*.svg` ✅ (hazır)
- `favicon.ico`, `apple-touch-icon.png`, `icon-192.png`, `icon-512.png`
- OpenGraph paylaşım görseli (1200×630) — panelden değiştirilebilir
- E-posta başlık görseli (600×140) — açık/koyu uyumlu
- Sosyal medya avatarları (kare) ve kapak görselleri
