#!/usr/bin/env bash
set -u
BASE="http://127.0.0.1:3001"
export HOME="${HOME:-/workspace/homes/9e03ed61266af80267d81f50}"
PHP="$HOME/bin/php/php"
MYSQL="$HOME/mariadb/bin/mariadb --socket=$HOME/mysql-tmp/mysql.sock -uroot evrako_live -N -B"
CD="/workspace/chats/9e03ed61266af80267d81f50"
JO=/tmp/t3_owner.txt; JM=/tmp/t3_member.txt; rm -f $JO $JM
pass=0; fail=0
ok(){ echo "  ✓ $1"; pass=$((pass+1)); }
bad(){ echo "  ✗ $1"; fail=$((fail+1)); }
check(){ [ "$2" = "$3" ] && ok "$1 ($2)" || bad "$1 — beklenen '$3', gelen '$2'"; }
tok(){ grep -o 'name="_token" value="[^"]*"' "$1" | head -1 | sed 's/.*value="//;s/"$//'; }
login(){ curl -s -c "$1" "$BASE/giris" -o /tmp/t3_l.html; local t=$(tok /tmp/t3_l.html); curl -s -b "$1" -c "$1" -o /dev/null --data-urlencode "_token=$t" --data-urlencode "email=$2" --data-urlencode "password=$3" "$BASE/giris"; }

TEMPMAIL="testperm@example.com"
$MYSQL -e "DELETE FROM auth_tokens WHERE user_id IN (SELECT id FROM users WHERE email='$TEMPMAIL'); DELETE FROM organization_members WHERE invited_email='$TEMPMAIL' OR user_id IN (SELECT id FROM users WHERE email='$TEMPMAIL'); DELETE FROM users WHERE email='$TEMPMAIL';" >/dev/null 2>&1

echo "== owner giriş + geçici araç =="
login "$JO" "ayse@example.com" "Test1234!"
TYPE_ID=$($MYSQL -e "SELECT id FROM vehicle_types WHERE \`key\`='car' LIMIT 1")
curl -s -b "$JO" -c "$JO" "$BASE/panel/araclar/yeni" -o /tmp/t3_n.html; T=$(tok /tmp/t3_n.html)
curl -s -b "$JO" -c "$JO" -o /dev/null --data-urlencode "_token=$T" --data-urlencode "plate=PERM 1" --data-urlencode "vehicle_type_id=$TYPE_ID" "$BASE/panel/araclar"
VID=$($MYSQL -e "SELECT id FROM vehicles WHERE plate='PERM 1' AND deleted_at IS NULL LIMIT 1")
ok "Geçici araç (id=$VID)"

echo "== davet: yalnızca evrak izni =="
curl -s -b "$JO" -c "$JO" "$BASE/panel/ekip" -o /tmp/t3_team.html; T=$(tok /tmp/t3_team.html)
curl -s -b "$JO" -c "$JO" -o /dev/null --data-urlencode "_token=$T" --data-urlencode "email=$TEMPMAIL" --data-urlencode "role=viewer" --data-urlencode "can_manage_documents=1" "$BASE/panel/ekip/davet"
MUID=$($MYSQL -e "SELECT id FROM users WHERE email='$TEMPMAIL' LIMIT 1")
ok "Davetli kullanıcı (id=$MUID)"
TOKEN=$($PHP -r "require '$CD/app/bootstrap.php'; echo App\Core\Auth::createToken($MUID,'invite',3600);")
curl -s -c "$JM" -o /tmp/t3_acc.html "$BASE/davet?token=$TOKEN"; AT=$(tok /tmp/t3_acc.html)
curl -s -b "$JM" -c "$JM" -o /dev/null --data-urlencode "_token=$AT" --data-urlencode "token=$TOKEN" --data-urlencode "full_name=Perm Test" --data-urlencode "password=Uye1234!" --data-urlencode "password_confirmation=Uye1234!" "$BASE/davet"

echo "== üye: araç ekleyemez (yetki yok) =="
BEFORE=$($MYSQL -e "SELECT COUNT(*) FROM vehicles WHERE organization_id=1 AND deleted_at IS NULL")
curl -s -b "$JM" -c "$JM" "$BASE/panel/araclar/yeni" -o /tmp/t3_y.html
CODE=$(curl -s -b "$JM" -c "$JM" -o /dev/null -w '%{http_code}' "$BASE/panel/araclar/yeni")
check "Üye /yeni -> yönlendirme" "$CODE" "302"
T=$(tok /tmp/t3_y.html)
curl -s -b "$JM" -c "$JM" -o /dev/null --data-urlencode "_token=$T" --data-urlencode "plate=PERM 2" --data-urlencode "vehicle_type_id=$TYPE_ID" "$BASE/panel/araclar"
AFTER=$($MYSQL -e "SELECT COUNT(*) FROM vehicles WHERE organization_id=1 AND deleted_at IS NULL")
check "Üye araç ekleyemedi" "$BEFORE" "$AFTER"

