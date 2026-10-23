#!/usr/bin/env bash
# Cihaz bildirimleri (Web Push) — gerçek HTTP + şifreleme + VAPID doğrulaması.
set -u
BASE="http://127.0.0.1:3001"
ROOT="/workspace/chats/9e03ed61266af80267d81f50"
export HOME="${HOME:-/workspace/homes/9e03ed61266af80267d81f50}"
PHP="$HOME/bin/php/php"
MY="$HOME/mariadb/bin/mariadb --no-defaults --socket=$HOME/mysql-tmp/mysql.sock -uroot evrako_live -N -B"
OUT=/tmp/push_out; mkdir -p "$OUT"
J=/tmp/push_user.txt; rm -f "$J"
RECV_PORT=3112
pass=0; fail=0
ok(){ echo "  ✓ $1"; pass=$((pass+1)); }
bad(){ echo "  ✗ $1"; fail=$((fail+1)); }
check(){ [ "$2" = "$3" ] && ok "$1 ($2)" || bad "$1 — beklenen '$3', gelen '$2'"; }
has(){ grep -qF "$2" "$1" && ok "$1 içeriyor: $2" || bad "$1 eksik: $2"; }
nothas(){ grep -qF "$2" "$1" && bad "$1 içermemeliydi: $2" || ok "$1 beklendiği gibi: $2 yok"; }
tok(){ grep -o 'name="_token" value="[^"]*"' "$1" | head -1 | sed 's/.*value="//;s/"$//'; }

mkdir -p /tmp/push_capture; rm -f /tmp/push_capture/last.json
PUSH_CAPTURE_DIR=/tmp/push_capture setsid "$PHP" -S 127.0.0.1:$RECV_PORT "$ROOT/.freebuff/push-receiver.php" \
  > /tmp/push_receiver.log 2>&1 < /dev/null &
RECV_PID=$!

TEMP_ID=""
cleanup(){
  kill "$RECV_PID" >/dev/null 2>&1
  if [ -n "$TEMP_ID" ]; then $MY -e "DELETE FROM users WHERE id=$TEMP_ID" >/dev/null 2>&1; fi
  $MY -e "DELETE FROM push_subscriptions WHERE endpoint LIKE 'http://127.0.0.1:%'" >/dev/null 2>&1
}
trap cleanup EXIT

sleep 1
curl -s -o /dev/null -w '' -X POST -d x "http://127.0.0.1:$RECV_PORT/push"

echo "== Şifreleme + VAPID (uçtan uca) =="
CRYPTO_OUT=$OUT/crypto.txt
OPENSSL_CONF=/etc/ssl/openssl.cnf "$PHP" "$ROOT/.freebuff/push-call.php" run /tmp/push_capture \
  "http://127.0.0.1:$RECV_PORT/push" > "$CRYPTO_OUT" 2>&1
CRYPTO_STATUS=$?
tail -3 "$CRYPTO_OUT"
C_PASS=$(grep -o 'SONUÇ: [0-9]*' "$CRYPTO_OUT" | grep -o '[0-9]*' || echo 0)
C_FAIL=$(grep -o '[0-9]* kaldı' "$CRYPTO_OUT" | grep -o '[0-9]*' || echo 0)
pass=$((pass + C_PASS)); fail=$((fail + C_FAIL))
[ "$CRYPTO_STATUS" -eq 0 ] && ok "kripto paketi temiz" || bad "kripto paketi hatalı"

