#!/usr/bin/env bash
# Faz 2 uçtan uca test — gerçek MariaDB + PHP dev sunucusu
set -u
export HOME="${HOME:-/workspace/homes/9e03ed61266af80267d81f50}"
BASE="http://127.0.0.1:3001"
J="/tmp/evrako_cj.txt"; rm -f "$J"
MYSQL="$HOME/mariadb/bin/mariadb --socket=$HOME/mysql-tmp/mysql.sock -uroot evrako_live -N -B"

pass=0; fail=0
ok()   { echo "  ✓ $1"; pass=$((pass+1)); }
bad()  { echo "  ✗ $1"; fail=$((fail+1)); }
check(){ if [ "$2" = "$3" ]; then ok "$1 ($2)"; else bad "$1 — beklenen '$3', gelen '$2'"; fi; }

token_from() { grep -o 'name="_token" value="[^"]*"' "$1" | head -1 | sed 's/.*value="//;s/"$//'; }

echo "== 1) Giriş yap =="
curl -s -c "$J" "$BASE/giris" -o /tmp/pkg_login.html
TOK=$(token_from /tmp/pkg_login.html)
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$TOK" --data-urlencode "email=ayse@example.com" --data-urlencode "password=Test1234!" \
  "$BASE/giris")
check "POST /giris" "$CODE" "302"

echo "== 2) Araç listesi erişimi =="
CODE=$(curl -s -b "$J" -c "$J" -o /tmp/pkg_list.html -w '%{http_code}' "$BASE/panel/araclar")
check "GET /panel/araclar" "$CODE" "200"
grep -q "Araçlar" /tmp/pkg_list.html && ok "Araç listesi render edildi" || bad "Araç listesi render edilmedi"

echo "== 3) Araç oluştur =="
curl -s -b "$J" -c "$J" "$BASE/panel/araclar/yeni" -o /tmp/pkg_new.html
TOK=$(token_from /tmp/pkg_new.html)
TYPE_ID=$($MYSQL -e "SELECT id FROM vehicle_types WHERE \`key\`='car' LIMIT 1" 2>/dev/null)
echo "  (vehicle_type_id=$TYPE_ID)"
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$TOK" --data-urlencode "plate=AB 123" --data-urlencode "vehicle_type_id=$TYPE_ID" \
  --data-urlencode "brand=Honda" --data-urlencode "model=Civic" --data-urlencode "model_year=2019" \
  "$BASE/panel/araclar")
check "POST /panel/araclar" "$CODE" "302"
VID=$($MYSQL -e "SELECT id FROM vehicles WHERE plate='AB 123' AND deleted_at IS NULL LIMIT 1" 2>/dev/null)
[ -n "$VID" ] && ok "Araç DB'ye yazıldı (id=$VID)" || bad "Araç DB'ye yazılmadı"

echo "== 4) Aynı plaka ikinci kez (tekrar engeli) =="
curl -s -b "$J" -c "$J" "$BASE/panel/araclar/yeni" -o /tmp/pkg_new2.html
TOK=$(token_from /tmp/pkg_new2.html)
BEFORE=$($MYSQL -e "SELECT COUNT(*) FROM vehicles WHERE organization_id=1 AND deleted_at IS NULL" 2>/dev/null)
curl -s -b "$J" -c "$J" -o /dev/null \
  --data-urlencode "_token=$TOK" --data-urlencode "plate=AB 123" --data-urlencode "vehicle_type_id=$TYPE_ID" \
  "$BASE/panel/araclar"
AFTER=$($MYSQL -e "SELECT COUNT(*) FROM vehicles WHERE organization_id=1 AND deleted_at IS NULL" 2>/dev/null)
check "Çift plaka engellendi" "$BEFORE" "$AFTER"

