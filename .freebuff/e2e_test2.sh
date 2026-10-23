#!/usr/bin/env bash
# Faz 2 (kalan) uçtan uca test — takvim, .ics, rapor, ekip/davet/yetki
set -u
BASE="http://127.0.0.1:3001"
export HOME="${HOME:-/workspace/homes/9e03ed61266af80267d81f50}"
PHP="$HOME/bin/php/php"
MYSQL="$HOME/mariadb/bin/mariadb --socket=$HOME/mysql-tmp/mysql.sock -uroot evrako_live -N -B"
J="/tmp/t2_j.txt"; rm -f "$J"
J2="/tmp/t2_j2.txt"; rm -f "$J2"
CD="/workspace/chats/9e03ed61266af80267d81f50"

pass=0; fail=0
ok(){ echo "  ✓ $1"; pass=$((pass+1)); }
bad(){ echo "  ✗ $1"; fail=$((fail+1)); }
check(){ [ "$2" = "$3" ] && ok "$1 ($2)" || bad "$1 — beklenen '$3', gelen '$2'"; }
tok(){ grep -o 'name="_token" value="[^"]*"' "$1" | head -1 | sed 's/.*value="//;s/"$//'; }
login(){ # jar email pass
  local jar=$1 em=$2 pw=$3 t
  curl -s -c "$jar" "$BASE/giris" -o /tmp/t2_login.html
  t=$(tok /tmp/t2_login.html)
  curl -s -b "$jar" -c "$jar" -o /dev/null --data-urlencode "_token=$t" --data-urlencode "email=$em" --data-urlencode "password=$pw" "$BASE/giris"
}

echo "== login (owner ayse) =="
login "$J" "ayse@example.com" "Test1234!"
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' "$BASE/panel/ekip")
check "GET /panel/ekip" "$CODE" "200"

echo "== araç + evrak (bu ay bitecek şekilde) =="
TYPE_ID=$($MYSQL -e "SELECT id FROM vehicle_types WHERE \`key\`='car' LIMIT 1")
curl -s -b "$J" -c "$J" "$BASE/panel/araclar/yeni" -o /tmp/t2_n.html; T=$(tok /tmp/t2_n.html)
curl -s -b "$J" -c "$J" -o /dev/null --data-urlencode "_token=$T" --data-urlencode "plate=CAL 1" --data-urlencode "vehicle_type_id=$TYPE_ID" --data-urlencode "brand=Kia" --data-urlencode "model=Sportage" "$BASE/panel/araclar"
VID=$($MYSQL -e "SELECT id FROM vehicles WHERE plate='CAL 1' AND deleted_at IS NULL LIMIT 1")
[ -n "$VID" ] && ok "Araç oluşturuldu (id=$VID)" || bad "Araç oluşturulamadı"
DT=$($MYSQL -e "SELECT id FROM document_types WHERE \`key\`='insurance' LIMIT 1")
MONTH=$(date +%Y-%m); END=$(date +%Y-%m-20)
curl -s -b "$J" -c "$J" "$BASE/panel/araclar/$VID" -o /tmp/t2_s.html; T=$(tok /tmp/t2_s.html)
curl -s -b "$J" -c "$J" -o /dev/null --data-urlencode "_token=$T" --data-urlencode "document_type_id=$DT" --data-urlencode "issuer=Takvim Sigorta" --data-urlencode "end_date=$END" "$BASE/panel/araclar/$VID/evrak"
DID=$($MYSQL -e "SELECT id FROM vehicle_documents WHERE vehicle_id=$VID AND status='active' LIMIT 1")
[ -n "$DID" ] && ok "Evrak oluşturuldu (id=$DID, bitiş=$END)" || bad "Evrak yok"

echo "== takvim =="
CODE=$(curl -s -b "$J" -c "$J" -o /tmp/t2_cal.html -w '%{http_code}' "$BASE/panel/takvim?ay=$MONTH")
check "GET /panel/takvim" "$CODE" "200"
grep -q "CAL 1" /tmp/t2_cal.html && ok "Takvimde plaka görünüyor" || bad "Takvimde plaka yok"
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' "$BASE/panel/takvim?ay=2020-01")
check "Takvim (başka ay)" "$CODE" "200"

