#!/usr/bin/env bash
# Yönetim paneli: yeni ayar sekmeleri + kaydetme turu (geçici şifre ile, sonunda geri yüklenir)
set -u
BASE="http://127.0.0.1:3001"
export HOME="${HOME:-/workspace/homes/9e03ed61266af80267d81f50}"
PHP="$HOME/bin/php/php"
MYSQL="$HOME/mariadb/bin/mariadb --socket=$HOME/mysql-tmp/mysql.sock -uroot evrako_live -N -B"
J="/tmp/adm_j.txt"; rm -f "$J"
OUT=/tmp/adm_out; mkdir -p "$OUT"

pass=0; fail=0
ok(){ echo "  ✓ $1"; pass=$((pass+1)); }
bad(){ echo "  ✗ $1"; fail=$((fail+1)); }
check(){ [ "$2" = "$3" ] && ok "$1 ($2)" || bad "$1 — beklenen '$3', gelen '$2'"; }
has(){ grep -qF "$2" "$1" && ok "$1 içeriyor: $2" || bad "$1 eksik: $2"; }
tok(){ grep -o 'name="_token" value="[^"]*"' "$1" | head -1 | sed 's/.*value="//;s/"$//'; }

OLD=$($MYSQL -e "SELECT password_hash FROM users WHERE id=1")
NEW=$($PHP -r "echo password_hash('TempAdmin123!', PASSWORD_DEFAULT);")
$MYSQL -e "UPDATE users SET password_hash='$NEW' WHERE id=1" >/dev/null
restore(){ $MYSQL -e "UPDATE users SET password_hash='$OLD' WHERE id=1" >/dev/null; echo "  (yönetici şifresi geri yüklendi)"; }
trap restore EXIT

echo "== yönetici girişi =="
curl -s -c "$J" "$BASE/yonetim/giris" -o "$OUT/login.html"
T=$(tok "$OUT/login.html")
curl -s -b "$J" -c "$J" -o /dev/null --data-urlencode "_token=$T" \
  --data-urlencode "email=admin@evrako.test" --data-urlencode "password=TempAdmin123!" "$BASE/yonetim/giris"
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' "$BASE/yonetim/")
check "GET /yonetim/" "$CODE" "200"

echo "== yeni ayar sekmeleri =="
for tab in analytics cookie popup slider cta; do
  CODE=$(curl -s -b "$J" -c "$J" -o "$OUT/tab_$tab.html" -w '%{http_code}' "$BASE/yonetim/ayarlar/$tab")
  check "GET /yonetim/ayarlar/$tab" "$CODE" "200"
done
has "$OUT/tab_analytics.html" 'Analitik'
has "$OUT/tab_cookie.html" 'Çerez'
has "$OUT/tab_popup.html" 'Popup'
has "$OUT/tab_slider.html" 'Slider'
has "$OUT/tab_cta.html" 'Sabit CTA'

echo "== popup ayarını aç ve kaydet =="
T=$(tok "$OUT/tab_popup.html")
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$T" --data-urlencode "tab=popup" \
  --data-urlencode "popup_enabled=1" \
  --data-urlencode "popup_title=Yaz kampanyası" \
  --data-urlencode "popup_text=Yıllık planlarda %20 indirim bu ay boyunca geçerli." \
  --data-urlencode "popup_button_text=Kampanyayı gör" \
  --data-urlencode "popup_button_url=/fiyatlar" \
  --data-urlencode "popup_delay=2" \
  --data-urlencode "popup_frequency=session" \
  "$BASE/yonetim/ayarlar/kaydet")
check "POST ayarlar/kaydet (popup)" "$CODE" "302"
VAL=$($MYSQL -e "SELECT CONCAT(\`value\`,'|',(SELECT \`value\` FROM settings WHERE \`key\`='popup.title')) FROM settings WHERE \`key\`='popup.enabled'")
check "popup.enabled + popup.title kaydedildi" "$VAL" "1|Yaz kampanyası"

echo "== popup ön yüzde görünür =="
curl -s "$BASE/" -o "$OUT/home_popup.html"
has "$OUT/home_popup.html" 'data-popup'
has "$OUT/home_popup.html" 'Yaz kampanyası'
has "$OUT/home_popup.html" 'data-popup-backdrop'

echo "== temizlik: popup kapat =="
T=$(tok "$OUT/tab_popup.html")
curl -s -b "$J" -c "$J" -o /dev/null \
  --data-urlencode "_token=$T" --data-urlencode "tab=popup" \
  --data-urlencode "popup_title=Yaz kampanyası" \
  --data-urlencode "popup_delay=2" --data-urlencode "popup_frequency=session" \
  "$BASE/yonetim/ayarlar/kaydet"
VAL=$($MYSQL -e "SELECT \`value\` FROM settings WHERE \`key\`='popup.enabled'")
check "popup.enabled kapalı" "$VAL" "0"
curl -s "$BASE/" -o "$OUT/home_nopopup.html"
grep -qF 'data-popup' "$OUT/home_nopopup.html" && bad "popup kapalıyken hâlâ render ediliyor" || ok "popup kapalıyken render edilmiyor"

