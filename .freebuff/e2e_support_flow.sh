#!/usr/bin/env bash
# Destek akışı: müşteri talep açar → yanıt gelene kadar yeni talep açamaz ve
# mesaj yazamaz → yanıt gelince yanıtlar / kapatır / yeni talep açar.
set -u
BASE="http://127.0.0.1:3001"
ROOT="/workspace/chats/9e03ed61266af80267d81f50"
export HOME="${HOME:-/workspace/homes/9e03ed61266af80267d81f50}"
PHP="$HOME/bin/php/php"
MY() { "$HOME/mariadb/bin/mariadb" --no-defaults --socket="$HOME/mysql-tmp/mysql.sock" -uroot evrako_live -N -B -e "$1"; }
OUT=/tmp/support_out; mkdir -p "$OUT"
STAMP=$(date +%s)
EMAIL="sup-$STAMP@evrako.test"
PASS='SupPass123!'
USER_ID=""; ORG_ID=""
J=/tmp/sup_cust.jar; rm -f "$J"
pass=0; fail=0
ok(){ echo "  ✓ $1"; pass=$((pass+1)); }
bad(){ echo "  ✗ $1"; fail=$((fail+1)); }
check(){ [ "$2" = "$3" ] && ok "$1 ($2)" || bad "$1 — beklenen '$3', gelen '$2'"; }
has(){ grep -qF "$2" "$1" && ok "$(basename "$1") içeriyor: $2" || bad "$(basename "$1") eksik: $2"; }
nothas(){ grep -qF "$2" "$1" && bad "$(basename "$1") içermemeliydi: $2" || ok "$(basename "$1") beklendiği gibi: $2 yok"; }
tok(){ grep -o 'name="_token" value="[^"]*"' "$1" | head -1 | sed 's/.*value="//;s/"$//'; }
loc(){ grep -i '^location:' "$1" | tr -d '\r' | sed 's/^[Ll]ocation: *//'; }

cleanup(){
  [ -n "$USER_ID" ] && MY "DELETE FROM support_ticket_messages WHERE ticket_id IN (SELECT id FROM support_tickets WHERE user_id=$USER_ID)" >/dev/null 2>&1
  [ -n "$USER_ID" ] && MY "DELETE FROM support_tickets WHERE user_id=$USER_ID" >/dev/null 2>&1
  [ -n "$ORG_ID" ] && MY "DELETE FROM organizations WHERE id=$ORG_ID" >/dev/null 2>&1
  [ -n "$USER_ID" ] && MY "DELETE FROM user_notifications WHERE user_id=$USER_ID" >/dev/null 2>&1
  [ -n "$USER_ID" ] && MY "DELETE FROM users WHERE id=$USER_ID" >/dev/null 2>&1
}
trap cleanup EXIT

echo "== hazırlık =="
HASH=$($PHP -r "echo password_hash('$PASS', PASSWORD_DEFAULT);")
USER_ID=$(MY "INSERT INTO users (full_name,email,password_hash,role,status,locale,email_verified_at,created_at) VALUES ('Destek Test','$EMAIL','$HASH','customer','active','tr',NOW(),NOW()); SELECT LAST_INSERT_ID();")
ORG_ID=$(MY "INSERT INTO organizations (owner_user_id,name,type,created_at) VALUES ($USER_ID,'Destek Test $STAMP','individual',NOW()); SELECT LAST_INSERT_ID();")
MY "INSERT INTO organization_members (organization_id,user_id,role,status,created_at) VALUES ($ORG_ID,$USER_ID,'owner','active',NOW())" >/dev/null
curl -s -c "$J" "$BASE/giris" -o "$OUT/login.html"
UT=$(tok "$OUT/login.html")
curl -s -b "$J" -c "$J" -o /dev/null --data-urlencode "_token=$UT" \
  --data-urlencode "email=$EMAIL" --data-urlencode "password=$PASS" "$BASE/giris"

echo "== ilk talep açılır =="
check "yeni talep formu açık" "$(curl -s -b "$J" -c "$J" -o "$OUT/form.html" -w '%{http_code}' "$BASE/panel/destek/yeni")" "200"
FT=$(tok "$OUT/form.html")
curl -s -b "$J" -c "$J" -D "$OUT/create.hdr" -o /dev/null --data-urlencode "_token=$FT" \
  --data-urlencode "subject=E2E destek konusu" --data-urlencode "body=Bu bir uçtan uca destek talebi denemesidir." \
  --data-urlencode "category=technical" "$BASE/panel/destek"
TICKET_LOC=$(loc "$OUT/create.hdr")
check "talep sayfasına yönlendirildi" "$(echo "$TICKET_LOC" | sed 's#/panel/destek/[0-9]*#/panel/destek/{id}#')" "/panel/destek/{id}"
TID=$(echo "$TICKET_LOC" | sed 's#.*/##')
check "talep açık durumda" "$(MY "SELECT status FROM support_tickets WHERE id=$TID")" "open"
check "tek talep var" "$(MY "SELECT COUNT(*) FROM support_tickets WHERE user_id=$USER_ID")" "1"

