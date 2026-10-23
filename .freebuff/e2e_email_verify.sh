#!/usr/bin/env bash
# E-posta doğrulama: 6 haneli kod, 2 dakika TTL, yeniden gönderim, markalı mail.
# Gerçek HTTP + MariaDB + yerel SMTP sink kullanır; kendi fixture'ını temizler.
set -u
BASE="http://127.0.0.1:3001"
ROOT="/workspace/chats/9e03ed61266af80267d81f50"
export HOME="${HOME:-/workspace/homes/9e03ed61266af80267d81f50}"
PHP="$HOME/bin/php/php"
MY="$HOME/mariadb/bin/mariadb --no-defaults --socket=$HOME/mysql-tmp/mysql.sock -uroot evrako_live -N -B"
J=/tmp/verify_j.txt; rm -f "$J"
OUT=/tmp/verify_out; rm -rf "$OUT"; mkdir -p "$OUT"
SINK_DIR=/tmp/verify-sink; rm -rf "$SINK_DIR"; mkdir -p "$SINK_DIR"
SINK_LOG=/tmp/verify-sink.log
STAMP=$(date +%s)
E1="verify-one-$STAMP@evrako.test"
E2="verify-two-$STAMP@evrako.test"
E3="verify-three-$STAMP@evrako.test"
PASS='Verify123!'
SETTINGS_KEYS="'smtp.host','smtp.port','smtp.encryption','smtp.auth','smtp.username','smtp.password','smtp.from_email','smtp.from_name'"
SINK_PID=""
pass=0; fail=0
ok(){ echo "  ✓ $1"; pass=$((pass+1)); }
bad(){ echo "  ✗ $1"; fail=$((fail+1)); }
check(){ [ "$2" = "$3" ] && ok "$1 ($2)" || bad "$1 — beklenen '$3', gelen '$2'"; }
has(){ grep -qF "$2" "$1" && ok "$1 içeriyor: $2" || bad "$1 eksik: $2"; }
nothas(){ grep -qF "$2" "$1" && bad "$1 içermemeliydi: $2" || ok "$1 beklendiği gibi: $2 yok"; }
tok(){ grep -o 'name="_token" value="[^"]*"' "$1" | head -1 | sed 's/.*value="//;s/"$//'; }
loc(){ grep -i '^location:' "$1" | tr -d '\r' | sed 's/^[Ll]ocation: *//'; }
sinkcount(){ ls "$SINK_DIR"/*.eml 2>/dev/null | wc -l | tr -d ' '; }

$MY -e "SELECT CONCAT('UPDATE settings SET \`value\`=',QUOTE(\`value\`),' WHERE \`key\`=',QUOTE(\`key\`),';') FROM settings WHERE \`key\` IN ($SETTINGS_KEYS)" > /tmp/verify_settings_backup.sql

cleanup(){
    [ -n "$SINK_PID" ] && kill "$SINK_PID" 2>/dev/null
    for email in "$E1" "$E2" "$E3"; do
        $MY -e "DELETE FROM login_attempts WHERE email='$email'" >/dev/null 2>&1
        $MY -e "DELETE FROM users WHERE email='$email'" >/dev/null 2>&1
    done
    [ -s /tmp/verify_settings_backup.sql ] && $MY < /tmp/verify_settings_backup.sql >/dev/null 2>&1
    echo "  (doğrulama kullanıcıları, kodları ve SMTP ayarları geri yüklendi)"
}
trap cleanup EXIT

echo "== hazırlık: SMTP sink + ayarlar =="
$MY -e "UPDATE settings SET \`value\`='127.0.0.1' WHERE \`key\`='smtp.host'" >/dev/null
$MY -e "UPDATE settings SET \`value\`='2525' WHERE \`key\`='smtp.port'" >/dev/null
$MY -e "UPDATE settings SET \`value\`='none' WHERE \`key\`='smtp.encryption'" >/dev/null
$MY -e "UPDATE settings SET \`value\`='0' WHERE \`key\`='smtp.auth'" >/dev/null
$MY -e "UPDATE settings SET \`value\`='noreply@evrako.test' WHERE \`key\`='smtp.from_email'" >/dev/null
setsid "$PHP" "$ROOT/.freebuff/smtp-sink.php" 2525 "$SINK_DIR" 12 600 > "$SINK_LOG" 2>&1 &
SINK_PID=$!
for i in $(seq 1 20); do
    grep -q "sink listening" "$SINK_LOG" 2>/dev/null && break
    sleep 0.3
done
grep -q "sink listening" "$SINK_LOG" && ok "yerel SMTP sink dinliyor (2525)" || bad "SMTP sink başlamadı"

echo "== kayıt: 6 haneli kod gönderimi =="
curl -s -c "$J" "$BASE/kayit" -o "$OUT/register.html"
T=$(tok "$OUT/register.html")
CODE=$(curl -s -b "$J" -c "$J" -D "$OUT/reg1.headers" -o "$OUT/reg1.body" -w '%{http_code}' \
  --data-urlencode "_token=$T" --data-urlencode "full_name=Doğrulama Testi" \
  --data-urlencode "email=$E1" --data-urlencode "phone=5550000000" \
  --data-urlencode "password=$PASS" --data-urlencode "password_confirmation=$PASS" \
  --data-urlencode "terms=1" --data-urlencode "marketing_opt_in=1" "$BASE/kayit")
check "POST /kayit" "$CODE" "302"
check "kayıt doğrulama ekranına yönlendirir" "$(loc "$OUT/reg1.headers")" "/dogrula"
for i in $(seq 1 30); do [ -f "$SINK_DIR/1.eml" ] && break; sleep 0.2; done
check "doğrulama + karşılama e-postası sink'e ulaştı" "$(sinkcount)" "2"
"$PHP" "$ROOT/.freebuff/mail-part.php" "$SINK_DIR/1.eml" html > "$OUT/mail1.html"
"$PHP" "$ROOT/.freebuff/mail-part.php" "$SINK_DIR/1.eml" text > "$OUT/mail1.txt"
CODE1=$(grep -oE '[0-9]{6}' "$OUT/mail1.txt" | head -1)
[ "${#CODE1}" = "6" ] && ok "mailden 6 haneli kod okundu ($CODE1)" || bad "mailde 6 haneli kod yok"
has "$OUT/mail1.html" "$CODE1"
has "$OUT/mail1.html" '2 dakika'
has "$OUT/mail1.html" 'Doğrulama kodunuz'
has "$OUT/mail1.html" 'Evrako'
has "$OUT/mail1.html" 'dir="ltr"'

USER1=$($MY -e "SELECT id FROM users WHERE email='$E1' LIMIT 1")
check "kullanıcı oluşturuldu" "$([ -n "$USER1" ] && echo yes)" "yes"
SUBTRIAL=$($MY -e "SELECT CONCAT(p.\`key\`, '|', p.trial_days) FROM subscriptions s JOIN plans p ON p.id = s.plan_id JOIN organizations o ON o.id = s.organization_id WHERE o.owner_user_id=$USER1 AND s.status='trialing' LIMIT 1")
check "kayıt 6 aylık deneme planı atar" "$SUBTRIAL" "trial|180"
check "yeni kullanıcı doğrulanmamış" "$($MY -e "SELECT email_verified_at IS NULL FROM users WHERE id=$USER1")" "1"
check "doğrulama kodu kaydedildi" "$($MY -e "SELECT COUNT(*) FROM email_verification_codes WHERE user_id=$USER1")" "1"
CODE_HASH=$($MY -e "SELECT code_hash FROM email_verification_codes WHERE user_id=$USER1")
echo "$CODE_HASH" | grep -qF "$CODE1" && bad "kod düz metin olarak saklanmış" || ok "kod hash'lenmiş"
curl -s -b "$J" -c "$J" -D "$OUT/panel1.headers" -o /dev/null "$BASE/panel"
check "doğrulanmamış kullanıcı panelden doğrulamaya gider" "$(loc "$OUT/panel1.headers")" "/dogrula"

echo "== doğrulama ekranı + hatalı kod =="
curl -s -b "$J" -c "$J" "$BASE/dogrula" -o "$OUT/verify1.html"
has "$OUT/verify1.html" "$E1"
has "$OUT/verify1.html" 'name="code"'
has "$OUT/verify1.html" 'Kalan süre'
T=$(tok "$OUT/verify1.html")
CODE=$(curl -s -b "$J" -c "$J" -D "$OUT/bad.headers" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$T" --data-urlencode "code=000000" "$BASE/dogrula")
check "hatalı kod 302" "$CODE" "302"
check "hatalı kod doğrulama ekranına döner" "$(loc "$OUT/bad.headers")" "/dogrula"
check "hatalı deneme sayacı arttı" "$($MY -e "SELECT attempts FROM email_verification_codes WHERE user_id=$USER1")" "1"
curl -s -b "$J" -c "$J" "$BASE/dogrula" -o "$OUT/verify1_bad.html"
has "$OUT/verify1_bad.html" 'Kod geçersiz'

echo "== doğru kod: hesap aktif =="
T=$(tok "$OUT/verify1_bad.html")
CODE=$(curl -s -b "$J" -c "$J" -D "$OUT/good.headers" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$T" --data-urlencode "code=$CODE1" "$BASE/dogrula")
check "doğru kod 302" "$CODE" "302"
check "doğrulama sonrası panele gider" "$(loc "$OUT/good.headers")" "/panel"
check "e-posta doğrulandı" "$($MY -e "SELECT email_verified_at IS NOT NULL FROM users WHERE id=$USER1")" "1"
check "kod kullanıldıktan sonra silindi" "$($MY -e "SELECT COUNT(*) FROM email_verification_codes WHERE user_id=$USER1")" "0"
CODE=$(curl -s -b "$J" -c "$J" -o "$OUT/panel.html" -w '%{http_code}' "$BASE/panel")
check "doğrulanmış kullanıcı /panel" "$CODE" "200"
has "$OUT/panel.html" 'Panelim'

echo "== süre dolması + yeniden gönderme =="
rm -f "$J"; curl -s -c "$J" "$BASE/kayit" -o "$OUT/register2.html"
T=$(tok "$OUT/register2.html")
curl -s -b "$J" -c "$J" -o /dev/null \
  --data-urlencode "_token=$T" --data-urlencode "full_name=İkinci Doğrulama" \
  --data-urlencode "email=$E2" --data-urlencode "password=$PASS" \
  --data-urlencode "password_confirmation=$PASS" --data-urlencode "terms=1" "$BASE/kayit"
for i in $(seq 1 30); do [ -f "$SINK_DIR/3.eml" ] && break; sleep 0.2; done
"$PHP" "$ROOT/.freebuff/mail-part.php" "$SINK_DIR/3.eml" text > "$OUT/mail2.txt"
CODE2=$(grep -oE '[0-9]{6}' "$OUT/mail2.txt" | head -1)
USER2=$($MY -e "SELECT id FROM users WHERE email='$E2' LIMIT 1")
$MY -e "UPDATE email_verification_codes SET expires_at='2000-01-01 00:00:00' WHERE user_id=$USER2" >/dev/null
curl -s -b "$J" -c "$J" "$BASE/dogrula" -o "$OUT/verify2.html"
T=$(tok "$OUT/verify2.html")
CODE=$(curl -s -b "$J" -c "$J" -D "$OUT/expired.headers" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$T" --data-urlencode "code=$CODE2" "$BASE/dogrula")
check "süresi dolmuş kod 302" "$CODE" "302"
curl -s -b "$J" -c "$J" "$BASE/dogrula" -o "$OUT/verify2_expired.html"
has "$OUT/verify2_expired.html" 'Kodun süresi doldu'
check "süresi dolmuş kod silindi" "$($MY -e "SELECT COUNT(*) FROM email_verification_codes WHERE user_id=$USER2")" "0"
T=$(tok "$OUT/verify2_expired.html")
CODE=$(curl -s -b "$J" -c "$J" -D "$OUT/resend.headers" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$T" "$BASE/dogrula/yeniden-gonder")
check "yeniden gönderim 302" "$CODE" "302"
for i in $(seq 1 30); do [ -f "$SINK_DIR/5.eml" ] && break; sleep 0.2; done
check "yeni doğrulama e-postası gönderildi" "$(sinkcount)" "5"
"$PHP" "$ROOT/.freebuff/mail-part.php" "$SINK_DIR/5.eml" text > "$OUT/mail3.txt"
CODE3=$(grep -oE '[0-9]{6}' "$OUT/mail3.txt" | head -1)
[ "$CODE3" != "$CODE2" ] && ok "yeniden gönderim yeni kod üretti" || bad "yeniden gönderim eski kodu tekrar kullandı"
# 60 saniye bekleme: hemen ikinci istek yeni mail üretmemeli.
curl -s -b "$J" -c "$J" "$BASE/dogrula" -o "$OUT/verify2_new.html"
T=$(tok "$OUT/verify2_new.html")
curl -s -b "$J" -c "$J" -o /dev/null --data-urlencode "_token=$T" "$BASE/dogrula/yeniden-gonder"
sleep 1
check "60 sn bekleme yeni mail üretmedi" "$(sinkcount)" "5"
T=$(tok "$OUT/verify2_new.html")
CODE=$(curl -s -b "$J" -c "$J" -D "$OUT/good2.headers" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$T" --data-urlencode "code=$CODE3" "$BASE/dogrula")
check "yeni kodla doğrulama" "$(loc "$OUT/good2.headers")" "/panel"

echo "== giriş: doğrulanmamış hesap =="
HASH=$($PHP -r "echo password_hash('$PASS', PASSWORD_DEFAULT);")
USER3=$($MY -e "INSERT INTO users (full_name,email,password_hash,role,status,locale,created_at) VALUES ('Üçüncü Doğrulama','$E3','$HASH','customer','active','tr',NOW()); SELECT LAST_INSERT_ID();")
rm -f "$J"; curl -s -c "$J" "$BASE/giris" -o "$OUT/login.html"
T=$(tok "$OUT/login.html")
CODE=$(curl -s -b "$J" -c "$J" -D "$OUT/login_unverified.headers" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$T" --data-urlencode "email=$E3" --data-urlencode "password=$PASS" "$BASE/giris")
check "doğrulanmamış giriş 302" "$CODE" "302"
check "doğrulanmamış giriş kod ekranına gider" "$(loc "$OUT/login_unverified.headers")" "/dogrula"
for i in $(seq 1 30); do [ -f "$SINK_DIR/6.eml" ] && break; sleep 0.2; done
check "girişte yeni kod gönderildi" "$(sinkcount)" "6"
curl -s -b "$J" -c "$J" -D "$OUT/panel3.headers" -o /dev/null "$BASE/panel"
check "doğrulanmamış giriş paneli engellenir" "$(loc "$OUT/panel3.headers")" "/dogrula"

echo
echo "SONUÇ: $pass geçti, $fail kaldı"
exit $fail
