#!/usr/bin/env bash
# Hesap bildirimleri (karşılama, kredi, abonelik), marka adı, giriş butonu
# ve yönetim paneli kutucuk seçimi — gerçek HTTP + MariaDB.
set -u
BASE="http://127.0.0.1:3001"
ROOT="/workspace/chats/9e03ed61266af80267d81f50"
export HOME="${HOME:-/workspace/homes/9e03ed61266af80267d81f50}"
PHP="$HOME/bin/php/php"
MY="$HOME/mariadb/bin/mariadb --no-defaults --socket=$HOME/mysql-tmp/mysql.sock -uroot evrako_live -N -B"
OUT=/tmp/account_out; mkdir -p "$OUT"
pass=0; fail=0
ok(){ echo "  ✓ $1"; pass=$((pass+1)); }
bad(){ echo "  ✗ $1"; fail=$((fail+1)); }
check(){ [ "$2" = "$3" ] && ok "$1 ($2)" || bad "$1 — beklenen '$3', gelen '$2'"; }
has(){ grep -qF "$2" "$1" && ok "$1 içeriyor: $2" || bad "$1 eksik: $2"; }
nothas(){ grep -qF "$2" "$1" && bad "$1 içermemeliydi: $2" || ok "$1 beklendiği gibi: $2 yok"; }
tok(){ grep -o 'name="_token" value="[^"]*"' "$1" | head -1 | sed 's/.*value="//;s/"$//'; }

OLD_HOST=$($MY -e "SELECT value FROM settings WHERE \`key\`='smtp.host'")
OLD_HASH=$($MY -e "SELECT password_hash FROM users WHERE id=1")
TMP_EMAIL="acc-test-$(date +%s)@example.test"
TMP_ID=""

cleanup(){
  $MY -e "UPDATE settings SET value='$OLD_HOST' WHERE \`key\`='smtp.host'" >/dev/null 2>&1
  $MY -e "UPDATE users SET password_hash='$OLD_HASH' WHERE id=1" >/dev/null 2>&1
  $PHP -r 'require "app/bootstrap.php"; App\Core\Settings::forget("dashboard.widgets");' >/dev/null 2>&1
  if [ -n "$TMP_ID" ]; then
    $MY -e "DELETE FROM users WHERE id=$TMP_ID" >/dev/null 2>&1
    $MY -e "DELETE FROM organizations WHERE owner_user_id NOT IN (SELECT id FROM users)" >/dev/null 2>&1
  fi
}
trap cleanup EXIT
# SMTP'yi yerel testte kapat: e-postalar storage/logs/mail.log'a yazılır.
$MY -e "UPDATE settings SET value='' WHERE \`key\`='smtp.host'" >/dev/null

