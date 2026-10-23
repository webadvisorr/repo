#!/usr/bin/env bash
# Abonelik akışı — iki paket (ücretsiz deneme + standart), havale yönlendirmesi,
# ödeme bildirimi ve yönetim tarafı. Gerçek MariaDB + HTTP ile uçtan uca test.
set -u
BASE="http://127.0.0.1:3001"
ROOT="/workspace/chats/9e03ed61266af80267d81f50"
export HOME="${HOME:-/workspace/homes/9e03ed61266af80267d81f50}"
PHP="$HOME/bin/php/php"
MY() { "$HOME/mariadb/bin/mariadb" --no-defaults --socket="$HOME/mysql-tmp/mysql.sock" -uroot evrako_live -N -B -e "$1"; }
OUT=/tmp/billing_out; mkdir -p "$OUT"
STAMP=$(date +%s)
EMAIL="bill-$STAMP@evrako.test"
PASS='BillPass123!'
USER_ID=""; ORG_ID=""
J=/tmp/bill_cust.jar; JC=/tmp/bill_admin.jar
rm -f "$J" "$JC"
pass=0; fail=0
ok(){ echo "  ✓ $1"; pass=$((pass+1)); }
bad(){ echo "  ✗ $1"; fail=$((fail+1)); }
check(){ [ "$2" = "$3" ] && ok "$1 ($2)" || bad "$1 — beklenen '$3', gelen '$2'"; }
has(){ grep -qF "$2" "$1" && ok "$(basename "$1") içeriyor: $2" || bad "$(basename "$1") eksik: $2"; }
nothas(){ grep -qiF "$2" "$1" && bad "$(basename "$1") içermemeliydi: $2" || ok "$(basename "$1") beklendiği gibi: $2 yok"; }
tok(){ grep -o 'name="_token" value="[^"]*"' "$1" | head -1 | sed 's/.*value="//;s/"$//'; }
loc(){ grep -i '^location:' "$1" | tr -d '\r' | sed 's/^[Ll]ocation: *//'; }

OLD_HASH=$(MY "SELECT password_hash FROM users WHERE id=1")
cleanup(){
  [ -n "$ORG_ID" ] && MY "DELETE FROM payments WHERE organization_id=$ORG_ID" >/dev/null 2>&1
  [ -n "$ORG_ID" ] && MY "DELETE FROM subscriptions WHERE organization_id=$ORG_ID" >/dev/null 2>&1
  [ -n "$ORG_ID" ] && MY "DELETE FROM organizations WHERE id=$ORG_ID" >/dev/null 2>&1
  [ -n "$USER_ID" ] && MY "DELETE FROM users WHERE id=$USER_ID" >/dev/null 2>&1
  MY "UPDATE users SET password_hash='$OLD_HASH' WHERE id=1" >/dev/null 2>&1
  echo "  (geçici üyelik ve ödeme kayıtları silindi)"
}
trap cleanup EXIT

echo "== hazırlık =="
HASH=$($PHP -r "echo password_hash('$PASS', PASSWORD_DEFAULT);")
USER_ID=$(MY "INSERT INTO users (full_name,email,password_hash,role,status,locale,email_verified_at,created_at) VALUES ('Billing Test','$EMAIL','$HASH','customer','active','tr',NOW(),NOW()); SELECT LAST_INSERT_ID();")
ORG_ID=$(MY "INSERT INTO organizations (owner_user_id,name,type,created_at) VALUES ($USER_ID,'Billing Test $STAMP','individual',NOW()); SELECT LAST_INSERT_ID();")
MY "INSERT INTO organization_members (organization_id,user_id,role,status,created_at) VALUES ($ORG_ID,$USER_ID,'owner','active',NOW())" >/dev/null
check "geçici üyelik hazır" "$([ "$ORG_ID" -gt 0 ] && echo ok)" "ok"

echo "== herkese açık fiyat sayfası: iki paket =="
curl -s "$BASE/fiyatlar" -o "$OUT/pricing.html"
has "$OUT/pricing.html" '6 Ay Ücretsiz Deneme'
has "$OUT/pricing.html" 'Standart Paket'
has "$OUT/pricing.html" 'tek seferlik'
nothas "$OUT/pricing.html" 'Aylık ücret'
nothas "$OUT/pricing.html" 'Yıllık ücret'

echo "== yönetim plan ekranı: aylık/yıllık dönem alanları kalktı =="
NEW_HASH=$($PHP -r "echo password_hash('TempAdmin123!', PASSWORD_DEFAULT);")
MY "UPDATE users SET password_hash='$NEW_HASH' WHERE id=1" >/dev/null
curl -s -c "$JC" "$BASE/yonetim/giris" -o "$OUT/admin_login.html"
AT=$(tok "$OUT/admin_login.html")
curl -s -b "$JC" -c "$JC" -o /dev/null --data-urlencode "_token=$AT" \
  --data-urlencode "email=admin@evrako.test" --data-urlencode "password=TempAdmin123!" "$BASE/yonetim/giris"
