#!/usr/bin/env bash
# Site asistanı + panel bilgi balonları.
# Gerçek HTTP; Gemini anahtarı olmadan yapılandırma/hata yollarını doğrular.
set -u
BASE="http://127.0.0.1:3001"
export HOME="${HOME:-/workspace/homes/9e03ed61266af80267d81f50}"
PHP="$HOME/bin/php/php"
J=/tmp/assistant_admin.txt; rm -f "$J"
OUT=/tmp/assistant_out; mkdir -p "$OUT"
ADMIN_EMAIL="admin@evrako.test"
ADMIN_PASS='TempAdmin123!'
pass=0; fail=0
ok(){ echo "  ✓ $1"; pass=$((pass+1)); }
bad(){ echo "  ✗ $1"; fail=$((fail+1)); }
has(){ grep -qF "$2" "$1" && ok "$1 içeriyor: $2" || bad "$1 eksik: $2"; }
nothas(){ grep -qF "$2" "$1" && bad "$1 içermemeliydi: $2" || ok "$1 beklendiği gibi: $2 yok"; }
code(){ curl -s -b "$J" -c "$J" -o "$2" -w '%{http_code}' "$1"; }
tok(){ grep -o 'name="_token" value="[^"]*"' "$1" | head -1 | sed 's/.*value="//;s/"$//'; }

MY="$HOME/mariadb/bin/mariadb --no-defaults --socket=$HOME/mysql-tmp/mysql.sock -uroot evrako_live -N -B"
OLD_HASH=$($MY -e "SELECT password_hash FROM users WHERE id=1")
NEW_HASH=$($PHP -r "echo password_hash('TempAdmin123!', PASSWORD_DEFAULT);")
$MY -e "UPDATE users SET password_hash='$NEW_HASH' WHERE id=1" >/dev/null
restore(){ $MY -e "UPDATE users SET password_hash='$OLD_HASH' WHERE id=1" >/dev/null 2>&1; }
OLD_KEY=$($MY -e "SELECT \`value\` FROM settings WHERE \`key\`='site_assistant.api_key' LIMIT 1")
set_key(){ $PHP -r "require 'app/bootstrap.php'; App\Core\Settings::set('site_assistant.api_key', '$1', 'site_assistant'); App\Core\Settings::set('site_assistant.enabled', '1', 'site_assistant');" >/dev/null; }
restore_all(){ set_key "$OLD_KEY"; restore; }
trap restore_all EXIT

# Anahtarsız faz: kurulu bir anahtar varsa geçici olarak boşalt (bitişte trap geri yükler).
set_key ""

echo "== asistan rol metni =="
PROMPT=$($PHP -r 'require "app/bootstrap.php"; echo App\Services\SiteAssistant::systemPrompt();')
for s in "490" "392" "343" "6 Ay Ücretsiz" "Muayene, sigorta, kasko" "halkla ilişkiler" "Kuzey Kıbrıs"; do
  echo "$PROMPT" | grep -qF "$s" && ok "rol metni içeriyor: $s" || bad "rol metni eksik: $s"
done
echo "$PROMPT" | grep -qF "Evrak başına veya evrak sayısına göre ASLA ek ücret yoktur" && ok "evrak ücretsizliği net" || bad "evrak ücretsizliği yok"

echo "== site: widget ve uç nokta (anahtar yokken) =="
CODE=$(curl -s -b "$J" -c "$J" -o "$OUT/home_nokey.html" -w '%{http_code}' "$BASE/")
[ "$CODE" = "200" ] && ok "ana sayfa 200" || bad "ana sayfa $CODE"
nothas "$OUT/home_nokey.html" 'data-site-assistant'
TOKEN=$(tok "$OUT/home_nokey.html")
[ -n "$TOKEN" ] && ok "csrf token bulundu" || bad "csrf token yok"

EMPTY=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' -X POST -F "_token=$TOKEN" -F "message=" "$BASE/asistan")
[ "$EMPTY" = "422" ] && ok "boş mesaj 422" || bad "boş mesaj $EMPTY"

