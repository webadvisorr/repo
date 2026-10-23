#!/usr/bin/env bash
# Genel site (public) özellik testi — SEO, yasal sayfalar, formlar, slider, çerez bandı, hata sayfaları
set -u
BASE="http://127.0.0.1:3001"
export HOME="${HOME:-/workspace/homes/9e03ed61266af80267d81f50}"
MYSQL="$HOME/mariadb/bin/mariadb --socket=$HOME/mysql-tmp/mysql.sock -uroot evrako_live -N -B"
PHP="$HOME/bin/php/php"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
J="/tmp/pub_j.txt"; rm -f "$J"
OUT=/tmp/pub_out; mkdir -p "$OUT"

pass=0; fail=0
ok(){ echo "  ✓ $1"; pass=$((pass+1)); }
bad(){ echo "  ✗ $1"; fail=$((fail+1)); }
check(){ [ "$2" = "$3" ] && ok "$1 ($2)" || bad "$1 — beklenen '$3', gelen '$2'"; }
has(){ grep -qF "$2" "$1" && ok "$1 inneriyor: $2" || bad "$1 eksik: $2"; }
nothas(){ grep -qF "$2" "$1" && bad "$1 içermemeliydi: $2" || ok "$1 beklendiği gibi: $2 yok"; }
tok(){ grep -o 'name="_token" value="[^"]*"' "$1" | head -1 | sed 's/.*value="//;s/"$//'; }

echo "== sayfa durum kodları =="
for p in "/" "/nasil-calisir" "/fiyatlar" "/iletisim" "/gizlilik" "/kullanim-kosullari"; do
  CODE=$(curl -s -o "$OUT/p.html" -w '%{http_code}' "$BASE$p")
  check "GET $p" "$CODE" "200"
done

echo "== ana sayfa: slider + CTA + güven şeridi =="
curl -s "$BASE/" -o "$OUT/home.html"
has "$OUT/home.html" 'data-slider'
has "$OUT/home.html" 'slider__track'
has "$OUT/home.html" 'slider__progress'
has "$OUT/home.html" 'slide__actions'
has "$OUT/home.html" 'trust-strip'
has "$OUT/home.html" 'data-open-drawer' # header CTA fallback
grep -q 'class="btn btn-accent"' "$OUT/home.html" && ok "Slider CTA butonu var" || bad "Slider CTA butonu yok"

echo "== SEO =="
has "$OUT/home.html" '<link rel="canonical"'
has "$OUT/home.html" 'hreflang="x-default"'
has "$OUT/home.html" 'application/ld+json'
has "$OUT/home.html" 'og:image'
grep -q 'og:image" content="https\?://' "$OUT/home.html" && ok "og:image mutlak adres" || bad "og:image mutlak değil"
curl -s "$BASE/iletisim" -o "$OUT/contact.html"
grep -q '<link rel="canonical" href="[^"]*/iletisim"' "$OUT/contact.html" && ok "İletişim canonical sayfa bazlı" || bad "İletişim canonical hatalı"
grep -q 'name="description" content="[^"]*Sorularınız' "$OUT/contact.html" && ok "İletişim meta açıklama" || bad "İletişim meta açıklama yok"

echo "== robots.txt / sitemap.xml =="
HDR=$(curl -s -D - -o "$OUT/robots.txt" "$BASE/robots.txt")
echo "$HDR" | grep -qi 'Content-Type: text/plain' && ok "robots.txt text/plain" || bad "robots.txt içerik tipi hatalı"
has "$OUT/robots.txt" 'Sitemap:'
has "$OUT/robots.txt" 'Disallow: /panel'
HDR=$(curl -s -D - -o "$OUT/sitemap.xml" "$BASE/sitemap.xml")
echo "$HDR" | grep -qi 'Content-Type: application/xml' && ok "sitemap.xml application/xml" || bad "sitemap.xml içerik tipi hatalı"
has "$OUT/sitemap.xml" '<urlset'
has "$OUT/sitemap.xml" '/kullanim-kosullari'

echo "== özel 404 (layout ile) =="
CODE=$(curl -s -o "$OUT/404.html" -w '%{http_code}' "$BASE/boyle-bir-sayfa-yok")
check "GET /boyle-bir-sayfa-yok" "$CODE" "404"
has "$OUT/404.html" '<html'
has "$OUT/404.html" 'error-code'
has "$OUT/404.html" '</footer>'
has "$OUT/404.html" 'error-link'

