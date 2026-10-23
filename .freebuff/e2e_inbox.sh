#!/usr/bin/env bash
# ------------------------------------------------------------
#  Site içi bildirim merkezi + özel duyurular (Faz 5)
#  Gerçek HTTP: hatırlatmanın zile düşmesi, okundu işaretleme,
#  panelden resim/metin/link içeren duyuru gönderimi, hedef kitle,
#  yetki/CSRF ve silme. Sunucu 3001'de ayakta olmalı.
# ------------------------------------------------------------
set -uo pipefail

export HOME="${HOME:-/workspace/homes/9e03ed61266af80267d81f50}"
BASE="http://127.0.0.1:3001"
PHP="$HOME/bin/php/php"
MY="$HOME/mariadb/bin/mariadb --socket=$HOME/mysql-tmp/mysql.sock -uroot --default-character-set=utf8mb4 evrako_live"
MYSQL() { $MY -N -B -e "$1"; }
OUT="/tmp/e2e_inbox"
rm -rf "$OUT"; mkdir -p "$OUT"

PASS=0; FAIL=0
ok()   { PASS=$((PASS+1)); printf '  \033[32m✓\033[0m %s\n' "$1"; }
no()   { FAIL=$((FAIL+1)); printf '  \033[31m✗\033[0m %s\n' "$1"; }
check(){ if [ "$2" = "$3" ]; then ok "$1"; else no "$1 (beklenen: $3, gelen: $2)"; fi; }
has()  { if grep -q "$2" "$3" 2>/dev/null; then ok "$1"; else no "$1 (metin bulunamadı: $2)"; fi; }
hasnt(){ if grep -q "$2" "$3" 2>/dev/null; then no "$1 (beklenmeyen metin: $2)"; else ok "$1"; fi; }
tok()  { grep -o 'name="_token" value="[^"]*"' "$1" | head -1 | sed 's/.*value="//;s/"$//'; }

SUF=$RANDOM
EMAIL="inbox-test-$SUF@example.com"
PASSWORD='InboxTest123!'
ORG_NAME="Inbox Test $SUF"
PLATE="IN$SUF"
BROADCAST_TITLE="Duyuru testi $SUF"

cleanup() {
    MYSQL "DELETE FROM inbox_broadcasts WHERE title LIKE 'Duyuru testi%$SUF%'" >/dev/null 2>&1
    for p in $(MYSQL "SELECT disk_path FROM media WHERE kind='inbox' AND original_name='pixel.png'" 2>/dev/null); do
        rm -f "storage/$p"
    done
    MYSQL "DELETE FROM media WHERE kind='inbox' AND original_name='pixel.png'" >/dev/null 2>&1
    MYSQL "DELETE FROM organizations WHERE name = '$ORG_NAME'" >/dev/null 2>&1
    MYSQL "DELETE FROM users WHERE email = '$EMAIL'" >/dev/null 2>&1
    MYSQL "DELETE FROM user_notifications WHERE user_id = 1 AND dedupe_key = 'e2e:foreign:$SUF'" >/dev/null 2>&1
    MYSQL "DELETE FROM notifications WHERE user_id = 1 AND dedupe_key = 'e2e:foreign:$SUF'" >/dev/null 2>&1
    [ -n "${OLD_ADMIN_HASH:-}" ] && MYSQL "UPDATE users SET password_hash='$OLD_ADMIN_HASH' WHERE id=1" >/dev/null 2>&1
}
trap cleanup EXIT

echo "== Hazırlık =="
MYSQL "SELECT 1" >/dev/null || { echo "MariaDB kapalı"; exit 1; }
curl -s -o /dev/null -w '%{http_code}' "$BASE/" | grep -q 200 || { echo "Sunucu 3001'de yanıt vermiyor"; exit 1; }

