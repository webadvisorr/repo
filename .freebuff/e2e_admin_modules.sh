#!/usr/bin/env bash
# Yönetim paneli tamamlanan modüller: araçlar, evraklar, denetim, mesajlar, slider.
# Gerçek HTTP + MariaDB; her koşuda kendi fixture'ını kurar ve temizler.
set -u
BASE="http://127.0.0.1:3001"
ROOT="/workspace/chats/9e03ed61266af80267d81f50"
export HOME="${HOME:-/workspace/homes/9e03ed61266af80267d81f50}"
PHP="$HOME/bin/php/php"
MY="$HOME/mariadb/bin/mariadb --no-defaults --socket=$HOME/mysql-tmp/mysql.sock -uroot evrako_live -N -B"
J=/tmp/modules_admin.txt; rm -f "$J"
OUT=/tmp/modules_out; mkdir -p "$OUT"
STAMP=$(date +%s)
EMAIL="mod-$STAMP@evrako.test"
PLATE="E2E$STAMP"
POLICY="POL-$STAMP"
EXPIRED_POLICY="EXPIRE-$STAMP"
MSG_SUBJECT="E2E Mesaj $STAMP"
SLIDE_TITLE="E2E Slayt $STAMP"
IMG_NAME="e2e-slide-$STAMP.png"
ADMIN_EMAIL="admin@evrako.test"
ADMIN_PASS='TempAdmin123!'
pass=0; fail=0
ok(){ echo "  ✓ $1"; pass=$((pass+1)); }
bad(){ echo "  ✗ $1"; fail=$((fail+1)); }
check(){ [ "$2" = "$3" ] && ok "$1 ($2)" || bad "$1 — beklenen '$3', gelen '$2'"; }
has(){ grep -qF "$2" "$1" && ok "$1 içeriyor: $2" || bad "$1 eksik: $2"; }
nothas(){ grep -qF "$2" "$1" && bad "$1 içermemeliydi: $2" || ok "$1 beklendiği gibi: $2 yok"; }
tok(){ grep -o 'name="_token" value="[^"]*"' "$1" | head -1 | sed 's/.*value="//;s/"$//'; }
loc(){ grep -i '^location:' "$1" | tr -d '\r' | sed 's/^[Ll]ocation: *//'; }
code(){ curl -s -b "$J" -c "$J" -o "$2" -w '%{http_code}' "$1"; }

OLD_HASH=$($MY -e "SELECT password_hash FROM users WHERE id=1")
NEW_HASH=$($PHP -r "echo password_hash('TempAdmin123!', PASSWORD_DEFAULT);")
$MY -e "UPDATE users SET password_hash='$NEW_HASH' WHERE id=1" >/dev/null