echo "== çerez bandı + sabit CTA + floaters =="
has "$OUT/home.html" 'data-cookie-banner'
has "$OUT/home.html" 'data-cookie-accept'
has "$OUT/home.html" 'data-cookie-decline'
has "$OUT/home.html" 'data-sticky-cta'
has "$OUT/home.html" 'data-scroll-progress'
has "$OUT/home.html" 'data-back-to-top'
has "$OUT/home.html" 'skip-link'

echo "== form doğrulama / loading =="
has "$OUT/home.html" 'data-form-validate'
has "$OUT/contact.html" 'data-loading-text'
has "$OUT/contact.html" 'data-char-count-for'

echo "== iletişim formu: geçersiz gönderim -> geri =="
curl -s -b "$J" -c "$J" "$BASE/iletisim" -o "$OUT/contact.html"; T=$(tok "$OUT/contact.html")
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$T" --data-urlencode "name=x" --data-urlencode "email=gecersiz" \
  --data-urlencode "message=kisa" "$BASE/iletisim")
check "POST geçersiz iletişim" "$CODE" "302"
curl -s -b "$J" -c "$J" "$BASE/iletisim" -o "$OUT/contact2.html"
has "$OUT/contact2.html" 'form-error'
has "$OUT/contact2.html" 'has-error'

echo "== iletişim formu: geçerli gönderim -> teşekkürler =="
curl -s -c "$J" "$BASE/iletisim" -o "$OUT/contact3.html"; T=$(tok "$OUT/contact3.html")
BEFORE=$($MYSQL -e "SELECT COUNT(*) FROM contact_messages")
LOC=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{redirect_url}' \
  --data-urlencode "_token=$T" --data-urlencode "name=Test Ziyaretçi" --data-urlencode "email=ziyaretci@example.com" \
  --data-urlencode "message=Bu bir otomatik test mesajıdır, en az on karakter." "$BASE/iletisim")
echo "  (yönlendirme: $LOC)"
CODE=$(curl -s -b "$J" -c "$J" -o "$OUT/thanks.html" -w '%{http_code}' "$BASE/tesekkurler")
check "GET /tesekkurler" "$CODE" "200"
has "$OUT/thanks.html" 'thanks-check'
has "$OUT/thanks.html" 'Test Ziyaretçi'
AFTER=$($MYSQL -e "SELECT COUNT(*) FROM contact_messages")
[ "$AFTER" -gt "$BEFORE" ] && ok "Mesaj veritabanına kaydedildi ($BEFORE -> $AFTER)" || bad "Mesaj kaydedilmedi"

echo "== teşekkür sayfasına doğrudan erişim (oturum yok) =="
CODE=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/tesekkurler")
check "GET /tesekkurler (session yok)" "$CODE" "302"

echo "== footer: dil + para birimi seçicileri =="
curl -s "$BASE/" -o "$OUT/home.html"
has "$OUT/home.html" 'footer-prefs'
has "$OUT/home.html" 'data-language-select'
has "$OUT/home.html" 'data-currency-select'
has "$OUT/home.html" 'id="currency-select"'
LANG_LINE=$(grep -n 'data-language-select' "$OUT/home.html" | head -1 | cut -d: -f1)
HEADER_END=$(grep -n '</header>' "$OUT/home.html" | head -1 | cut -d: -f1)
FOOTER_START=$(grep -n '<footer' "$OUT/home.html" | head -1 | cut -d: -f1)
[ "$LANG_LINE" -gt "$HEADER_END" ] && ok "Dil seçici header dışında (footer'da)" || bad "Dil seçici hâlâ header'da"
[ "$LANG_LINE" -gt "$FOOTER_START" ] && ok "Dil seçici footer içinde" || bad "Dil seçici footer'da değil"
CURR_LINE=$(grep -n 'data-currency-select' "$OUT/home.html" | head -1 | cut -d: -f1)
[ "$CURR_LINE" -gt "$FOOTER_START" ] && ok "Para birimi seçici footer içinde" || bad "Para birimi seçici footer'da değil"
DIST=$((CURR_LINE > LANG_LINE ? CURR_LINE - LANG_LINE : LANG_LINE - CURR_LINE))
[ "$DIST" -le 12 ] && ok "İki seçici yan yana ($DIST satır arayla)" || bad "Seçiciler birbirinden uzak ($DIST satır)"
grep -q '<option value="TRY"' "$OUT/home.html" && ok "TRY seçeneği var" || bad "TRY seçeneği yok"
grep -q '<option value="USD"' "$OUT/home.html" && ok "USD seçeneği var" || bad "USD seçeneği yok"
grep -q '<option value="EUR"' "$OUT/home.html" && ok "EUR seçeneği var" || bad "EUR seçeneği yok"
grep -q '<option value="GBP"' "$OUT/home.html" && ok "GBP seçeneği var" || bad "GBP seçeneği yok"