echo "== üye: evrak ekleyebilir (yetki var) =="
DT=$($MYSQL -e "SELECT id FROM document_types WHERE \`key\`='inspection' LIMIT 1")
curl -s -b "$JM" -c "$JM" "$BASE/panel/araclar/$VID" -o /tmp/t3_s.html; T=$(tok /tmp/t3_s.html)
END=$(date -d "+60 days" +%Y-%m-%d)
CODE=$(curl -s -b "$JM" -c "$JM" -o /dev/null -w '%{http_code}' --data-urlencode "_token=$T" --data-urlencode "document_type_id=$DT" --data-urlencode "end_date=$END" "$BASE/panel/araclar/$VID/evrak")
check "Üye evrak ekledi" "$CODE" "302"
DC=$($MYSQL -e "SELECT COUNT(*) FROM vehicle_documents WHERE vehicle_id=$VID AND status='active'")
check "Evrak DB'de" "$DC" "1"

echo "== mevcut aktif kullanıcı daveti (admin) =="
# Boş koltuk şart: org 1'in aktif planının max_users değeri testin kendi üyelerinden
# bağımsız olsun diye geçici olarak yükseltilir, sonunda geri yazılır.
PLAN_ID=$($MYSQL -e "SELECT plan_id FROM subscriptions WHERE organization_id=1 AND status='active' LIMIT 1")
OLD_MAXU=$($MYSQL -e "SELECT max_users FROM plans WHERE id=$PLAN_ID")
$MYSQL -e "UPDATE plans SET max_users=50 WHERE id=$PLAN_ID" >/dev/null 2>&1
curl -s -b "$JO" -c "$JO" "$BASE/panel/ekip" -o /tmp/t3_team.html; T=$(tok /tmp/t3_team.html)
CODE=$(curl -s -b "$JO" -c "$JO" -o /dev/null -w '%{http_code}' --data-urlencode "_token=$T" --data-urlencode "email=admin@evrako.test" --data-urlencode "role=viewer" "$BASE/panel/ekip/davet")
check "POST davet (mevcut kullanıcı)" "$CODE" "302"
ADMSTATUS=$($MYSQL -e "SELECT m.status FROM organization_members m JOIN users u ON u.id=m.user_id WHERE u.email='admin@evrako.test' AND m.organization_id=1 LIMIT 1")
check "Mevcut kullanıcı doğrudan eklenir (active)" "$ADMSTATUS" "active"
# fixture geri alma: eklenen üyelik ve plan limiti
$MYSQL -e "DELETE FROM organization_members WHERE organization_id=1 AND user_id=(SELECT id FROM users WHERE email='admin@evrako.test')" >/dev/null 2>&1
$MYSQL -e "UPDATE plans SET max_users=$OLD_MAXU WHERE id=$PLAN_ID" >/dev/null 2>&1

echo "== sahip değiştirilemez =="
OID=$($MYSQL -e "SELECT id FROM organization_members WHERE organization_id=1 AND role='owner' LIMIT 1")
T=$(tok /tmp/t3_team.html)
curl -s -b "$JO" -c "$JO" -o /dev/null --data-urlencode "_token=$T" --data-urlencode "role=viewer" "$BASE/panel/ekip/$OID"
OSTATUS=$($MYSQL -e "SELECT role FROM organization_members WHERE id=$OID")
check "Sahip rolü korunuyor" "$OSTATUS" "owner"

echo
echo "SONUÇ: $pass geçti, $fail kaldı"

echo "== temizlik =="
$MYSQL -e "DELETE FROM auth_tokens WHERE user_id IN (SELECT id FROM users WHERE email='$TEMPMAIL'); DELETE FROM organization_members WHERE invited_email='$TEMPMAIL' OR user_id IN (SELECT id FROM users WHERE email='$TEMPMAIL'); DELETE FROM users WHERE email='$TEMPMAIL'; DELETE FROM organization_members WHERE user_id=(SELECT id FROM users WHERE email='admin@evrako.test') AND organization_id=1 AND role<>'owner'; DELETE FROM vehicle_documents WHERE vehicle_id=$VID; DELETE FROM vehicles WHERE plate='PERM 1'; DELETE FROM vehicles WHERE plate='PERM 2';" >/dev/null 2>&1
echo "kalan perm aracı: $($MYSQL -e "SELECT COUNT(*) FROM vehicles WHERE plate LIKE 'PERM %'")"
echo "kalan perm üyesi: $($MYSQL -e "SELECT COUNT(*) FROM users WHERE email='$TEMPMAIL'")"
exit $fail