NO_KEY=$(curl -s -b "$J" -c "$J" -o "$OUT/nokey.json" -w '%{http_code}' -X POST -F "_token=$TOKEN" -F "message=Merhaba" "$BASE/asistan")
[ "$NO_KEY" = "502" ] && ok "anahtarsız istek 502" || bad "anahtarsız istek $NO_KEY"
has "$OUT/nokey.json" 'çevrimdışı'

echo "== site: widget ve uç nokta (anahtar varken) =="
set_key "TEST-INVALID-KEY"
curl -s -b "$J" -c "$J" -o "$OUT/home.html" "$BASE/"
has "$OUT/home.html" 'data-site-assistant'
has "$OUT/home.html" 'Asistana sor'
has "$OUT/home.html" 'site-assistant__panel'
TOKEN=$(tok "$OUT/home.html")
[ -n "$TOKEN" ] && ok "csrf token (anahtarlı) bulundu" || bad "csrf token (anahtarlı) yok"

API=$(curl -s -b "$J" -c "$J" -o "$OUT/api.json" -w '%{http_code}' -X POST -F "_token=$TOKEN" -F "message=Merhaba, paketler neler?" "$BASE/asistan")
[ "$API" = "502" ] && ok "geçersiz anahtar nazik hata döner (502)" || bad "geçersiz anahtar $API"
has "$OUT/api.json" 'yanıt veremiyorum'

echo "== asistan yanıt veremediğinde WhatsApp yönlendirmesi =="
has "$OUT/home.html" 'site-assistant__contact'
has "$OUT/home.html" 'Dilerseniz buradan ekibimizle iletişime geçebilirsiniz.'
has "$OUT/home.html" 'Anlayışınız için teşekkür ederiz.'
has "$OUT/home.html" 'String(json.reply).trim() === T.noAnswer'
grep -qE 'var whatsapp = "(https://wa\.me/|http://127\.0\.0\.1:3001/iletisim)' "$OUT/home.html" \
  && ok "whatsapp bağlantısı dolu (numara yoksa iletişim sayfası)" || bad "whatsapp bağlantısı boş"
grep -qF 'if (whatsapp) {' "$OUT/home.html" && ok "bağlantı kartı yalnız adres varken basılır" || bad "contact kartı koşulu yok"

BADCSRF=$(curl -s -o /dev/null -w '%{http_code}' -X POST -F "_token=sagliksiz" -F "message=Merhaba" "$BASE/asistan")
[ "$BADCSRF" = "419" ] && ok "geçersiz CSRF 419" || bad "geçersiz CSRF $BADCSRF"

echo "== panel: bilgi balonları =="
curl -s -c "$J" -b "$J" "$BASE/giris" -o "$OUT/login.html"
LT=$(tok "$OUT/login.html")
curl -s -c "$J" -b "$J" -o /dev/null -X POST \
  --data-urlencode "_token=$LT" --data-urlencode "email=$ADMIN_EMAIL" --data-urlencode "password=$ADMIN_PASS" "$BASE/giris"

code "$BASE/yonetim/" "$OUT/dash.html" >/dev/null
has "$OUT/dash.html" 'info-tip__bubble'
has "$OUT/dash.html" 'Yönetim paneli'

code "$BASE/yonetim/slider/yeni" "$OUT/slide.html" >/dev/null
has "$OUT/slide.html" 'data-hint="Slayt metinleri'
has "$OUT/slide.html" 'info-tip__dot'

code "$BASE/yonetim/ayarlar/site_assistant" "$OUT/assistant_settings.html" >/dev/null
has "$OUT/assistant_settings.html" 'Site asistanı (Gemini)'
has "$OUT/assistant_settings.html" 'name="site_assistant_api_key"'
has "$OUT/assistant_settings.html" 'name="site_assistant_model"'
has "$OUT/assistant_settings.html" 'name="site_assistant_greeting"'
has "$OUT/assistant_settings.html" 'gemini-3.6-flash'
has "$OUT/assistant_settings.html" 'aistudio.google.com'

code "$BASE/panel" "$OUT/panel.html" >/dev/null
has "$OUT/panel.html" 'info-tip__bubble'

echo "SONUÇ: $pass geçti, $fail kaldı"
[ "$fail" -eq 0 ]