# Header'da dil seçici kalmadı mı (tek örnek olmalı)
COUNT=$(grep -o 'data-language-select' "$OUT/home.html" | wc -l | tr -d ' ')
check "Dil seçici tek örnek" "$COUNT" "1"

JQ=/tmp/pub_cur.txt; rm -f "$JQ"
echo "== para birimi: varsayılan (taban) =="
curl -s -c "$JQ" -b "$JQ" "$BASE/fiyatlar" -o "$OUT/price_try.html"
has "$OUT/price_try.html" '490 ₺'
grep -q '<option value="TRY" selected' "$OUT/price_try.html" && ok "TRY seçili" || bad "TRY seçili değil"
grep -q 'Fiyatlar USD' "$OUT/price_try.html" && bad "Taban birimdeyken döviz dipnotu gösterilmemeli" || ok "Taban birimde dipnot yok"

echo "== para birimi: ?para=USD (dönüşüm + çerez) =="
CODE=$(curl -s -c "$JQ" -b "$JQ" -o "$OUT/price_usd.html" -w '%{http_code}' "$BASE/fiyatlar?para=USD")
check "GET /fiyatlar?para=USD" "$CODE" "200"
has "$OUT/price_usd.html" '$9,89'
has "$OUT/price_usd.html" '$7,91'
grep -q 'Fiyatlar USD cinsinden yaklaşık gösterilir' "$OUT/price_usd.html" && ok "Döviz dipnotu gösteriliyor" || bad "Döviz dipnotu yok"
grep -q '1 USD = 49,57 TRY' "$OUT/price_usd.html" && ok "Kur dipnotta yazıyor" || bad "Kur dipnotta yok"
grep -q '<option value="USD" selected' "$OUT/price_usd.html" && ok "USD seçili" || bad "USD seçili değil"
grep -q 'evrako_currency' "$JQ" && ok "Seçim çereze yazıldı" || bad "Çerez yazılmadı"

curl -s -c "$JQ" -b "$JQ" "$BASE/fiyatlar" -o "$OUT/price_usd2.html"
has "$OUT/price_usd2.html" '$9,89'
curl -s -c "$JQ" -b "$JQ" "$BASE/" -o "$OUT/home_usd.html"
has "$OUT/home_usd.html" '$9,89'
grep -q 'Fiyatlar USD' "$OUT/home_usd.html" && ok "Ana sayfa da döviz dipnotu gösteriyor" || bad "Ana sayfada dipnot yok"

# Dil değişimi para birimi seçimini bozmamalı
curl -s -c "$JQ" -b "$JQ" "$BASE/fiyatlar?dil=en&para=USD" -o "$OUT/price_usd_en.html"
has "$OUT/price_usd_en.html" '$9,89'
grep -q 'Prices are shown approximately in USD' "$OUT/price_usd_en.html" && ok "İngilizce dipnot çevrildi" || bad "İngilizce dipnot hatalı"

JB=/tmp/pub_cur_bad.txt; rm -f "$JB"
curl -s -c "$JB" -b "$JB" "$BASE/fiyatlar?para=XYZ" -o "$OUT/price_bad.html"
has "$OUT/price_bad.html" '490 ₺'
grep -q '<option value="TRY" selected' "$OUT/price_bad.html" && ok "Geçersiz birim yok sayıldı" || bad "Geçersiz birim uygulandı"

# Taban birime dönüş (çerez güncellenir)
curl -s -c "$JQ" -b "$JQ" "$BASE/fiyatlar?para=TRY&dil=tr" -o "$OUT/price_back.html"
grep -q '<option value="TRY" selected' "$OUT/price_back.html" && ok "Taban birime dönüş çalıştı" || bad "Taban birime dönülemedi"