check "yönetici girişi" "$(curl -s -b "$JC" -o "$OUT/plans.html" -w '%{http_code}' "$BASE/yonetim/planlar")" "200"
has "$OUT/plans.html" 'Ücretsiz plan'
has "$OUT/plans.html" '6 Ay Ücretsiz Deneme'
has "$OUT/plans.html" 'Standart Paket'

echo "== müşteri ödeme merkezi =="
curl -s -c "$J" "$BASE/giris" -o "$OUT/login.html"
UT=$(tok "$OUT/login.html")
curl -s -b "$J" -c "$J" -o /dev/null --data-urlencode "_token=$UT" \
  --data-urlencode "email=$EMAIL" --data-urlencode "password=$PASS" "$BASE/giris"
check "GET /panel/odeme" "$(curl -s -b "$J" -c "$J" -o "$OUT/billing.html" -w '%{http_code}' "$BASE/panel/odeme")" "200"
has "$OUT/billing.html" 'Standart Paket'
has "$OUT/billing.html" 'Havale'
nothas "$OUT/billing.html" 'Aylık ücret'
nothas "$OUT/billing.html" 'Yıllık ücret'

PLAN_ID=$(MY "SELECT id FROM plans WHERE \`key\`='standard' AND is_active=1 LIMIT 1")
check "standart plan tanımlı" "$([ -n "$PLAN_ID" ] && echo ok)" "ok"

echo "== paket seçimi → havale talimatı =="
BT=$(tok "$OUT/billing.html")
curl -s -b "$J" -c "$J" -D "$OUT/sub.hdr" -o /dev/null \
  --data-urlencode "_token=$BT" --data-urlencode "plan_id=$PLAN_ID" "$BASE/panel/odeme/abonelik"
SUB_LOC=$(loc "$OUT/sub.hdr")
check "talep sayfasına yönlendirme" "$(echo "$SUB_LOC" | sed 's#^/panel/odeme/talep/[0-9]*#/panel/odeme/talep/{id}#')" "/panel/odeme/talep/{id}"
PAY_ID=$(echo "$SUB_LOC" | sed 's#.*/##')
check "bekleyen ödeme kaydı oluştu" "$(MY "SELECT status FROM payments WHERE id=$PAY_ID AND organization_id=$ORG_ID")" "pending"
check "ödeme türü abonelik" "$(MY "SELECT kind FROM payments WHERE id=$PAY_ID")" "subscription"
check "plan bağı kuruldu" "$(MY "SELECT plan_id FROM payments WHERE id=$PAY_ID")" "$PLAN_ID"
REF=$(MY "SELECT reference FROM payments WHERE id=$PAY_ID")
curl -s -b "$J" -c "$J" "$BASE/panel/odeme/talep/$PAY_ID" -o "$OUT/intent.html"
has "$OUT/intent.html" "$REF"
BANK_NAME=$(MY "SELECT JSON_UNQUOTE(JSON_EXTRACT(value, '\$[0].bank')) FROM settings WHERE \`key\`='payment.bank_accounts'")
has "$OUT/intent.html" "$BANK_NAME"
has "$OUT/intent.html" 'TR33'
has "$OUT/intent.html" 'Bildirim'

echo "== ödeme bildirimi =="
IT=$(tok "$OUT/intent.html")
curl -s -b "$J" -c "$J" -o /dev/null -w '' \
  --data-urlencode "_token=$IT" --data-urlencode "note=E2E havale bildirimi" "$BASE/panel/odeme/talep/$PAY_ID/bildir"
check "bildirim kaydedildi" "$(MY "SELECT COUNT(*) FROM payments WHERE id=$PAY_ID AND JSON_EXTRACT(meta_json, '\$.declared') IS NOT NULL")" "1"
check "yönetim listesinde görünüyor" "$(curl -s -b "$JC" "$BASE/yonetim/odemeler" | grep -c "$REF")" "2"

echo "== ücretsiz deneme paketi =="
curl -s -b "$J" -c "$J" -o /dev/null --data-urlencode "_token=$BT" "$BASE/panel/odeme/deneme"
check "deneme aboneliği açıldı" "$(MY "SELECT COUNT(*) FROM subscriptions s JOIN plans p ON p.id=s.plan_id WHERE s.organization_id=$ORG_ID AND p.\`key\`='trial'")" "1"
curl -s -b "$J" -c "$J" -o /dev/null --data-urlencode "_token=$BT" "$BASE/panel/odeme/deneme"
check "ikinci deneme engellendi" "$(MY "SELECT COUNT(*) FROM subscriptions s JOIN plans p ON p.id=s.plan_id WHERE s.organization_id=$ORG_ID AND p.\`key\`='trial'")" "1"

echo ""
echo "SONUÇ: $pass geçti, $fail kaldı"