VTYPE=$($MY -e "SELECT id FROM vehicle_types ORDER BY id LIMIT 1")
DTYPE=$($MY -e "SELECT id FROM document_types ORDER BY id LIMIT 1")
DTYPE_LABEL=$($MY -e "SELECT labels_json FROM document_types WHERE id=$DTYPE")
FUID=0; FORG=0; FVID=0; FDID=0; EXPDID=0; MSG_ID=0; SLIDE_ID=0
cleanup(){
  [ "$EXPDID" -gt 0 ] && $MY -e "DELETE FROM vehicle_documents WHERE id=$EXPDID" >/dev/null 2>&1
  [ "$FVID" -gt 0 ] && $MY -e "DELETE FROM vehicles WHERE id=$FVID" >/dev/null 2>&1
  [ "$FORG" -gt 0 ] && $MY -e "DELETE FROM organizations WHERE id=$FORG" >/dev/null 2>&1
  [ "$FUID" -gt 0 ] && $MY -e "DELETE FROM users WHERE id=$FUID" >/dev/null 2>&1
  $MY -e "DELETE FROM contact_messages WHERE email='$EMAIL'" >/dev/null 2>&1
  $MY -e "DELETE FROM slides WHERE title LIKE 'E2E Slayt%'" >/dev/null 2>&1
  $MY -e "DELETE FROM audit_logs WHERE (entity='vehicle' AND entity_id='$FVID') OR (entity='contact_message' AND entity_id='$MSG_ID') OR (entity='slide' AND entity_id='$SLIDE_ID')" >/dev/null 2>&1
  for media in $($MY -e "SELECT CONCAT(id,':',disk_path) FROM media WHERE original_name='$IMG_NAME'" 2>/dev/null); do
    mid=${media%%:*}; mpath=${media#*:}
    $MY -e "DELETE FROM media WHERE id=$mid" >/dev/null 2>&1
    [ -n "$mpath" ] && rm -f "$ROOT/storage/$mpath"
  done
  $MY -e "UPDATE users SET password_hash='$OLD_HASH' WHERE id=1" >/dev/null 2>&1
  echo "  (araç/evrak/kurum/mesaj/slayt fixture'ları silindi, yönetici şifresi geri yüklendi)"
}
trap cleanup EXIT

echo "== fixture =="
PWHASH=$($PHP -r "echo password_hash('Fixture123!', PASSWORD_DEFAULT);")
FUID=$($MY -e "INSERT INTO users (full_name,email,password_hash,role,status,email_verified_at,locale,created_at) VALUES ('E2E Modül Kullanıcı','$EMAIL','$PWHASH','customer','active',NOW(),'tr',NOW()); SELECT LAST_INSERT_ID();")
FORG=$($MY -e "INSERT INTO organizations (owner_user_id,name,type,credit_balance,created_at) VALUES ($FUID,'E2E Modül Kurum','individual',0,NOW()); SELECT LAST_INSERT_ID();")
FVID=$($MY -e "INSERT INTO vehicles (organization_id,vehicle_type_id,plate,brand,model,status,created_at) VALUES ($FORG,$VTYPE,'$PLATE','Test Marka','Test Model','active',NOW()); SELECT LAST_INSERT_ID();")
FDID=$($MY -e "INSERT INTO vehicle_documents (vehicle_id,document_type_id,issuer,policy_no,end_date,status,created_at) VALUES ($FVID,$DTYPE,'E2E Sigorta','$POLICY',DATE_ADD(CURDATE(), INTERVAL 20 DAY),'active',NOW()); SELECT LAST_INSERT_ID();")
EXPDID=$($MY -e "INSERT INTO vehicle_documents (vehicle_id,document_type_id,issuer,policy_no,end_date,status,created_at) VALUES ($FVID,$DTYPE,'E2E Sigorta','$EXPIRED_POLICY',DATE_SUB(CURDATE(), INTERVAL 10 DAY),'active',NOW()); SELECT LAST_INSERT_ID();")
$MY -e "INSERT INTO contact_messages (name,email,phone,subject,message,ip,is_read,created_at) VALUES ('E2E Gönderen','$EMAIL','5550000000','$MSG_SUBJECT','Bu bir e2e test mesajıdır. Yönetim paneli mesaj modülünü doğrular.','127.0.0.1',0,NOW())" >/dev/null
echo "  kullanıcı=$FUID kurum=$FORG araç=$FVID evrak=$FDID süresi-geçmiş=$EXPDID"

echo "== yönetici girişi =="
curl -s -c "$J" "$BASE/yonetim/giris" -o "$OUT/login.html"
T=$(tok "$OUT/login.html")
curl -s -b "$J" -c "$J" -o /dev/null --data-urlencode "_token=$T" \
  --data-urlencode "email=$ADMIN_EMAIL" --data-urlencode "password=$ADMIN_PASS" "$BASE/yonetim/giris"
curl -s -b "$J" -c "$J" "$BASE/yonetim/" -o "$OUT/dashboard.html"
T=$(tok "$OUT/dashboard.html")
check "GET /yonetim/" "$(code "$BASE/yonetim/" "$OUT/dashboard2.html")" "200"

echo "== menü ve panel bağlantıları =="
has "$OUT/dashboard2.html" '/yonetim/araclar"'
has "$OUT/dashboard2.html" '/yonetim/evraklar"'
has "$OUT/dashboard2.html" '/yonetim/mesajlar'
has "$OUT/dashboard2.html" '/yonetim/slider"'
has "$OUT/dashboard2.html" '/yonetim/denetim"'
nothas "$OUT/dashboard2.html" 'yakında'
has "$OUT/dashboard2.html" '/yonetim/mesajlar?durum=okunmamis'
check "dashboard yaklaşan listesi süresi geçmiş evrakı göstermez" "$(grep -oF "$PLATE" "$OUT/dashboard2.html" | wc -l | tr -d ' ')" "1"

echo "== araçlar =="
check "GET /yonetim/araclar" "$(code "$BASE/yonetim/araclar" "$OUT/vehicles.html")" "200"
has "$OUT/vehicles.html" "$PLATE"
has "$OUT/vehicles.html" 'E2E Modül Kullanıcı'
has "$OUT/vehicles.html" 'Toplam araç'
check "araç arama (plaka)" "$(code "$BASE/yonetim/araclar?q=$PLATE" "$OUT/vehicles_q.html")" "200"
has "$OUT/vehicles_q.html" "$PLATE"
nothas "$OUT/vehicles_q.html" 'Araç bulunamadı'
code "$BASE/yonetim/araclar?q=bulunmayan-plaka-xyz" "$OUT/vehicles_none.html" >/dev/null
has "$OUT/vehicles_none.html" 'Araç bulunamadı'
code "$BASE/yonetim/araclar?vade=30" "$OUT/vehicles_vade.html" >/dev/null
has "$OUT/vehicles_vade.html" "$PLATE"
check "GET araç detayı" "$(code "$BASE/yonetim/araclar/$FVID" "$OUT/vehicle.html")" "200"
has "$OUT/vehicle.html" "$PLATE"
has "$OUT/vehicle.html" "$POLICY"
has "$OUT/vehicle.html" 'Araç durumu'
has "$OUT/vehicle.html" 'Sahip / kurum'

curl -s -b "$J" -c "$J" -o /dev/null -w '' --data-urlencode "_token=$T" --data-urlencode "id=$FVID" --data-urlencode "status=sold" "$BASE/yonetim/araclar/durum"
check "araç durumu satıldı" "$($MY -e "SELECT status FROM vehicles WHERE id=$FVID")" "sold"
code "$BASE/yonetim/araclar/$FVID" "$OUT/vehicle_sold.html" >/dev/null
has "$OUT/vehicle_sold.html" 'Satıldı'
curl -s -b "$J" -c "$J" -o /dev/null --data-urlencode "_token=$T" --data-urlencode "id=$FVID" --data-urlencode "status=active" "$BASE/yonetim/araclar/durum"
check "araç durumu geri alındı" "$($MY -e "SELECT status FROM vehicles WHERE id=$FVID")" "active"

echo "== evraklar =="
check "GET /yonetim/evraklar" "$(code "$BASE/yonetim/evraklar" "$OUT/documents.html")" "200"
has "$OUT/documents.html" "$PLATE"
has "$OUT/documents.html" "$POLICY"
has "$OUT/documents.html" 'Toplam '
code "$BASE/yonetim/evraklar?tur=$DTYPE" "$OUT/documents_type.html" >/dev/null
has "$OUT/documents_type.html" "$POLICY"
code "$BASE/yonetim/evraklar?vade=30" "$OUT/documents_30.html" >/dev/null
has "$OUT/documents_30.html" "$POLICY"
code "$BASE/yonetim/evraklar?vade=gecmis" "$OUT/documents_expired.html" >/dev/null
nothas "$OUT/documents_expired.html" "$POLICY"
check "GET evrak detayı" "$(code "$BASE/yonetim/evraklar/$FDID" "$OUT/document.html")" "200"
has "$OUT/document.html" "$POLICY"
has "$OUT/document.html" 'Dosyalar'
has "$OUT/document.html" "$PLATE"

echo "== denetim kaydı =="
check "GET /yonetim/denetim" "$(code "$BASE/yonetim/denetim" "$OUT/audit.html")" "200"
has "$OUT/audit.html" 'Toplam kayıt'
has "$OUT/audit.html" 'vehicle.status'
has "$OUT/audit.html" 'Göster'
code "$BASE/yonetim/denetim?islem=vehicle.status" "$OUT/audit_filter.html" >/dev/null
has "$OUT/audit_filter.html" 'vehicle.status'
has "$OUT/audit_filter.html" "$PLATE"
code "$BASE/yonetim/denetim?gun=7" "$OUT/audit_days.html" >/dev/null
has "$OUT/audit_days.html" 'vehicle.status'

echo "== mesajlar =="
check "GET /yonetim/mesajlar" "$(code "$BASE/yonetim/mesajlar" "$OUT/messages.html")" "200"
has "$OUT/messages.html" "$MSG_SUBJECT"
has "$OUT/messages.html" "$EMAIL"
code "$BASE/yonetim/mesajlar?durum=okunmamis" "$OUT/messages_unread.html" >/dev/null
has "$OUT/messages_unread.html" "$MSG_SUBJECT"
MID=$($MY -e "SELECT id FROM contact_messages WHERE email='$EMAIL' LIMIT 1")
MSG_ID=$MID
check "GET mesaj detayı" "$(code "$BASE/yonetim/mesajlar/$MID" "$OUT/message.html")" "200"
has "$OUT/message.html" 'Bu bir e2e test mesajıdır.'
check "mesaj açılınca okundu" "$($MY -e "SELECT is_read FROM contact_messages WHERE id=$MID")" "1"
curl -s -b "$J" -c "$J" -o /dev/null --data-urlencode "_token=$T" --data-urlencode "id=$MID" --data-urlencode "read=0" "$BASE/yonetim/mesajlar/durum"
check "okunmadı işaretlendi" "$($MY -e "SELECT is_read FROM contact_messages WHERE id=$MID")" "0"
curl -s -b "$J" -c "$J" -o /dev/null --data-urlencode "_token=$T" --data-urlencode "q=$EMAIL" "$BASE/yonetim/mesajlar/tumu-okundu"
check "filtredeki tüm mesajlar okundu" "$($MY -e "SELECT is_read FROM contact_messages WHERE id=$MID")" "1"
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' --data-urlencode "id=$MID" "$BASE/yonetim/mesajlar/sil")
check "CSRF'siz mesaj silme reddedildi" "$CODE" "419"
curl -s -b "$J" -c "$J" -o /dev/null --data-urlencode "_token=$T" --data-urlencode "id=$MID" "$BASE/yonetim/mesajlar/sil"
check "mesaj silindi" "$($MY -e "SELECT COUNT(*) FROM contact_messages WHERE id=$MID")" "0"

echo "== slider =="
check "GET /yonetim/slider" "$(code "$BASE/yonetim/slider" "$OUT/slides.html")" "200"
check "GET /yonetim/slider/yeni" "$(code "$BASE/yonetim/slider/yeni" "$OUT/slide_new.html")" "200"
has "$OUT/slide_new.html" 'name="image"'
printf 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z0Z8AAAAASUVORK5CYII=' | base64 -d > /tmp/slide_e2e.png
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' \
  -F "_token=$T" -F "title=$SLIDE_TITLE" -F "subtitle=E2E alt başlık" \
  -F "description=E2E slayt açıklaması." -F "button_text=Test butonu" -F "button_url=/fiyatlar" \
  -F "button_target=_self" -F "text_position=center" -F "overlay_opacity=45" -F "locale=tr" \
  -F "sort_order=77" -F "is_active=1" -F "image=@/tmp/slide_e2e.png;filename=$IMG_NAME;type=image/png" \
  "$BASE/yonetim/slider/kaydet")
check "POST slider/kaydet (görselli)" "$CODE" "302"
SID=$($MY -e "SELECT id FROM slides WHERE title='$SLIDE_TITLE' LIMIT 1")
SLIDE_ID=$SID
check "slayt veritabanında" "$([ -n "$SID" ] && echo yes)" "yes"
check "slayt görseli kaydedildi" "$($MY -e "SELECT image_path IS NOT NULL FROM slides WHERE id=$SID")" "1"
SLIDE_MEDIA_PATH=$($MY -e "SELECT disk_path FROM media WHERE original_name='$IMG_NAME' ORDER BY id DESC LIMIT 1")
check "slayt dil/sıra" "$($MY -e "SELECT CONCAT(locale,'|',sort_order,'|',is_active) FROM slides WHERE id=$SID")" "tr|77|1"
curl -s "$BASE/" -o "$OUT/home_slide.html"
has "$OUT/home_slide.html" "$SLIDE_TITLE"
has "$OUT/home_slide.html" '/media/uploads/slide/'
check "GET slayt düzenle" "$(code "$BASE/yonetim/slider/$SID" "$OUT/slide_edit.html")" "200"
has "$OUT/slide_edit.html" "$SLIDE_TITLE"
has "$OUT/slide_edit.html" 'name="subtitle"'
has "$OUT/slide_edit.html" 'name="description"'
has "$OUT/slide_edit.html" 'name="button_text"'
has "$OUT/slide_edit.html" 'name="button_url"'
has "$OUT/slide_edit.html" 'name="image"'

# Ayarlar → Slider sekmesi içerikleri ve düzenleme yolunu göstermeli.
check "GET ayarlar/slider" "$(code "$BASE/yonetim/ayarlar/slider" "$OUT/settings_slider.html")" "200"
has "$OUT/settings_slider.html" 'Slayt içerikleri'
has "$OUT/settings_slider.html" 'Slider Yönetimi'
has "$OUT/settings_slider.html" "$SLIDE_TITLE"
has "$OUT/settings_slider.html" 'Test butonu'
has "$OUT/settings_slider.html" '/media/uploads/slide/'
has "$OUT/settings_slider.html" "/yonetim/slider/$SID"
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$T" --data-urlencode "id=$SID" --data-urlencode "title=$SLIDE_TITLE Güncel" \
  --data-urlencode "button_text=Test butonu" --data-urlencode "button_url=/fiyatlar" \
  --data-urlencode "button_target=_self" --data-urlencode "text_position=right" \
  --data-urlencode "overlay_opacity=55" --data-urlencode "locale=" --data-urlencode "sort_order=76" \
  --data-urlencode "is_active=1" "$BASE/yonetim/slider/kaydet")
check "POST slider/kaydet (güncelle)" "$CODE" "302"
check "slayt güncellendi" "$($MY -e "SELECT CONCAT(title,'|',text_position,'|',overlay_opacity) FROM slides WHERE id=$SID")" "$SLIDE_TITLE Güncel|right|55"
check "mevcut görsel korundu" "$($MY -e "SELECT image_path IS NOT NULL FROM slides WHERE id=$SID")" "1"
curl -s "$BASE/" -o "$OUT/home_slide2.html"
has "$OUT/home_slide2.html" "$SLIDE_TITLE Güncel"

# Geçersiz buton bağlantısı reddedilir
BEFORE=$($MY -e "SELECT COUNT(*) FROM slides")
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$T" --data-urlencode "title=E2E Slayt Geçersiz" \
  --data-urlencode "button_url=javascript:alert(1)" --data-urlencode "is_active=1" \
  "$BASE/yonetim/slider/kaydet")
check "geçersiz buton bağlantısı yönlendirir" "$CODE" "302"
check "geçersiz slayt oluşmadı" "$($MY -e "SELECT COUNT(*) FROM slides")" "$BEFORE"

curl -s -b "$J" -c "$J" -o /dev/null --data-urlencode "_token=$T" --data-urlencode "id=$SID" "$BASE/yonetim/slider/durum"
check "slayt yayından kaldırıldı" "$($MY -e "SELECT is_active FROM slides WHERE id=$SID")" "0"
curl -s "$BASE/" -o "$OUT/home_slide_off.html"
nothas "$OUT/home_slide_off.html" "$SLIDE_TITLE Güncel"
curl -s -b "$J" -c "$J" -o /dev/null --data-urlencode "_token=$T" --data-urlencode "id=$SID" "$BASE/yonetim/slider/durum"
check "slayt yeniden yayında" "$($MY -e "SELECT is_active FROM slides WHERE id=$SID")" "1"
curl -s -b "$J" -c "$J" -o /dev/null --data-urlencode "_token=$T" --data-urlencode "id=$SID" "$BASE/yonetim/slider/sil"
check "slayt silindi" "$($MY -e "SELECT COUNT(*) FROM slides WHERE id=$SID")" "0"
check "slayt silinince görsel kaydı temizlendi" "$($MY -e "SELECT COUNT(*) FROM media WHERE original_name='$IMG_NAME'")" "0"
check "slayt silinince görsel dosyası temizlendi" "$([ -n "$SLIDE_MEDIA_PATH" ] && [ ! -f "$ROOT/storage/$SLIDE_MEDIA_PATH" ] && echo yes)" "yes"

echo "== yetki =="
rm -f /tmp/modules_guest.txt
CODE=$(curl -s -c /tmp/modules_guest.txt -D "$OUT/guest_headers.txt" -o /dev/null -w '%{http_code}' "$BASE/yonetim/araclar")
check "oturumsuz araçlar → giriş" "$CODE" "302"
check "oturumsuz yönlendirme hedefi" "$(loc "$OUT/guest_headers.txt" | grep -o '/yonetim/giris')" "/yonetim/giris"

echo
echo "SONUÇ: $pass geçti, $fail kaldı"
exit $fail