# Kur 0 ise birim seçicide görünmez
RATE_EUR=$($MYSQL -e "SELECT \`value\` FROM settings WHERE \`key\`='payment.rate_eur'")
$MYSQL -e "UPDATE settings SET \`value\`='0' WHERE \`key\`='payment.rate_eur'" >/dev/null
curl -s -c "$JB" -b "$JB" "$BASE/fiyatlar" -o "$OUT/price_norate.html"
grep -q '<option value="EUR"' "$OUT/price_norate.html" && bad "Kuru 0 olan birim listede" || ok "Kuru 0 olan birim listede değil"
$MYSQL -e "UPDATE settings SET \`value\`='$RATE_EUR' WHERE \`key\`='payment.rate_eur'" >/dev/null
check "EUR kuru geri yüklendi" "$($MYSQL -e "SELECT \`value\` FROM settings WHERE \`key\`='payment.rate_eur'")" "$RATE_EUR"

echo "== koyu tema: kontrast + okunmayan butonlar =="
curl -s "$BASE/assets/css/app.css" -o "$OUT/app.css"
has "$OUT/app.css" '.btn-light { background: #fff; color: #0A1D3A;'
grep -q 'data-theme="dark"\] .btn-light' "$OUT/app.css" && ok "btn-light koyu tema kuralı var" || bad "btn-light koyu tema kuralı yok"
grep -qF '.slider__arrow' "$OUT/app.css" && bad "slider okları hâlâ CSS'te tanımlı" || ok "slider sağ/sol okları kaldırıldı"
grep -q 'data-theme="dark"\] .btn-danger' "$OUT/app.css" && ok "btn-danger koyu tema kuralı var" || bad "btn-danger koyu tema kuralı yok"
grep -qF 'style="background:#fff"' "$OUT/home.html" && bad "CTA bandında hâlâ inline beyaz buton var" || ok "CTA bandı inline beyaz buton kaldırıldı"
has "$OUT/home.html" 'class="btn btn-light"'

curl -s "$BASE/assets/js/app.js" -o "$OUT/app.js"
grep -q 'data-currency-select' "$OUT/app.js" && ok "app.js para birimi seçicisini dinliyor" || bad "app.js para birimi seçicisi yok"
grep -q "url.searchParams.set('para'" "$OUT/app.js" && ok "app.js ?para= parametresini yazıyor" || bad "app.js para parametresi yok"

$PHP "$ROOT/.freebuff/contrast_check.php" > "$OUT/contrast.txt" 2>&1; CONTRAST=$?
check "kontrast denetimi çıkış kodu" "$CONTRAST" "0"
has "$OUT/contrast.txt" 'KONTRAST: TAMAM'
grep -qF '.slider__arrow' "$OUT/contrast.txt" && bad "kontrast listesinde kaldırılan ok stili var" || ok "kontrast listesinde ok stili yok"

echo "== slider kontrolleri + yüzen butonlar (animasyon/kaydırma) =="
grep -qF '.slider__controls {' "$OUT/app.css" && ok "slider nokta kontrolleri ortada" || bad "slider__controls CSS yok"
grep -qF '.slider__dot.is-active' "$OUT/app.css" && ok "slider nokta göstergesi stili var" || bad "slider nokta stili yok"
grep -qF '@keyframes waPulse' "$OUT/app.css" && ok "WhatsApp nabız animasyonu tanımlı" || bad "waPulse animasyonu yok"
grep -qF 'body.is-scrolled' "$OUT/app.css" && ok "kaydırma durumu CSS'te ele alınıyor" || bad "is-scrolled CSS yok"
grep -qF 'body.has-sticky-cta' "$OUT/app.css" && ok "sabit CTA yükseklik telafisi var" || bad "has-sticky-cta CSS yok"
grep -qF 'slider__controls' "$OUT/app.js" && ok "app.js kontrolleri tek satırda kuruyor" || bad "app.js slider__controls yok"
grep -qF 'slider__arrow' "$OUT/app.js" && bad "app.js hâlâ ok düğmesi üretiyor" || ok "app.js sağ/sol ok düğmesi üretmiyor"
grep -qF 'slider__dot' "$OUT/app.js" && ok "app.js nokta göstergelerini üretiyor" || bad "app.js nokta göstergesi yok"
grep -qF "classList.toggle('is-scrolled'" "$OUT/app.js" && ok "app.js kaydırmada is-scrolled ekliyor" || bad "app.js is-scrolled yok"

echo "== mobil (burger) menü içeriği =="
sed -n '/id="mobile-menu"/,/^<\/aside>/p' "$OUT/home.html" > "$OUT/mobile_menu.html"
has "$OUT/mobile_menu.html" 'mobile-menu__brand'
has "$OUT/mobile_menu.html" 'class="brand"'
grep -qE 'brand__logo|viewBox="0 0 64 64"' "$OUT/mobile_menu.html" && ok "mobil menüde site logosu var" || bad "mobil menüde site logosu yok"
nothas "$OUT/mobile_menu.html" '/gizlilik'
nothas "$OUT/mobile_menu.html" '/kullanim-kosullari'
nothas "$OUT/mobile_menu.html" 'WhatsApp'
nothas "$OUT/mobile_menu.html" 'mailto:'
nothas "$OUT/mobile_menu.html" 'tel:'
grep -qF "classList.toggle('has-sticky-cta'" "$OUT/app.js" && ok "app.js CTA görünürken has-sticky-cta ekliyor" || bad "app.js has-sticky-cta yok"
grep -qF 'backToTop.hidden' "$OUT/app.js" && bad "app.js hâlâ hidden ile gizliyor (animasyon çalışmaz)" || ok "yukarı-dön görünürlüğü sınıfla yönetiliyor"
grep -qF 'data-back-to-top aria-label="Yukarı dön" title="Yukarı dön">' "$OUT/home.html" && ok "yukarı-dön butonu hidden özniteliği olmadan render ediliyor" || bad "yukarı-dön hidden özniteliği hâlâ var"

echo "== hidden katmanları tıklamayı engellemesin (sabit CTA / çerez bandı) =="
grep -qF '[hidden] { display: none !important; }' "$OUT/app.css" && ok "[hidden] gerçekten gizliyor (display reset)" || bad "[hidden] reset yok — görünmez katman tıklamayı yer"
grep -qF '.cookie-banner:not(.is-visible)' "$OUT/app.css" && ok "görünmeyen çerez bandı tıklamayı engellemiyor" || bad "çerez bandı pointer-events koruması yok"
grep -qF '.sticky-cta:not(.is-visible)' "$OUT/app.css" && ok "görünmeyen sabit CTA tıklamayı engellemiyor" || bad "sabit CTA pointer-events koruması yok"
grep -qF 'class="cookie-banner"' "$OUT/home.html" && ok "çerez bandı render ediliyor" || bad "çerez bandı yok"
grep -qF 'class="sticky-cta"' "$OUT/home.html" && ok "sabit CTA render ediliyor" || bad "sabit CTA yok"

echo "== KKTC uyarlaması: plakalar, evrak türleri, koyu tema açılır liste =="
grep -qF 'AA 123 A' "$OUT/home.html" && ok "Örnek plakalar KKTC biçiminde (AA 123 A)" || bad "Örnek plakalar güncellenmedi"
grep -qF 'Ruhsat/poliçe' "$OUT/home.html" && bad "Ana sayfada hâlâ 'Ruhsat/poliçe' var" || ok "Ana sayfada 'Ruhsat/poliçe' yok"
curl -s "$BASE/nasil-calisir" -o "$OUT/how.html"
grep -qiE 'emüasyon|müasyon' "$OUT/how.html" && bad "nasıl çalışır'da hâlâ 'emüasyon' var" || ok "'emüasyon' kaldırıldı"
grep -qi 'yol vergisi' "$OUT/how.html" && bad "nasıl çalışır'da hâlâ 'yol vergisi' var" || ok "'yol vergisi' -> 'Seyrüsefer' güncellendi"
grep -qF 'Seyrüsefer' "$OUT/how.html" && ok "nasıl çalışır'da Seyrüsefer geçiyor" || bad "Seyrüsefer bulunamadı"
grep -qi 'işletme / kiralama' "$OUT/how.html" && bad "nasıl çalışır'da 'İşletme / Kiralama' hâlâ var" || ok "İşletme/Kiralama listesi kaldırıldı"
grep -qF 'Hatırlatmayı sistem ayarlar' "$OUT/how.html" && ok "Hatırlatma adımı otomatik dille yazıldı" || bad "Hatırlatma adımı güncellenmedi"
grep -qF 'Dilerseniz 30/14 gibi' "$OUT/how.html" && bad "Eski 'kendi aralığınızı seçin' metni hâlâ duruyor" || ok "Eski hatırlatma seçim metni kaldırıldı"
grep -qF 'select option { background: var(--c-card); color: var(--c-text); }' "$OUT/app.css" && ok "Koyu temada açılır liste okunur (option rengi)" || bad "option renk düzeltmesi yok"

echo "== sosyal logolar, banka havalesi, adres ve slider yerleşimi =="
grep -qF 'class="social-links"' "$OUT/home.html" && ok "Sosyal medya bağlantıları render ediliyor" || bad "Sosyal medya bağlantıları yok"
grep -qF 'class="icon icon--facebook"' "$OUT/home.html" && ok "Facebook logosu SVG olarak basılıyor" || bad "Facebook logosu yok"
grep -qF 'class="icon icon--instagram"' "$OUT/home.html" && ok "Instagram logosu SVG olarak basılıyor" || bad "Instagram logosu yok"
grep -qF 'class="icon icon--youtube"' "$OUT/home.html" && ok "YouTube logosu SVG olarak basılıyor" || bad "YouTube logosu yok"
grep -qE 'social-links[^$]*>Facebook<' "$OUT/home.html" && bad "Sosyal medyada hâlâ yazı etiketi var" || ok "Sosyal medya yazı yerine logo ile gösteriliyor"
grep -qF 'https://www.facebook.com/evrako' "$OUT/home.html" && ok "Facebook kullanıcı adı profile çevrildi" || bad "Facebook bağlantısı hatalı"
grep -qF 'class="icon icon--whatsapp"' "$OUT/contact.html" && ok "İletişimde gerçek WhatsApp ikonu kullanılıyor" || bad "WhatsApp ikonu SVG değil"
grep -qF 'https://wa.me/905338276161' "$OUT/contact.html" && ok "WhatsApp bağlantısı uluslararası biçimde (90533…)" || bad "WhatsApp numarası wa.me için normalize edilmedi"
grep -qF 'Kuzey Kıbrıs' "$OUT/contact.html" && ok "İletişim adresi 'Kuzey Kıbrıs' olarak güncellendi" || bad "Kuzey Kıbrıs adresi yok"
grep -qiF 'Ahmet kalfa' "$OUT/contact.html" && bad "Sokak adresi hâlâ sitede" || ok "Sokak adresi siteden kaldırıldı"
grep -qF 'Ödemeler banka havalesi veya kredi bakiyesi ile alınmaktadır.' "$OUT/price_back.html" && ok "Fiyat notu havale + kredi ödemesini söylüyor" || bad "Ödeme yöntemi notu güncel değil"
grep -qiF 'iyzico' "$OUT/price_back.html" && bad "Fiyat notunda hâlâ iyzico geçiyor" || ok "iyzico metni kaldırıldı"
grep -qF 'Hatırlatma planını seç' "$OUT/home.html" && bad "Ana sayfada hatırlatma planı adımı hâlâ var" || ok "Hatırlatma planı adımı kaldırıldı"
grep -qF 'Üç adımda kurulum' "$OUT/home.html" && ok "Adım sayısı üçe güncellendi" || bad "Adım sayısı metni güncellenmedi"
grep -qF '.slide__content { padding: 30px 20px 104px; }' "$OUT/app.css" && ok "Mobil slider metni kontroller için boşluk bırakıyor" || bad "Mobil slider boşluğu yok"
grep -qF '@media (max-width: 700px)' "$OUT/app.css" && ok "Mobil slider düzeltmesi tanımlı" || bad "Mobil slider medya sorgusu yok"

echo "== varlıklar =="
CODE=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/assets/js/app.js")
check "GET app.js" "$CODE" "200"
CODE=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/assets/brand/og-default.svg")
check "GET og-default.svg" "$CODE" "200"
CODE=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/assets/css/rtl.css")
check "GET rtl.css" "$CODE" "200"
# asset_version yapılandırmadan okunur (sabit sürüme bağımlı değil)
AV=$($PHP -r "\$c = require '$ROOT/config/config.php'; echo \$c['app']['asset_version'] ?? '1';" 2>/dev/null)
[ -n "$AV" ] || AV=1
grep -qE 'v=[0-9]+' "$OUT/home.html" && ok "asset sürüm parametresi uygulanmış (v=$AV)" || bad "asset sürüm parametresi yok"

echo
echo "SONUÇ: $pass geçti, $fail kaldı"
exit $fail