HASH=$($PHP -r "echo password_hash('$PASSWORD', PASSWORD_DEFAULT);")
USER_ID=$(MYSQL "INSERT INTO users (full_name,email,password_hash,role,status,locale,email_verified_at,created_at) VALUES ('Inbox Test','$EMAIL','$HASH','customer','active','tr',NOW(),NOW()); SELECT LAST_INSERT_ID();")
ORG_ID=$(MYSQL "INSERT INTO organizations (owner_user_id,name,type,created_at) VALUES ($USER_ID,'$ORG_NAME','individual',NOW()); SELECT LAST_INSERT_ID();")
MYSQL "INSERT INTO organization_members (organization_id,user_id,role,status,created_at) VALUES ($ORG_ID,$USER_ID,'owner','active',NOW())" >/dev/null
VTYPE=$(MYSQL "SELECT id FROM vehicle_types ORDER BY id LIMIT 1")
VEHICLE_ID=$(MYSQL "INSERT INTO vehicles (organization_id,vehicle_type_id,plate,brand,model,status,created_at) VALUES ($ORG_ID,$VTYPE,'$PLATE','Test','Model','active',NOW()); SELECT LAST_INSERT_ID();")
DTYPE=$(MYSQL "SELECT id FROM document_types WHERE \`key\`='inspection' LIMIT 1")
DOC_END=$($PHP -r 'require "app/bootstrap.php"; echo date("Y-m-d", strtotime("+7 day"));')
DOC_ID=$(MYSQL "INSERT INTO vehicle_documents (vehicle_id,document_type_id,end_date,status,created_at) VALUES ($VEHICLE_ID,$DTYPE,'$DOC_END','active',NOW()); SELECT LAST_INSERT_ID();")
echo "  kullanıcı #$USER_ID · kurum #$ORG_ID · araç #$VEHICLE_ID · evrak #$DOC_ID"

echo "== Hatırlatma → site içi bildirim =="
SCAN=$($PHP -r 'require "app/bootstrap.php"; echo json_encode(App\Services\ReminderService::scan());')
echo "  tarama: $SCAN"
check "kuyruğa 1 hatırlatma eklendi" "$(MYSQL "SELECT COUNT(*) FROM notifications WHERE user_id=$USER_ID AND template_key='reminder'")" "1"
check "zile 1 bildirim düştü" "$(MYSQL "SELECT COUNT(*) FROM user_notifications WHERE user_id=$USER_ID AND kind='reminder'")" "1"
check "bildirim başlığında plaka var" "$(MYSQL "SELECT COUNT(*) FROM user_notifications WHERE user_id=$USER_ID AND title LIKE '%$PLATE%'")" "1"
check "evrak bağlantısı doğru" "$(MYSQL "SELECT link_url FROM user_notifications WHERE user_id=$USER_ID LIMIT 1")" "/panel/araclar/$VEHICLE_ID"

$PHP -r 'require "app/bootstrap.php"; App\Services\ReminderService::scan();' >/dev/null
check "ikinci tarama çoğaltmadı" "$(MYSQL "SELECT COUNT(*) FROM user_notifications WHERE user_id=$USER_ID")" "1"

echo "== Müşteri paneli =="
UJ="$OUT/user.jar"
curl -s -c "$UJ" "$BASE/giris" -o "$OUT/user-login.html"
UT=$(tok "$OUT/user-login.html")
curl -s -c "$UJ" -b "$UJ" -X POST \
  --data-urlencode "_token=$UT" --data-urlencode "email=$EMAIL" --data-urlencode "password=$PASSWORD" \
  "$BASE/giris" -o /dev/null -w '' 2>/dev/null

curl -s -b "$UJ" "$BASE/panel" -o "$OUT/panel.html"
check "panel 200" "$(curl -s -o /dev/null -w '%{http_code}' -b "$UJ" "$BASE/panel")" "200"
has "panelde bildirim zili var" "data-inbox-toggle" "$OUT/panel.html"
has "zil rozeti okunmamış sayısını gösteriyor" "inbox__badge" "$OUT/panel.html"