echo "== 5) Evrak ekle (belge yüklemeli) =="
printf '%%PDF-1.4 test police' > /tmp/police.pdf
DOC_TYPE=$($MYSQL -e "SELECT id FROM document_types WHERE \`key\`='insurance' LIMIT 1" 2>/dev/null)
curl -s -b "$J" -c "$J" "$BASE/panel/araclar/$VID" -o /tmp/pkg_show.html
TOK=$(token_from /tmp/pkg_show.html)
END=$(date -d "+20 days" +%Y-%m-%d)
START=$(date +%Y-%m-%d)
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' \
  -F "_token=$TOK" -F "document_type_id=$DOC_TYPE" -F "issuer=Test Sigorta" -F "policy_no=POL-999" \
  -F "start_date=$START" -F "end_date=$END" -F "cost=1250.50" -F "files[]=@/tmp/police.pdf;type=application/pdf" \
  "$BASE/panel/araclar/$VID/evrak")
check "POST /panel/araclar/$VID/evrak" "$CODE" "302"
DID=$($MYSQL -e "SELECT id FROM vehicle_documents WHERE vehicle_id=$VID AND status='active' LIMIT 1" 2>/dev/null)
[ -n "$DID" ] && ok "Evrak DB'ye yazıldı (id=$DID)" || bad "Evrak yazılmadı"
FCOUNT=$($MYSQL -e "SELECT COUNT(*) FROM document_files WHERE document_id=$DID" 2>/dev/null)
check "Belge yüklendi" "$FCOUNT" "1"

echo "== 6) Trafik ışığı (20 gün -> sarı) =="
curl -s -b "$J" -c "$J" "$BASE/panel/araclar/$VID" -o /tmp/pkg_show2.html
grep -q "30 günden az" /tmp/pkg_show2.html && ok "Sarı ışık gösterildi" || bad "Sarı ışık yok"

echo "== 7) Yenile (eski kayıt geçmişe) =="
TOK=$(token_from /tmp/pkg_show2.html)
NEWEND=$(date -d "+400 days" +%Y-%m-%d)
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$TOK" --data-urlencode "end_date=$NEWEND" --data-urlencode "cost=1400" \
  "$BASE/panel/evrak/$DID/yenile")
check "POST /panel/evrak/$DID/yenile" "$CODE" "302"
OLD_STATUS=$($MYSQL -e "SELECT status FROM vehicle_documents WHERE id=$DID" 2>/dev/null)
check "Eski kayıt 'renewed'" "$OLD_STATUS" "renewed"
NEWROW=$($MYSQL -e "SELECT id FROM vehicle_documents WHERE renewed_from_id=$DID LIMIT 1" 2>/dev/null)
[ -n "$NEWROW" ] && ok "Yeni dönem kaydı oluştu (id=$NEWROW, renewed_from_id=$DID)" || bad "Yeni dönem kaydı yok"
ACTIVE=$($MYSQL -e "SELECT COUNT(*) FROM vehicle_documents WHERE vehicle_id=$VID AND status='active' AND deleted_at IS NULL" 2>/dev/null)
check "Aktif evrak sayısı" "$ACTIVE" "1"

echo "== 8) CSRF'siz evrak ekleme reddedilmeli =="
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' \
  --data-urlencode "document_type_id=$DOC_TYPE" --data-urlencode "end_date=$NEWEND" \
  "$BASE/panel/araclar/$VID/evrak")
check "CSRF'siz POST -> 419" "$CODE" "419"

echo "== 9) Yetki: kurum üyeliği olmayan kullanıcı müşteri rotasına erişemez =="
# Sabit bir hesaba (admin) bağlı kalmasın: kurumsuz geçici kullanıcı ile denetlenir.
# Not: admin@evrako.test artık kendi kurumunun (org 2) sahibi olduğu için /panel/araclar ona 200 döner.
NOORG="testnoorg@example.com"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
$MYSQL -e "DELETE FROM users WHERE email='$NOORG'" >/dev/null 2>&1
"$HOME/bin/php/php" -r "require '$ROOT/app/bootstrap.php'; App\Core\Database::insert('users', ['full_name'=>'Test No Org','email'=>'$NOORG','password_hash'=>password_hash('Test1234!', PASSWORD_DEFAULT),'role'=>'customer','status'=>'active','email_verified_at'=>date('Y-m-d H:i:s'),'locale'=>'tr','created_at'=>date('Y-m-d H:i:s')]);" >/dev/null 2>&1
J2="/tmp/evrako_noorg.txt"; rm -f "$J2"
curl -s -c "$J2" "$BASE/giris" -o /tmp/pkg_login2.html
TOK2=$(token_from /tmp/pkg_login2.html)
curl -s -b "$J2" -c "$J2" -o /dev/null \
  --data-urlencode "_token=$TOK2" --data-urlencode "email=$NOORG" --data-urlencode "password=Test1234!" \
  "$BASE/giris"