echo "== ödeme sekmesi: görüntüleme kurları (para birimi) =="
CODE=$(curl -s -b "$J" -c "$J" -o "$OUT/tab_payment.html" -w '%{http_code}' "$BASE/yonetim/ayarlar/payment")
check "GET /yonetim/ayarlar/payment" "$CODE" "200"
has "$OUT/tab_payment.html" 'name="payment_rate_usd"'
has "$OUT/tab_payment.html" 'name="payment_rate_eur"'
has "$OUT/tab_payment.html" 'name="payment_rate_gbp"'
has "$OUT/tab_payment.html" 'Kurlar elle girilir'

# Ödeme grubunun tamamını yedekle: form kısmi gönderilirse diğer alanlar boşalır,
# test gerçek formu (tüm alanlarla) gönderir ve sonunda yedekten geri yükler.
PAYDUMP=/tmp/adm_payment_before.sql
$MYSQL -e "SELECT CONCAT('UPDATE settings SET \`value\`=', QUOTE(\`value\`), ', is_json=', is_json, ' WHERE \`key\`=', QUOTE(\`key\`), ';') FROM settings WHERE \`group\`='payment'" > "$PAYDUMP"
val(){ $MYSQL -e "SELECT \`value\` FROM settings WHERE \`key\`='$1'"; }
BANK_NOTE=$(val 'payment.bank_note'); BANK_IBAN=$(val 'payment.bank_iban'); BANK_OWNER=$(val 'payment.bank_owner')
PRESETS=$(val 'payment.topup_presets'); PRESETS=${PRESETS//\[/}; PRESETS=${PRESETS//\]/}; PRESETS=${PRESETS//\"/}
IYZ_KEY=$(val 'payment.iyzico_api_key'); IYZ_BASE=$(val 'payment.iyzico_base')
CUR=$(val 'payment.currency'); PROVIDER=$(val 'payment.provider')
MIN_TOPUP=$(val 'payment.min_topup'); TTL=$(val 'payment.intent_ttl_hours'); BANK_ON=$(val 'payment.bank_enabled')
RATE_USD=$(val 'payment.rate_usd'); RATE_EUR=$(val 'payment.rate_eur'); RATE_GBP=$(val 'payment.rate_gbp')

echo "  (mevcut kur: USD=$RATE_USD EUR=$RATE_EUR GBP=$RATE_GBP)"
T=$(tok "$OUT/tab_payment.html")
SEND=(--data-urlencode "_token=$T" --data-urlencode "tab=payment"
  --data-urlencode "payment_provider=$PROVIDER" --data-urlencode "payment_currency=$CUR"
  --data-urlencode "payment_bank_enabled=$BANK_ON"
  --data-urlencode "payment_min_topup=$MIN_TOPUP" --data-urlencode "payment_intent_ttl_hours=$TTL"
  --data-urlencode "payment_rate_usd=40.5" --data-urlencode "payment_rate_eur=$RATE_EUR" --data-urlencode "payment_rate_gbp=$RATE_GBP"
  --data-urlencode "payment_bank_note=$BANK_NOTE" --data-urlencode "payment_topup_presets=$PRESETS"
  --data-urlencode "payment_bank_iban=$BANK_IBAN" --data-urlencode "payment_bank_owner=$BANK_OWNER"
  --data-urlencode "payment_iyzico_api_key=$IYZ_KEY" --data-urlencode "payment_iyzico_base=$IYZ_BASE")
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' "${SEND[@]}" "$BASE/yonetim/ayarlar/kaydet")
check "POST ayarlar/kaydet (ödeme)" "$CODE" "302"
check "payment.rate_usd kaydedildi" "$(val 'payment.rate_usd')" "40.5"
check "payment.currency korundu" "$(val 'payment.currency')" "$CUR"
check "payment.bank_iban korundu" "$(val 'payment.bank_iban')" "$BANK_IBAN"

# Kur değişince sitede dönüşüm de değişir: 490 TRY / 40.5 = $12,10
PRICE=$(curl -s "$BASE/fiyatlar?para=USD" | sed 's/<[^>]*>//g' | grep -o '\$[0-9,]*' | head -1)
check "yeni kura göre USD fiyat" "$PRICE" "\$12,10"

# Yedekten geri yükle
$MYSQL < "$PAYDUMP" >/dev/null 2>&1
PAYDUMP=/tmp/adm_payment_2.sql
$MYSQL -e "SELECT CONCAT('UPDATE settings SET \`value\`=', QUOTE(\`value\`), ', is_json=', is_json, ' WHERE \`key\`=', QUOTE(\`key\`), ';') FROM settings WHERE \`group\`='payment'" > "$PAYDUMP"
check "kur geri yüklendi (USD)" "$(val 'payment.rate_usd')" "$RATE_USD"
check "ödemeler ayarı bozulmadı" "$(val 'payment.currency')" "$CUR"

# Karşılaştırma için ilk yedekteki satır sayısı
BEFORE_LINES=$(grep -c 'UPDATE settings' /tmp/adm_payment_before.sql)
AFTER_LINES=$(grep -c 'UPDATE settings' "$PAYDUMP")
check "ödeme ayar satır sayısı korundu" "$AFTER_LINES" "$BEFORE_LINES"

# Test kirliliği: geçici yedek dosyaları
rm -f "$PAYDUMP"

echo
echo "SONUÇ: $pass geçti, $fail kaldı"
exit $fail