curl -s -b "$UJ" "$BASE/panel/bildirimler" -o "$OUT/list.html"
check "bildirimler sayfası 200" "$(curl -s -o /dev/null -w '%{http_code}' -b "$UJ" "$BASE/panel/bildirimler")" "200"
has "liste hatırlatmayı gösteriyor" "Muayene" "$OUT/list.html"
has "liste plakayı gösteriyor" "$PLATE" "$OUT/list.html"
has "liste hatırlatma etiketi" "Hatırlatma" "$OUT/list.html"

SUMMARY=$(curl -s -b "$UJ" -H 'Accept: application/json' "$BASE/panel/bildirimler/ozet")
echo "  özet: $(echo "$SUMMARY" | head -c 200)"
echo "$SUMMARY" | grep -q '"unread":1' && ok "özet okunmamış=1" || no "özet okunmamış=1 değil"
echo "$SUMMARY" | grep -q "$PLATE" && ok "özet plakayı içeriyor" || no "özet plaka içermiyor"

NOTIF_ID=$(MYSQL "SELECT id FROM user_notifications WHERE user_id=$USER_ID LIMIT 1")
TOKEN=$(tok "$OUT/list.html")
CODE=$(curl -s -o "$OUT/read.json" -w '%{http_code}' -b "$UJ" -X POST \
  -H 'Accept: application/json' --data-urlencode "_token=$TOKEN" --data-urlencode "id=$NOTIF_ID" \
  "$BASE/panel/bildirimler/oku")
check "okundu işaretleme 200" "$CODE" "200"
check "okunmamış 0'a düştü" "$(MYSQL "SELECT COUNT(*) FROM user_notifications WHERE user_id=$USER_ID AND read_at IS NULL")" "0"

MYSQL "UPDATE user_notifications SET read_at=NULL WHERE user_id=$USER_ID" >/dev/null
CODE=$(curl -s -o /dev/null -w '%{http_code}' -b "$UJ" -X POST -H 'Accept: application/json' \
  --data-urlencode "_token=$TOKEN" "$BASE/panel/bildirimler/tumu-oku")
check "tümünü okundu 200" "$CODE" "200"
check "tümü okundu" "$(MYSQL "SELECT COUNT(*) FROM user_notifications WHERE user_id=$USER_ID AND read_at IS NULL")" "0"

MYSQL "UPDATE user_notifications SET read_at=NULL WHERE user_id=$USER_ID" >/dev/null
CODE=$(curl -s -o /dev/null -w '%{http_code}' -b "$UJ" -X POST --data-urlencode "id=$NOTIF_ID" "$BASE/panel/bildirimler/oku")
check "CSRF'siz istek reddedildi (419)" "$CODE" "419"

MYSQL "INSERT INTO user_notifications (user_id,kind,title,body,dedupe_key,created_at) VALUES (1,'system','Yabancı kayıt','x','e2e:foreign:$SUF',NOW())" >/dev/null
FOREIGN_ID=$(MYSQL "SELECT id FROM user_notifications WHERE dedupe_key='e2e:foreign:$SUF' LIMIT 1")
RES=$(curl -s -b "$UJ" -X POST -H 'Accept: application/json' \
  --data-urlencode "_token=$TOKEN" --data-urlencode "id=$FOREIGN_ID" "$BASE/panel/bildirimler/oku")
echo "$RES" | grep -q '"ok":false' && ok "başka kullanıcının bildirimi okundu sayılmadı" || no "başka kullanıcının bildirimi korunmadı ($RES)"
check "yabancı kayıt okunmamış kaldı" "$(MYSQL "SELECT COUNT(*) FROM user_notifications WHERE id=$FOREIGN_ID AND read_at IS NULL")" "1"