# kurumsuz kullanıcı /panel/araclar -> /panel'e yönlenir (302)
CODE=$(curl -s -b "$J2" -c "$J2" -o /dev/null -w '%{http_code}' "$BASE/panel/araclar")
check "Kurumsuz kullanıcı /panel/araclar -> 302" "$CODE" "302"
$MYSQL -e "DELETE FROM users WHERE email='$NOORG'" >/dev/null 2>&1

echo "== 10) KKTC uyarlaması: evrak türleri, yükleme ipucu, butonlar =="
check "inspection etiketi (Emüasyon kaldırıldı)" \
  "$($MYSQL -e "SELECT JSON_UNQUOTE(JSON_EXTRACT(labels_json,'\$.tr')) FROM document_types WHERE \`key\`='inspection'" 2>/dev/null)" "Muayene"
check "road_tax etiketi (Seyrüsefer)" \
  "$($MYSQL -e "SELECT JSON_UNQUOTE(JSON_EXTRACT(labels_json,'\$.tr')) FROM document_types WHERE \`key\`='road_tax'" 2>/dev/null)" "Seyrüsefer"
check "İşletme/Kiralama türü aktif değil" \
  "$($MYSQL -e "SELECT COUNT(*) FROM document_types WHERE \`key\`='permit' AND is_active=1" 2>/dev/null)" "0"
grep -qF 'name="document_type_id"' /tmp/pkg_show.html && ok "Evrak formu render edildi" || bad "Evrak formu yok (bölüm boş geçti)"
grep -qF 'name="reminder_offsets"' /tmp/pkg_show.html && bad "Evrak formunda hâlâ 'Hatırlatma (gün)' alanı var" || ok "Evrak formunda 'Hatırlatma (gün)' alanı yok"
check "'Hatırlatma (gün)' metni yok" "$(grep -c 'Hatırlatma (gün)' /tmp/pkg_show.html || true)" "0"
grep -qF 'Koçan fotoğrafı veya PDF (en fazla 12 MB)' /tmp/pkg_show.html && ok "Yükleme ipucu Koçan olarak güncellendi" || bad "Yükleme ipucu güncellenmedi"
grep -qF 'Ruhsat/poliçe' /tmp/pkg_show.html && bad "'Ruhsat/poliçe' metni hâlâ duruyor" || ok "'Ruhsat/poliçe' metni kaldırıldı"
grep -qF 'Seyrüsefer' /tmp/pkg_show.html && ok "Evrak türü listesinde Seyrüsefer var" || bad "Seyrüsefer yok"
grep -qF 'İşletme / Kiralama' /tmp/pkg_show.html && bad "Kaldırılan tür hâlâ listede" || ok "İşletme/Kiralama türü listeden kalktı"
grep -qF 'Çıkış Yap' /tmp/pkg_show.html && bad "Başlıkta hâlâ Çıkış Yap var" || ok "Başlıkta Çıkış Yap yok (sadece Panelim)"
curl -s -b "$J" -c "$J" "$BASE/panel" -o /tmp/pkg_panel.html
grep -qE '<a class="btn btn-danger btn-sm" href="[^"]*/cikis"' /tmp/pkg_panel.html && ok "Panel içi çıkış butonu kırmızı (btn-danger)" || bad "Panel içi çıkış butonu kırmızı değil"

echo "== 11) Çıkışsız erişim =="
CODE=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/panel/araclar")
check "Anonim /panel/araclar -> 302" "$CODE" "302"

echo
echo "SONUÇ: $pass geçti, $fail kaldı"
# temizlik
$MYSQL -e "DELETE FROM vehicles WHERE id=$VID" >/dev/null 2>&1
exit $fail