echo "== Marka adı: Evrako =="
check "brand.title" "$($MY -e "SELECT value FROM settings WHERE \`key\`='brand.title'")" "Evrako"
check "marka göçü uygulandı" "$($MY -e "SELECT COUNT(*) FROM migrations WHERE filename='2026_10_22_014_brand_evrako.sql'")" "1"
curl -s "$BASE/" -o "$OUT/home.html"
nothas "$OUT/home.html" "Evrako KKTC"
has "$OUT/home.html" "Evrako"
has "$OUT/home.html" "btn-auth"
has "$OUT/home.html" "Giriş"
grep -q 'KKTC hukukuna' "$ROOT/app/Views/site/terms.php" && ok "hukuk metni korunmuş (marka değil)" || bad "hukuk metni değişmiş"

echo "== Yeni üye karşılaması (kayıt akışı) =="
RJ=/tmp/account_reg.txt; rm -f "$RJ"
curl -s -c "$RJ" -b "$RJ" "$BASE/kayit" -o "$OUT/reg.html"
RT=$(tok "$OUT/reg.html")
curl -s -c "$RJ" -b "$RJ" -X POST \
  --data-urlencode "_token=$RT" --data-urlencode "full_name=Karşılama Testi" \
  --data-urlencode "email=$TMP_EMAIL" --data-urlencode "phone=05330000001" \
  --data-urlencode "password=TestPass123!" --data-urlencode "password_confirmation=TestPass123!" \
  --data-urlencode "terms=1" "$BASE/kayit" -o /dev/null -w '%{http_code}' > "$OUT/reg_code.txt"
check "kayıt yönlendirmesi" "$(cat "$OUT/reg_code.txt")" "302"
TMP_ID=$($MY -e "SELECT id FROM users WHERE email='$TMP_EMAIL'")
[ -n "$TMP_ID" ] && ok "kullanıcı oluştu (#$TMP_ID)" || bad "kullanıcı oluşmadı"
check "zil karşılama bildirimi" "$($MY -e "SELECT COUNT(*) FROM user_notifications WHERE user_id=$TMP_ID AND title LIKE 'Hoş geldiniz%'")" "1"
check "karşılama e-postası (günlük)" "$($MY -e "SELECT COUNT(*) FROM notifications WHERE user_id=$TMP_ID AND template_key='account' AND subject LIKE '%Hoş geldiniz%' AND status='sent'")" "1"
grep -q "Karşılama Testi" "$ROOT/storage/logs/mail.log" && ok "e-posta gövdesi kişiselleştirilmiş" || bad "e-posta gövdesi kişisel değil"
grep -q "Evrako" "$ROOT/storage/logs/mail.log" && ok "e-postada marka adı Evrako" || bad "e-postada marka adı yok"
$MY -e "UPDATE users SET email_verified_at=NOW() WHERE id=$TMP_ID" >/dev/null
curl -s -b "$RJ" "$BASE/panel" -o "$OUT/panel.html"
has "$OUT/panel.html" "Hoş geldiniz"

echo "== Kredi yükleme bildirimi =="
ORG=$($MY -e "SELECT organization_id FROM organization_members WHERE user_id=$TMP_ID LIMIT 1")
[ -n "$ORG" ] && ok "kurum bulundu (#$ORG)" || bad "kurum yok"
$PHP -r 'require "app/bootstrap.php"; $r = App\Services\CreditService::add((int)$argv[1], 490, "manual", "Test yükleme"); echo json_encode($r);' "$ORG" > "$OUT/credit.json"
grep -q '"ok":true' "$OUT/credit.json" && ok "kredi eklendi" || bad "kredi eklenemedi: $(cat "$OUT/credit.json")"
check "kredi zil bildirimi" "$($MY -e "SELECT COUNT(*) FROM user_notifications WHERE user_id=$TMP_ID AND title='Kredi yüklendi'")" "1"
check "kredi e-postası" "$($MY -e "SELECT COUNT(*) FROM notifications WHERE user_id=$TMP_ID AND subject LIKE '%Kredi yüklendi%' AND status='sent'")" "1"
grep -q '"balance":490' "$OUT/credit.json" && ok "bakiye 490" || bad "bakiye yanlış"

echo "== Havale/ödeme onayı bildirimi =="
$PHP -r 'require "app/bootstrap.php"; $r = App\Services\CreditService::add((int)$argv[1], 100, "bank_transfer", "Havale onayı"); echo json_encode($r);' "$ORG" > "$OUT/bank.json"
check "ödeme onayı zil bildirimi" "$($MY -e "SELECT COUNT(*) FROM user_notifications WHERE user_id=$TMP_ID AND title='Ödemeniz onaylandı'")" "1"
check "ödeme onayı e-postası" "$($MY -e "SELECT COUNT(*) FROM notifications WHERE user_id=$TMP_ID AND subject LIKE '%Ödemeniz onaylandı%'")" "1"

echo "== Abonelik bildirimi =="
PLAN=$($MY -e "SELECT id FROM plans WHERE \`key\`='standard' LIMIT 1")
[ -z "$PLAN" ] && PLAN=$($MY -e "SELECT id FROM plans ORDER BY id LIMIT 1")
$PHP -r 'require "app/bootstrap.php"; $r = App\Services\SubscriptionService::assign((int)$argv[1], (int)$argv[2], ["interval"=>"year"]); echo json_encode($r);' "$ORG" "$PLAN" > "$OUT/sub.json"
grep -q '"ok":true' "$OUT/sub.json" && ok "abonelik atandı" || bad "abonelik atanamadı: $(cat "$OUT/sub.json")"
check "abonelik zil bildirimi" "$($MY -e "SELECT COUNT(*) FROM user_notifications WHERE user_id=$TMP_ID AND title='Aboneliğiniz başladı'")" "1"
check "abonelik e-postası" "$($MY -e "SELECT COUNT(*) FROM notifications WHERE user_id=$TMP_ID AND subject LIKE '%Aboneliğiniz başladı%'")" "1"

echo "== Yönetim paneli kutucukları =="
NEW_HASH=$($PHP -r "echo password_hash('TempAdmin123!', PASSWORD_DEFAULT);")
$MY -e "UPDATE users SET password_hash='$NEW_HASH' WHERE id=1" >/dev/null
AJ=/tmp/account_admin.txt; rm -f "$AJ"
curl -s -c "$AJ" -b "$AJ" "$BASE/yonetim/giris" -o "$OUT/admin_login.html"
AT=$(tok "$OUT/admin_login.html")
curl -s -c "$AJ" -b "$AJ" -X POST --data-urlencode "_token=$AT" \
  --data-urlencode "email=admin@evrako.test" --data-urlencode "password=TempAdmin123!" \
  "$BASE/yonetim/giris" -o /dev/null
curl -s -b "$AJ" "$BASE/yonetim/" -o "$OUT/dash.html"
has "$OUT/dash.html" "Kutucuk ayarları"
has "$OUT/dash.html" "Bugün e-posta hatırlatma"
has "$OUT/dash.html" "Bugün WhatsApp hatırlatma"
has "$OUT/dash.html" "Bugün Telegram hatırlatma"
has "$OUT/dash.html" "Bugün yeni üye"
has "$OUT/dash.html" "Bu ay onaylanan ödeme"
has "$OUT/dash.html" "Bağlı cihaz"
has "$OUT/dash.html" "/yonetim/panel/kutucuklar"
DT=$(tok "$OUT/dash.html")
code=$(curl -s -b "$AJ" -o /dev/null -w '%{http_code}' -X POST \
  --data-urlencode "_token=$DT" --data-urlencode "widgets[]=customers" --data-urlencode "widgets[]=new_today" \
  "$BASE/yonetim/panel/kutucuklar")
check "kutucuk seçimi kaydı" "$code" "302"
check "seçim ayarı" "$($MY -e "SELECT COUNT(*) FROM settings WHERE \`key\`='dashboard.widgets'")" "1"
curl -s -b "$AJ" "$BASE/yonetim/" -o "$OUT/dash_subset.html"
has "$OUT/dash_subset.html" "Bugün yeni üye"
grep -q 'stat__value' "$OUT/dash_subset.html" && ok "seçili kutucuklar render edildi" || bad "kutucuk yok"
COUNT=$($PHP -r '$s=require "app/bootstrap.php"; $w=App\Core\Settings::get("dashboard.widgets", []); echo count($w);')
check "kaydedilen kutucuk sayısı" "$COUNT" "2"
$PHP -r 'require "app/bootstrap.php"; App\Core\Settings::forget("dashboard.widgets");' >/dev/null
curl -s -b "$AJ" "$BASE/yonetim/" -o "$OUT/dash_default.html"
has "$OUT/dash_default.html" "Bugün e-posta hatırlatma"
has "$OUT/dash_default.html" "30 günde bitecek"

echo
echo "SONUÇ: $pass geçti, $fail kaldı"
[ "$fail" -eq 0 ] || exit 1