echo "== Yönetim: duyuru listesi ve formu =="
OLD_ADMIN_HASH=$(MYSQL "SELECT password_hash FROM users WHERE id=1")
NEW_ADMIN_HASH=$($PHP -r "echo password_hash('TempAdmin123!', PASSWORD_DEFAULT);")
MYSQL "UPDATE users SET password_hash='$NEW_ADMIN_HASH' WHERE id=1" >/dev/null
AJ="$OUT/admin.jar"
curl -s -c "$AJ" "$BASE/yonetim/giris" -o "$OUT/admin-login.html"
AT=$(tok "$OUT/admin-login.html")
curl -s -c "$AJ" -b "$AJ" -X POST \
  --data-urlencode "_token=$AT" --data-urlencode "email=admin@evrako.test" --data-urlencode "password=TempAdmin123!" \
  "$BASE/yonetim/giris" -o /dev/null

curl -s -b "$AJ" "$BASE/yonetim/duyurular" -o "$OUT/admin-list.html"
check "duyuru listesi 200" "$(curl -s -o /dev/null -w '%{http_code}' -b "$AJ" "$BASE/yonetim/duyurular")" "200"
has "menüde duyurular var" "/yonetim/duyurular" "$OUT/admin-list.html"
curl -s -b "$AJ" "$BASE/yonetim/duyurular/yeni" -o "$OUT/admin-form.html"
check "yeni duyuru formu 200" "$(curl -s -o /dev/null -w '%{http_code}' -b "$AJ" "$BASE/yonetim/duyurular/yeni")" "200"
has "formda hedef kitle alanı" "audience_user_email" "$OUT/admin-form.html"
has "formda görsel alanı" 'type="file" name="image"' "$OUT/admin-form.html"

echo "== Duyuru gönderimi (metin + link, tek kullanıcı) =="
AT=$(tok "$OUT/admin-form.html")
LOC=$(curl -s -o /dev/null -w '%{redirect_url}' -b "$AJ" -X POST \
  --data-urlencode "_token=$AT" --data-urlencode "title=$BROADCAST_TITLE" \
  --data-urlencode "body=Kampanya metni: 31 Ekim'e kadar indirim." \
  --data-urlencode "link_url=/fiyatlar" --data-urlencode "link_label=Kampanyayı gör" \
  --data-urlencode "audience_user_email=$EMAIL" "$BASE/yonetim/duyurular")
echo "  yönlendirme: $LOC"
check "duyuru oluşturuldu ve detay sayfasına gitti" "1" "$(echo "$LOC" | grep -q '/yonetim/duyurular/[0-9]' && echo 1 || echo 0)"
BID=$(MYSQL "SELECT id FROM inbox_broadcasts WHERE title='$BROADCAST_TITLE' LIMIT 1")
check "duyuru kaydı oluştu" "1" "$([ -n "$BID" ] && echo 1 || echo 0)"
check "tek kullanıcıya ulaştı" "$(MYSQL "SELECT COUNT(*) FROM user_notifications WHERE broadcast_id=$BID AND user_id=$USER_ID")" "1"
check "link ve buton yazısı kaydedildi" "$(MYSQL "SELECT CONCAT(link_url,'|',link_label) FROM user_notifications WHERE broadcast_id=$BID LIMIT 1")" "/fiyatlar|Kampanyayı gör"

curl -s -b "$AJ" "$BASE/yonetim/duyurular/$BID" -o "$OUT/admin-show.html"
has "detayda başlık var" "$BROADCAST_TITLE" "$OUT/admin-show.html"
has "detayda ulaşan sayısı var" "Ulaşan kullanıcı" "$OUT/admin-show.html"
has "detayda alıcı listesi var" "$EMAIL" "$OUT/admin-show.html"

curl -s -b "$UJ" "$BASE/panel/bildirimler" -o "$OUT/list2.html"
has "duyuru müşteri listesine düştü" "$BROADCAST_TITLE" "$OUT/list2.html"
has "duyuru butonu görünüyor" "Kampanyayı gör" "$OUT/list2.html"