echo "== .ics =="
HDR=$(curl -s -b "$J" -c "$J" -D - -o /tmp/t2_ics.txt -w '' "$BASE/panel/evrak/$DID/takvim.ics")
echo "$HDR" | grep -qi "Content-Type: text/calendar" && ok ".ics Content-Type doğru" || bad ".ics Content-Type hatalı: $(echo "$HDR" | grep -i content-type)"
grep -q "BEGIN:VCALENDAR" /tmp/t2_ics.txt && ok ".ics gövdesi VCALENDAR" || bad ".ics gövdesi hatalı"
grep -q "BEGIN:VALARM" /tmp/t2_ics.txt && ok ".ics içinde hatırlatma (VALARM)" || bad "VALARM yok"
grep -qi "SUMMARY:.*CAL 1" /tmp/t2_ics.txt && ok ".ics SUMMARY plaka içeriyor" || bad "SUMMARY plaka içermiyor"

echo "== rapor (yazdırılabilir) =="
CODE=$(curl -s -b "$J" -c "$J" -o /tmp/t2_rep.html -w '%{http_code}' "$BASE/panel/araclar/$VID/rapor")
check "GET rapor" "$CODE" "200"
grep -q "CAL 1" /tmp/t2_rep.html && ok "Raporda plaka var" || bad "Raporda plaka yok"
grep -q "window.print" /tmp/t2_rep.html && ok "Rapor yazdırma düğmesi var" || bad "Yazdırma düğmesi yok"

echo "== ekip: davet (yeni e-posta) =="
NEWMAIL="testuye1@example.com"
$MYSQL -e "DELETE FROM organization_members WHERE invited_email LIKE 'testuye%@example.com'; DELETE FROM users WHERE email LIKE 'testuye%@example.com'" >/dev/null 2>&1
T=$(tok /tmp/t2_s.html); curl -s -b "$J" -c "$J" "$BASE/panel/ekip" -o /tmp/t2_team.html; T=$(tok /tmp/t2_team.html)
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$T" --data-urlencode "email=$NEWMAIL" --data-urlencode "role=viewer" --data-urlencode "can_manage_documents=1" \
  "$BASE/panel/ekip/davet")
check "POST davet" "$CODE" "302"
UID1=$($MYSQL -e "SELECT id FROM users WHERE email='$NEWMAIL' LIMIT 1")
[ -n "$UID1" ] && ok "Davetli kullanıcı oluşturuldu (id=$UID1, passive)" || bad "Davetli kullanıcı yok"
USTATUS=$($MYSQL -e "SELECT status FROM users WHERE id=$UID1")
check "Kullanıcı durumu passive" "$USTATUS" "passive"
MSTATUS=$($MYSQL -e "SELECT status FROM organization_members WHERE organization_id=1 AND user_id=$UID1")
check "Üyelik durumu invited" "$MSTATUS" "invited"
NT=$($MYSQL -e "SELECT COUNT(*) FROM auth_tokens WHERE user_id=$UID1 AND type='invite' AND used_at IS NULL")
[ "$NT" -ge 1 ] && ok "Davet tokenı oluşturuldu" || bad "Davet tokenı yok"