echo "== yanıt gelene kadar kilitli =="
curl -s -b "$J" -c "$J" "$BASE/panel/destek/$TID" -o "$OUT/ticket_waiting.html"
has "$OUT/ticket_waiting.html" 'Destek ekibi yanıtı bekleniyor'
nothas "$OUT/ticket_waiting.html" 'name="body"'
check "yeni talep formu engelli" "$(curl -s -b "$J" -c "$J" -D "$OUT/blocked.hdr" -o /dev/null "$BASE/panel/destek/yeni"; loc "$OUT/blocked.hdr")" "/panel/destek/$TID"
curl -s -b "$J" -c "$J" -o /dev/null --data-urlencode "_token=$FT" \
  --data-urlencode "subject=İkinci talep" --data-urlencode "body=Bu talep oluşmamalı çünkü yanıt bekleniyor." "$BASE/panel/destek"
check "ikinci talep oluşmadı" "$(MY "SELECT COUNT(*) FROM support_tickets WHERE user_id=$USER_ID")" "1"
curl -s -b "$J" -c "$J" -o /dev/null --data-urlencode "_token=$FT" --data-urlencode "id=$TID" \
  --data-urlencode "body=Yanıt gelmeden yazılmamalı." "$BASE/panel/destek/yanit"
check "beklerken mesaj yazılamadı" "$(MY "SELECT COUNT(*) FROM support_ticket_messages WHERE ticket_id=$TID")" "1"
curl -s -b "$J" -c "$J" "$BASE/panel/destek" -o "$OUT/list_waiting.html"
has "$OUT/list_waiting.html" 'Yanıt bekleniyor'
nothas "$OUT/list_waiting.html" '/panel/destek/yeni"'

echo "== yanıt gelince yanıt/kapat/yeni açılır =="
$PHP -r 'require "app/bootstrap.php"; App\Services\SupportService::reply((int)$argv[1], "admin", 1, "Merhaba, talebiniz incelendi. Ek bilgi verir misiniz?");' "$TID" >/dev/null
check "durum yanıtlandı" "$(MY "SELECT status FROM support_tickets WHERE id=$TID")" "answered"
curl -s -b "$J" -c "$J" "$BASE/panel/destek/$TID" -o "$OUT/ticket_answered.html"
has "$OUT/ticket_answered.html" 'name="body"'
has "$OUT/ticket_answered.html" 'btn btn-danger'
has "$OUT/ticket_answered.html" 'Talebi kapat'
check "yeni talep formu açıldı" "$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' "$BASE/panel/destek/yeni")" "200"

curl -s -b "$J" -c "$J" -o /dev/null --data-urlencode "_token=$FT" --data-urlencode "id=$TID" \
  --data-urlencode "body=Ek bilgi: fatura numarası 1234." "$BASE/panel/destek/yanit"
check "müşteri yanıtı kaydedildi" "$(MY "SELECT COUNT(*) FROM support_ticket_messages WHERE ticket_id=$TID AND sender='customer'")" "2"
check "yanıt sonrası yeniden bekliyor" "$(MY "SELECT status FROM support_tickets WHERE id=$TID")" "open"

echo "== kapatma + yeni talep =="
$PHP -r 'require "app/bootstrap.php"; App\Services\SupportService::reply((int)$argv[1], "admin", 1, "Teşekkürler, kaydınızı kapattık.");' "$TID" >/dev/null
curl -s -b "$J" -c "$J" -o /dev/null --data-urlencode "_token=$FT" --data-urlencode "id=$TID" "$BASE/panel/destek/kapat"
check "talep kapandı" "$(MY "SELECT status FROM support_tickets WHERE id=$TID")" "closed"
check "kapalıdan sonra yeni talep serbest" "$(curl -s -b "$J" -c "$J" -o "$OUT/form2.html" -w '%{http_code}' "$BASE/panel/destek/yeni")" "200"
FT2=$(tok "$OUT/form2.html")
curl -s -b "$J" -c "$J" -o /dev/null --data-urlencode "_token=$FT2" \
  --data-urlencode "subject=Yeni konu" --data-urlencode "body=Kapatılan talepten sonra açılan yeni talep." "$BASE/panel/destek"
check "yeni talep oluştu" "$(MY "SELECT COUNT(*) FROM support_tickets WHERE user_id=$USER_ID")" "2"

echo "== kapalı talep + başka bekleyen talep =="
curl -s -b "$J" -c "$J" "$BASE/panel/destek/$TID" -o "$OUT/ticket_closed_pending.html"
has "$OUT/ticket_closed_pending.html" 'Yanıt bekleyen başka bir talebiniz var.'
nothas "$OUT/ticket_closed_pending.html" '/panel/destek/yeni"'
curl -s -b "$J" -c "$J" "$BASE/panel/destek/$TID?dil=en" -o "$OUT/ticket_closed_pending_en.html"
has "$OUT/ticket_closed_pending_en.html" 'another request awaiting a response'

# Kırmızı "Talebi kapat" butonu: zemin kırmızı, metin beyaz (CSS kuralı).
grep -qF '.btn-danger { background: color-mix(in srgb, var(--c-danger) 92%, #000); color: #fff;' assets/css/app.css \
  && ok "talebi kapat: kırmızı zemin + beyaz metin" || bad "btn-danger kırmızı/beyaz kuralı yok"

echo ""
echo "SONUÇ: $pass geçti, $fail kaldı"