echo "== Duyuru gönderimi (görselli, kurum hedefi) =="
printf 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' | base64 -d > "$OUT/pixel.png"
IMG_TITLE="Duyuru testi görsel $SUF"
LOC2=$(curl -s -o /dev/null -w '%{redirect_url}' -b "$AJ" -X POST \
  -F "_token=$AT" -F "title=$IMG_TITLE" -F "body=Görselli duyuru metni." \
  -F "link_url=https://example.com/kampanya" -F "link_label=İncele" \
  -F "audience_org_id=$ORG_ID" -F "image=@$OUT/pixel.png;type=image/png" \
  "$BASE/yonetim/duyurular")
IMG_BID=$(MYSQL "SELECT id FROM inbox_broadcasts WHERE title='$IMG_TITLE' LIMIT 1")
check "görselli duyuru oluştu" "1" "$([ -n "$IMG_BID" ] && echo 1 || echo 0)"
IMG_PATH=$(MYSQL "SELECT image_path FROM inbox_broadcasts WHERE id=$IMG_BID LIMIT 1")
check "görsel yolu kaydedildi" "1" "$(echo "$IMG_PATH" | grep -q '^uploads/inbox/' && echo 1 || echo 0)"
check "görsel dosyası diskte" "1" "$([ -f "storage/$IMG_PATH" ] && echo 1 || echo 0)"
check "kurum üyesine ulaştı" "$(MYSQL "SELECT COUNT(*) FROM user_notifications WHERE broadcast_id=$IMG_BID AND user_id=$USER_ID")" "1"
check "harici link kabul edildi" "$(MYSQL "SELECT link_url FROM user_notifications WHERE broadcast_id=$IMG_BID LIMIT 1")" "https://example.com/kampanya"
curl -s -b "$UJ" "$BASE/panel/bildirimler" -o "$OUT/list3.html"
has "görsel bildirim sayfasında render edildi" '<a class="inbox-card__media"' "$OUT/list3.html"
has "görsel kart içinde img olarak var" '<img src="[^"]*uploads/inbox' "$OUT/list3.html"
has "görsel /media URL'i ile servis edildi" "uploads/inbox/" "$OUT/list3.html"

curl -s -b "$AJ" "$BASE/yonetim/duyurular" -o "$OUT/admin-list2.html"
has "yönetim listesi duyuruyu gösteriyor" "$IMG_TITLE" "$OUT/admin-list2.html"
has "yönetim listesi görsel küçük resmini gösteriyor" "uploads/inbox/" "$OUT/admin-list2.html"

echo "== Hedef kitle doğrulaması =="
BEFORE=$(MYSQL "SELECT COUNT(*) FROM inbox_broadcasts")
CODE=$(curl -s -o /dev/null -w '%{http_code}' -b "$AJ" -X POST \
  --data-urlencode "_token=$AT" --data-urlencode "title=Bulunmayan $SUF" --data-urlencode "body=x" \
  --data-urlencode "audience_user_email=yok-$SUF@example.com" "$BASE/yonetim/duyurular")
check "olmayan kullanıcı adresine gönderim engellendi" "$(MYSQL "SELECT COUNT(*) FROM inbox_broadcasts")" "$BEFORE"
check "forma geri yönlendirildi" "1" "$([ "$CODE" = "302" ] && echo 1 || echo 0)"

echo "== Silme =="
SHOW_TOK=$(tok "$OUT/admin-show.html")
CODE=$(curl -s -o /dev/null -w '%{http_code}' -b "$AJ" -X POST \
  --data-urlencode "_token=$SHOW_TOK" --data-urlencode "id=$BID" "$BASE/yonetim/duyurular/sil")
check "silme isteği 302" "$CODE" "302"
check "duyuru silindi" "$(MYSQL "SELECT COUNT(*) FROM inbox_broadcasts WHERE id=$BID")" "0"
check "kullanıcı bildirimleri de silindi" "$(MYSQL "SELECT COUNT(*) FROM user_notifications WHERE broadcast_id=$BID")" "0"

echo
echo "== Sonuç: $PASS geçti, $FAIL kaldı =="
exit $([ "$FAIL" -eq 0 ] && echo 0 || echo 1)