echo "== ekip: davet kabul akışı =="
TOKEN=$($PHP -r "require '$CD/app/bootstrap.php'; echo App\Core\Auth::createToken($UID1,'invite',3600);")
CODE=$(curl -s -c "$J2" -o /tmp/t2_acc.html -w '%{http_code}' "$BASE/davet?token=$TOKEN")
check "GET /davet?token" "$CODE" "200"
grep -q "Kuruma katıl" /tmp/t2_acc.html && ok "Kabul formu gösterildi" || bad "Kabul formu yok"
AT=$(tok /tmp/t2_acc.html)
CODE=$(curl -s -b "$J2" -c "$J2" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$AT" --data-urlencode "token=$TOKEN" --data-urlencode "full_name=Test Üye Bir" \
  --data-urlencode "password=Uye1234!" --data-urlencode "password_confirmation=Uye1234!" "$BASE/davet")
check "POST davet kabul" "$CODE" "302"
USTATUS=$($MYSQL -e "SELECT status FROM users WHERE id=$UID1")
check "Kullanıcı aktifleşti" "$USTATUS" "active"
MSTATUS=$($MYSQL -e "SELECT status FROM organization_members WHERE organization_id=1 AND user_id=$UID1")
check "Üyelik aktifleşti" "$MSTATUS" "active"

echo "== yetki: üye (viewer) davet gönderemez =="
BEFORE=$($MYSQL -e "SELECT COUNT(*) FROM organization_members WHERE organization_id=1")
CODE=$(curl -s -b "$J2" -c "$J2" "$BASE/panel/ekip" -o /tmp/t2_team2.html -w '%{http_code}' >/dev/null; curl -s -b "$J2" -c "$J2" -o /dev/null -w '%{http_code}' "$BASE/panel/ekip")
check "Üye /panel/ekip erişimi" "$CODE" "200"
T2=$(tok /tmp/t2_team2.html)
curl -s -b "$J2" -c "$J2" -o /dev/null --data-urlencode "_token=$T2" --data-urlencode "email=hack@example.com" --data-urlencode "role=manager" "$BASE/panel/ekip/davet"
AFTER=$($MYSQL -e "SELECT COUNT(*) FROM organization_members WHERE organization_id=1")
check "Viewer davet engellendi" "$BEFORE" "$AFTER"

echo "== plan limiti: max_users = 3 =="
MAXU=$($MYSQL -e "SELECT p.max_users FROM subscriptions s JOIN plans p ON p.id=s.plan_id WHERE s.organization_id=1 AND s.status='active' LIMIT 1")
echo "  (Aile max_users=$MAXU)"
# şu an: owner + testuye1 = 2 → 1 davet daha (3), sonra 1 daha denemeli engellenmeli
curl -s -b "$J" -c "$J" "$BASE/panel/ekip" -o /tmp/t2_team.html; T=$(tok /tmp/t2_team.html)
curl -s -b "$J" -c "$J" -o /dev/null --data-urlencode "_token=$T" --data-urlencode "email=testuye2@example.com" --data-urlencode "role=viewer" "$BASE/panel/ekip/davet"
USED=$($MYSQL -e "SELECT COUNT(*) FROM organization_members WHERE organization_id=1 AND status<>'removed'")
check "Limit sınırında üye sayısı (max_users=$MAXU)" "$USED" "$MAXU"
curl -s -b "$J" -c "$J" "$BASE/panel/ekip" -o /tmp/t2_team.html; T=$(tok /tmp/t2_team.html)
curl -s -b "$J" -c "$J" -o /dev/null --data-urlencode "_token=$T" --data-urlencode "email=testuye3@example.com" --data-urlencode "role=viewer" "$BASE/panel/ekip/davet"
USED2=$($MYSQL -e "SELECT COUNT(*) FROM organization_members WHERE organization_id=1 AND status<>'removed'")
check "Limit aşımı engellendi" "$USED" "$USED2"
U3=$($MYSQL -e "SELECT COUNT(*) FROM users WHERE email='testuye3@example.com'")
check "Limit doluyken kullanıcı açılmadı" "$U3" "0"

echo "== yetki matrisi: izin güncelle =="
MID=$($MYSQL -e "SELECT id FROM organization_members WHERE organization_id=1 AND user_id=$UID1 LIMIT 1")
T=$(tok /tmp/t2_team.html)
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$T" --data-urlencode "role=manager" --data-urlencode "can_manage_vehicles=1" --data-urlencode "can_manage_documents=1" --data-urlencode "can_manage_billing=1" \
  "$BASE/panel/ekip/$MID")
check "POST üye güncelle" "$CODE" "302"
ROW=$($MYSQL -e "SELECT CONCAT(role,'/',can_manage_vehicles,'/',can_manage_documents,'/',can_manage_billing) FROM organization_members WHERE id=$MID")
check "İzinler kaydedildi" "$ROW" "manager/1/1/1"

echo "== üye kaldır =="
T=$(tok /tmp/t2_team.html)
curl -s -b "$J" -c "$J" -o /dev/null --data-urlencode "_token=$T" "$BASE/panel/ekip/$MID/kaldir"
MSTATUS=$($MYSQL -e "SELECT status FROM organization_members WHERE id=$MID")
check "Üye kaldırıldı" "$MSTATUS" "removed"

echo "== CSRF'siz davet reddedilmeli =="
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' --data-urlencode "email=x@example.com" "$BASE/panel/ekip/davet")
check "CSRF'siz davet -> 419" "$CODE" "419"

echo
echo "SONUÇ: $pass geçti, $fail kaldı"

echo "== temizlik =="
$MYSQL -e "DELETE FROM auth_tokens WHERE user_id IN (SELECT id FROM users WHERE email LIKE 'testuye%@example.com'); DELETE FROM organization_members WHERE invited_email LIKE 'testuye%@example.com' OR user_id IN (SELECT id FROM users WHERE email LIKE 'testuye%@example.com'); DELETE FROM users WHERE email LIKE 'testuye%@example.com'; DELETE FROM vehicle_documents WHERE vehicle_id=$VID; DELETE FROM vehicles WHERE id=$VID;" >/dev/null 2>&1
echo "kalan test üyesi: $($MYSQL -e "SELECT COUNT(*) FROM users WHERE email LIKE 'testuye%@example.com'")"
echo "kalan test aracı: $($MYSQL -e "SELECT COUNT(*) FROM vehicles WHERE plate='CAL 1'")"
exit $fail