echo "== Geçici doğrulanmış kullanıcı =="
TEMP_ID=$("$PHP" -r '
require "app/bootstrap.php";
$email = "push-http-" . bin2hex(random_bytes(4)) . "@example.test";
$id = App\Core\Database::insert("users", [
  "full_name" => "Push HTTP Test",
  "email" => $email,
  "password_hash" => password_hash("PushTest123!", PASSWORD_DEFAULT),
  "role" => "customer", "status" => "active", "locale" => "tr",
  "email_verified_at" => date("Y-m-d H:i:s"),
  "created_at" => date("Y-m-d H:i:s"), "updated_at" => date("Y-m-d H:i:s"),
]);
echo $id, "\n", $email;
')
USER_ID=$(echo "$TEMP_ID" | head -1); USER_EMAIL=$(echo "$TEMP_ID" | tail -1); TEMP_ID="$USER_ID"
[ -n "$USER_ID" ] && ok "kullanıcı oluşturuldu (#$USER_ID)" || bad "kullanıcı oluşturulamadı"

echo "== Service worker =="
curl -s -D "$OUT/sw_headers.txt" -o "$OUT/sw.js" "$BASE/sw.js"
has "$OUT/sw.js" "addEventListener('push'"
has "$OUT/sw.js" "showNotification"
grep -qi 'Content-Type: application/javascript' "$OUT/sw_headers.txt" && ok "sw.js MIME doğru" || bad "sw.js MIME yanlış"
grep -qi 'Service-Worker-Allowed: /' "$OUT/sw_headers.txt" && ok "Service-Worker-Allowed: /" || bad "Service-Worker-Allowed eksik"
check "sw.js durum" "$(head -1 "$OUT/sw_headers.txt" | grep -o '[0-9][0-9][0-9]')" "200"

echo "== Girişsiz erişim =="
code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/panel/bildirimler/cihaz")
check "cihaz durumu girişsiz" "$code" "302"
code=$(curl -s -o /dev/null -w '%{http_code}' -X POST "$BASE/panel/bildirimler/cihaz")
check "cihaz kaydı girişsiz" "$code" "302"

echo "== Müşteri paneli cihaz kartı =="
curl -s -c "$J" -b "$J" "$BASE/giris" -o "$OUT/login.html"
T=$(tok "$OUT/login.html")
curl -s -c "$J" -b "$J" -X POST --data-urlencode "_token=$T" \
  --data-urlencode "email=$USER_EMAIL" --data-urlencode "password=PushTest123!" \
  "$BASE/giris" -o /dev/null
curl -s -c "$J" -b "$J" "$BASE/panel/bildirimler" -o "$OUT/notifications.html"
has "$OUT/notifications.html" "Cihaz bildirimleri"
has "$OUT/notifications.html" "data-device-push"
has "$OUT/notifications.html" "Bu cihazda bildirimleri aç"
has "$OUT/notifications.html" "data-key="
has "$OUT/notifications.html" "sw.js"
nothas "$OUT/notifications.html" "private_key"
PT=$(grep -o 'name="_token" value="[^"]*"' "$OUT/notifications.html" | head -1 | sed 's/.*value="//;s/"$//')
[ -n "$PT" ] && ok "panel CSRF tokenı bulundu" || bad "panel CSRF tokenı yok"

echo "== Abonelik doğrulaması =="
code=$(curl -s -b "$J" -o "$OUT/bad.json" -w '%{http_code}' -X POST \
  -H 'Accept: application/json' -H 'X-Requested-With: XMLHttpRequest' \
  --data-urlencode "_token=$PT" --data-urlencode "endpoint=http://evil.example.test/push" \
  --data-urlencode "p256dh=x" --data-urlencode "auth=y" \
  "$BASE/panel/bildirimler/cihaz")
check "uzak http uç noktası reddedildi" "$code" "422"
grep -q '"ok":false' "$OUT/bad.json" && ok "hata JSON döndü" || bad "hata JSON yok"

code=$(curl -s -b "$J" -o /dev/null -w '%{http_code}' -X POST \
  --data-urlencode "endpoint=http://127.0.0.1:9999/push" \
  --data-urlencode "p256dh=x" --data-urlencode "auth=y" \
  "$BASE/panel/bildirimler/cihaz")
check "CSRF'siz abonelik" "$code" "419"

POINT=$("$PHP" -r 'echo rtrim(strtr(base64_encode("\x04" . random_bytes(64)), "+/", "-_"), "=");')
AUTHK=$("$PHP" -r 'echo rtrim(strtr(base64_encode(random_bytes(16)), "+/", "-_"), "=");')
ENDPOINT="http://127.0.0.1:9999/push-test"
code=$(curl -s -b "$J" -o "$OUT/sub.json" -w '%{http_code}' -X POST \
  -H 'Accept: application/json' -H 'X-Requested-With: XMLHttpRequest' \
  --data-urlencode "_token=$PT" --data-urlencode "endpoint=$ENDPOINT" \
  --data-urlencode "p256dh=$POINT" --data-urlencode "auth=$AUTHK" \
  --data-urlencode "user_agent=push-http-test" \
  "$BASE/panel/bildirimler/cihaz")
check "geçerli abonelik" "$code" "200"
grep -q '"devices":1' "$OUT/sub.json" && ok "cihaz sayısı 1" || bad "cihaz sayısı yanlış: $(cat "$OUT/sub.json")"
check "DB'de abonelik" "$($MY -e "SELECT COUNT(*) FROM push_subscriptions WHERE user_id=$USER_ID")" "1"
check "notify_push işaretlendi" "$($MY -e "SELECT notify_push FROM users WHERE id=$USER_ID")" "1"

curl -s -b "$J" -H 'Accept: application/json' "$BASE/panel/bildirimler/cihaz" -o "$OUT/status.json"
grep -q '"devices":1' "$OUT/status.json" && ok "durum ucu cihazı gösteriyor" || bad "durum ucu yanlış: $(cat "$OUT/status.json")"
grep -q '"publicKey":"' "$OUT/status.json" && ok "durum ucu açık anahtarı veriyor" || bad "açık anahtar yok"
nothas "$OUT/status.json" "BEGIN EC PRIVATE KEY"

code=$(curl -s -b "$J" -o "$OUT/unsub.json" -w '%{http_code}' -X POST \
  -H 'Accept: application/json' -H 'X-Requested-With: XMLHttpRequest' \
  --data-urlencode "_token=$PT" --data-urlencode "endpoint=$ENDPOINT" \
  "$BASE/panel/bildirimler/cihaz/kapat")
check "abonelik kapatma" "$code" "200"
grep -q '"devices":0' "$OUT/unsub.json" && ok "cihaz sayısı 0" || bad "cihaz sayısı sıfırlanmadı"
check "notify_push kapatıldı" "$($MY -e "SELECT notify_push FROM users WHERE id=$USER_ID")" "0"

echo "== Yönetim: duyuru sayfası + kuyruk =="
ADMIN_EMAIL="admin@evrako.test"
ADMIN_PASS='TempAdmin123!'
OLD_HASH=$($MY -e "SELECT password_hash FROM users WHERE id=1")
NEW_HASH=$("$PHP" -r "echo password_hash('TempAdmin123!', PASSWORD_DEFAULT);")
$MY -e "UPDATE users SET password_hash='$NEW_HASH' WHERE id=1" >/dev/null
AJ=/tmp/push_admin.txt; rm -f "$AJ"
curl -s -c "$AJ" -b "$AJ" "$BASE/yonetim/giris" -o "$OUT/admin_login.html"
AT=$(tok "$OUT/admin_login.html")
curl -s -c "$AJ" -b "$AJ" -X POST --data-urlencode "_token=$AT" \
  --data-urlencode "email=$ADMIN_EMAIL" --data-urlencode "password=$ADMIN_PASS" \
  "$BASE/yonetim/giris" -o /dev/null
curl -s -c "$AJ" -b "$AJ" "$BASE/yonetim/duyurular" -o "$OUT/announcements.html"
has "$OUT/announcements.html" "Cihaz bildirimleri (Web Push)"
has "$OUT/announcements.html" "Bağlı cihaz"
has "$OUT/announcements.html" "/yonetim/duyurular/push-gonder"
has "$OUT/announcements.html" "Bekleyen cihaz bildirimlerini gönder"
AT2=$(tok "$OUT/announcements.html")
code=$(curl -s -b "$AJ" -o "$OUT/push_process.html" -w '%{http_code}' -X POST \
  --data-urlencode "_token=$AT2" "$BASE/yonetim/duyurular/push-gonder")
check "kuyruk işleme düğmesi" "$code" "302"
code=$(curl -s -b "$AJ" -o /dev/null -w '%{http_code}' -X POST \
  --data-urlencode "endpoint=http://127.0.0.1:9999/x" "$BASE/panel/bildirimler/cihaz")
check "CSRF'siz abonelik (panel, yönetici oturumu)" "$code" "419"
$MY -e "UPDATE users SET password_hash='$OLD_HASH' WHERE id=1" >/dev/null

echo "== Göç kaydı =="
check "migration uygulandı" "$($MY -e "SELECT COUNT(*) FROM migrations WHERE filename='2026_10_22_013_web_push.sql'")" "1"

echo
echo "SONUÇ: $pass geçti, $fail kaldı"
[ "$fail" -eq 0 ] || exit 1
