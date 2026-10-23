#!/usr/bin/env bash
# ------------------------------------------------------------
#  Canlı ziyaretçi, e-posta günlüğü adlandırması, tarih filtreleri,
#  müşteri mesaj takibi, destek talepleri ve yönetim araması.
#  Gerçek HTTP + MariaDB. Sunucu 3001'de ayakta olmalı.
# ------------------------------------------------------------
set -uo pipefail

export HOME="${HOME:-/workspace/homes/9e03ed61266af80267d81f50}"
BASE="http://127.0.0.1:3001"
PHP="$HOME/bin/php/php"
MY="$HOME/mariadb/bin/mariadb --socket=$HOME/mysql-tmp/mysql.sock -uroot --default-character-set=utf8mb4 evrako_live"
MYSQL() { $MY -N -B -e "$1"; }
OUT="/tmp/e2e_support"
rm -rf "$OUT"; mkdir -p "$OUT"

PASS=0; FAIL=0
ok()   { PASS=$((PASS+1)); printf '  \033[32m✓\033[0m %s\n' "$1"; }
no()   { FAIL=$((FAIL+1)); printf '  \033[31m✗\033[0m %s\n' "$1"; }
check(){ if [ "$2" = "$3" ]; then ok "$1"; else no "$1 (beklenen: $3, gelen: $2)"; fi; }
has()  { if grep -q "$2" "$3" 2>/dev/null; then ok "$1"; else no "$1 (metin bulunamadı: $2)"; fi; }
hasnt(){ if grep -q "$2" "$3" 2>/dev/null; then no "$1 (beklenmeyen metin: $2)"; else ok "$1"; fi; }
tok()  { grep -o 'name="_token" value="[^"]*"' "$1" | head -1 | sed 's/.*value="//;s/"$//'; }

SUF=$RANDOM
EMAIL="destek-test-$SUF@example.com"
EMAIL2="destek-diger-$SUF@example.com"
PASSWORD='DestekTest123!'
ORG_NAME="Destek Test $SUF"
PLATE="DT$SUF"
MSG_SUBJECT="Mesaj testi $SUF"
TICKET_SUBJECT="Talep testi $SUF"

cleanup() {
    MYSQL "DELETE FROM contact_messages WHERE email IN ('$EMAIL','$EMAIL2')" >/dev/null 2>&1
    MYSQL "DELETE FROM support_tickets WHERE subject LIKE '%$SUF%'" >/dev/null 2>&1
    MYSQL "DELETE FROM organizations WHERE name = '$ORG_NAME'" >/dev/null 2>&1
    MYSQL "DELETE FROM users WHERE email IN ('$EMAIL','$EMAIL2')" >/dev/null 2>&1
    [ -n "${OLD_ADMIN_HASH:-}" ] && MYSQL "UPDATE users SET password_hash='$OLD_ADMIN_HASH' WHERE id=1" >/dev/null 2>&1
}
trap cleanup EXIT

echo "== Hazırlık =="
MYSQL "SELECT 1" >/dev/null || { echo "MariaDB kapalı"; exit 1; }
curl -s -o /dev/null -w '%{http_code}' "$BASE/" | grep -q 200 || { echo "Sunucu yanıt vermiyor"; exit 1; }

HASH=$($PHP -r "echo password_hash('$PASSWORD', PASSWORD_DEFAULT);")
USER_ID=$(MYSQL "INSERT INTO users (full_name,email,phone,password_hash,role,status,locale,email_verified_at,created_at) VALUES ('Destek Test','$EMAIL','0533$SUF','$HASH','customer','active','tr',NOW(),NOW()); SELECT LAST_INSERT_ID();")
ORG_ID=$(MYSQL "INSERT INTO organizations (owner_user_id,name,type,created_at) VALUES ($USER_ID,'$ORG_NAME','individual',NOW()); SELECT LAST_INSERT_ID();")
MYSQL "INSERT INTO organization_members (organization_id,user_id,role,status,created_at) VALUES ($ORG_ID,$USER_ID,'owner','active',NOW())" >/dev/null
VTYPE=$(MYSQL "SELECT id FROM vehicle_types ORDER BY id LIMIT 1")
MYSQL "INSERT INTO vehicles (organization_id,vehicle_type_id,plate,brand,model,status,created_at) VALUES ($ORG_ID,$VTYPE,'$PLATE','Test','Model','active',NOW())" >/dev/null
HASH2=$($PHP -r "echo password_hash('$PASSWORD', PASSWORD_DEFAULT);")
USER2_ID=$(MYSQL "INSERT INTO users (full_name,email,password_hash,role,status,locale,email_verified_at,created_at) VALUES ('Diğer Kullanıcı','$EMAIL2','$HASH2','customer','active','tr',NOW(),NOW()); SELECT LAST_INSERT_ID();")
echo "  kullanıcı #$USER_ID / #$USER2_ID · kurum #$ORG_ID"

login() { # $1=email $2=jar
    curl -s -c "$2" "$BASE/giris" -o "$OUT/login.html"
    local t; t=$(tok "$OUT/login.html")
    curl -s -c "$2" -b "$2" -X POST --data-urlencode "_token=$t" \
        --data-urlencode "email=$1" --data-urlencode "password=$PASSWORD" "$BASE/giris" -o /dev/null
}

echo "== Canlı ziyaretçi sayacı =="
VJ="$OUT/visitor.jar"
curl -s -c "$VJ" -b "$VJ" "$BASE/" -o /dev/null
curl -s -c "$VJ" -b "$VJ" "$BASE/fiyatlar" -o /dev/null
ONLINE=$($PHP -r 'require "app/bootstrap.php"; echo App\Services\PresenceService::onlineCount();')
[ "${ONLINE:-0}" -ge 1 ] && ok "son 5 dakikada en az 1 çevrimiçi ziyaretçi ($ONLINE)" || no "çevrimiçi ziyaretçi sayılmadı ($ONLINE)"
check "ziyaretçi oturumu kaydedildi" "1" "$(MYSQL "SELECT COUNT(*) > 0 FROM visitor_sessions WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)")"
VISITOR_PATH=$(MYSQL "SELECT path FROM visitor_sessions ORDER BY id DESC LIMIT 1")
check "son görüntülenen sayfa kaydedildi" "1" "$(echo "$VISITOR_PATH" | grep -q '^/' && echo 1 || echo 0)"

echo "== Yönetim girişi =="
OLD_ADMIN_HASH=$(MYSQL "SELECT password_hash FROM users WHERE id=1")
NEW_ADMIN_HASH=$($PHP -r "echo password_hash('TempAdmin9!', PASSWORD_DEFAULT);")
MYSQL "UPDATE users SET password_hash='$NEW_ADMIN_HASH' WHERE id=1" >/dev/null
AJ="$OUT/admin.jar"
curl -s -c "$AJ" "$BASE/yonetim/giris" -o "$OUT/admin-login.html"
AT=$(tok "$OUT/admin-login.html")
curl -s -c "$AJ" -b "$AJ" -X POST --data-urlencode "_token=$AT" \
    --data-urlencode "email=admin@evrako.test" --data-urlencode "password=TempAdmin9!" "$BASE/yonetim/giris" -o /dev/null

curl -s -b "$AJ" "$BASE/yonetim/" -o "$OUT/dashboard.html"
check "panel 200" "$(curl -s -o /dev/null -w '%{http_code}' -b "$AJ" "$BASE/yonetim/")" "200"
has "canlı ziyaretçi paneli var" "Canlı ziyaretçiler" "$OUT/dashboard.html"
has "şu an sitede kutusu var" "Şu an sitede" "$OUT/dashboard.html"
has "destek talepleri özeti var" "Destek talepleri" "$OUT/dashboard.html"
hasnt "başarısız bildirim kutusu kaldırıldı" "Başarısız bildirim" "$OUT/dashboard.html"
has "renkli istatistik kutuları eklendi" "stat--tone-7" "$OUT/dashboard.html"
has "menüde arama kutusu var" "admin-search" "$OUT/dashboard.html"
has "canlı sayaç otomatik tazeleniyor" "setInterval(refresh" "$OUT/dashboard.html"
LIVE=$(curl -s -b "$AJ" -H 'Accept: application/json' "$BASE/yonetim/canli")
echo "  canlı sayaç: $(echo "$LIVE" | head -c 160)"
echo "$LIVE" | grep -q '"ok":true' && ok "canlı sayaç JSON yanıtı" || no "canlı sayaç JSON yanıtı"
echo "$LIVE" | grep -q '"online"' && ok "canlı sayaç çevrimiçi sayısı döndü" || no "canlı sayaç çevrimiçi sayısı döndü"
check "canlı sayaç anonim erişimi engellendi" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/yonetim/canli")" "302"

echo "== Yeniden adlandırma =="
has "menüde E-posta Günlüğü" "E-posta Günlüğü" "$OUT/dashboard.html"
has "menüde Bildirim" ">Bildirim<" "$OUT/dashboard.html"
curl -s -b "$AJ" "$BASE/yonetim/bildirimler" -o "$OUT/email-log.html"
has "e-posta günlüğü başlığı" "E-posta Günlüğü" "$OUT/email-log.html"
has "e-posta günlüğü tarih filtresi" 'name="baslangic"' "$OUT/email-log.html"
has "e-posta günlüğü filtre formu" "/yonetim/bildirimler" "$OUT/email-log.html"
curl -s -b "$AJ" "$BASE/yonetim/duyurular" -o "$OUT/bildirim.html"
has "bildirim sayfası başlığı" "Yeni bildirim" "$OUT/bildirim.html"

echo "== Tarih aralığı filtreleri =="
curl -s -b "$AJ" "$BASE/yonetim/denetim?baslangic=2035-01-01&bitis=2035-12-31" -o "$OUT/audit-empty.html"
has "denetim: gelecek tarihte kayıt yok" "Toplam 0 kayıt" "$OUT/audit-empty.html"
curl -s -b "$AJ" "$BASE/yonetim/denetim?baslangic=2020-01-01&bitis=2035-12-31" -o "$OUT/audit-all.html"
hasnt "denetim: geniş aralıkta kayıt var" "Toplam 0 kayıt" "$OUT/audit-all.html"
curl -s -b "$AJ" "$BASE/yonetim/mesajlar" -o "$OUT/msg-admin.html"
has "mesajlar: tarih filtresi" 'name="baslangic"' "$OUT/msg-admin.html"
curl -s -b "$AJ" "$BASE/yonetim/uyeler" -o "$OUT/members.html"
has "üyeler: tarih filtresi" 'name="baslangic"' "$OUT/members.html"
curl -s -b "$AJ" "$BASE/yonetim/destek" -o "$OUT/support-admin.html"
has "destek: tarih filtresi" 'name="baslangic"' "$OUT/support-admin.html"

echo "== Yönetim araması =="
curl -s -b "$AJ" "$BASE/yonetim/arama?q=$(printf '%s' "$EMAIL" | sed 's/@/%40/')" -o "$OUT/search.html"
has "arama e-posta ile müşteriyi buldu" "Destek Test" "$OUT/search.html"
has "arama sonucu üye kaydına bağlanıyor" "/yonetim/uyeler/$USER_ID" "$OUT/search.html"
curl -s -b "$AJ" "$BASE/yonetim/arama?q=0533$SUF" -o "$OUT/search-phone.html"
has "arama telefonla müşteriyi buldu" "Destek Test" "$OUT/search-phone.html"
curl -s -b "$AJ" "$BASE/yonetim/arama?q=Destek%20Test" -o "$OUT/search-name.html"
has "arama adla müşteriyi buldu" "$EMAIL" "$OUT/search-name.html"
curl -s -b "$AJ" "$BASE/yonetim/arama?q=$PLATE" -o "$OUT/search-plate.html"
has "arama plakayı buldu" "$PLATE" "$OUT/search-plate.html"

echo "== İletişim mesajı: müşteri takibi =="
UJ="$OUT/user.jar"
login "$EMAIL" "$UJ"
curl -s -b "$UJ" "$BASE/iletisim" -o "$OUT/contact.html"
CT=$(tok "$OUT/contact.html")
curl -s -b "$UJ" -X POST --data-urlencode "_token=$CT" --data-urlencode "name=Destek Test" \
    --data-urlencode "email=$EMAIL" --data-urlencode "phone=0533$SUF" \
    --data-urlencode "subject=$MSG_SUBJECT" --data-urlencode "message=Bu bir e2e mesaj testidir, panelden takip edilmeli." \
    "$BASE/iletisim" -o /dev/null
MSG_ID=$(MYSQL "SELECT id FROM contact_messages WHERE subject='$MSG_SUBJECT' LIMIT 1")
check "iletişim mesajı oluştu" "1" "$([ -n "$MSG_ID" ] && echo 1 || echo 0)"
check "mesaj giriş yapan kullanıcıya bağlandı" "$(MYSQL "SELECT user_id FROM contact_messages WHERE id=$MSG_ID")" "$USER_ID"

curl -s -b "$UJ" "$BASE/panel/mesajlar" -o "$OUT/my-messages.html"
has "panel mesaj listesinde görünüyor" "$MSG_SUBJECT" "$OUT/my-messages.html"
curl -s -b "$UJ" "$BASE/panel/mesajlar/$MSG_ID" -o "$OUT/my-message.html"
has "mesaj detayı açılıyor" "panelden takip edilmeli" "$OUT/my-message.html"
MT=$(tok "$OUT/my-message.html")
curl -s -b "$UJ" -X POST --data-urlencode "_token=$MT" --data-urlencode "id=$MSG_ID" \
    --data-urlencode "body=Müşteri yanıtı: plaka $PLATE için ek bilgi." "$BASE/panel/mesajlar/yanit" -o /dev/null
check "müşteri yanıtı kaydedildi" "$(MYSQL "SELECT COUNT(*) FROM contact_replies WHERE message_id=$MSG_ID AND sender='customer'")" "1"
check "müşteri yanıtı mesajı yönetim için okunmadı yaptı" "$(MYSQL "SELECT is_read FROM contact_messages WHERE id=$MSG_ID")" "0"

curl -s -b "$AJ" "$BASE/yonetim/mesajlar/$MSG_ID" -o "$OUT/msg-admin-detail.html"
has "yönetim müşteri yanıtını görüyor" "Müşteri yanıtı: plaka $PLATE" "$OUT/msg-admin-detail.html"
has "yönetim yanıt formu var" "Panelden yanıtla" "$OUT/msg-admin-detail.html"
MSG_AT=$(tok "$OUT/msg-admin-detail.html")
curl -s -b "$AJ" -X POST --data-urlencode "_token=$MSG_AT" --data-urlencode "id=$MSG_ID" \
    --data-urlencode "body=Yönetim yanıtı: kaydınızı inceledik." "$BASE/yonetim/mesajlar/yanit" -o /dev/null
check "yönetim yanıtı kaydedildi" "$(MYSQL "SELECT COUNT(*) FROM contact_replies WHERE message_id=$MSG_ID AND sender='admin'")" "1"
curl -s -b "$UJ" "$BASE/panel/mesajlar/$MSG_ID" -o "$OUT/my-message2.html"
has "müşteri yönetim yanıtını görüyor" "Yönetim yanıtı: kaydınızı inceledik." "$OUT/my-message2.html"
check "yönetim yanıtı mesajı okundu yaptı" "$(MYSQL "SELECT is_read FROM contact_messages WHERE id=$MSG_ID")" "1"

echo "== Destek talebi =="
curl -s -b "$UJ" "$BASE/panel/destek/yeni" -o "$OUT/support-form.html"
ST=$(tok "$OUT/support-form.html")
curl -s -b "$UJ" -X POST --data-urlencode "_token=$ST" --data-urlencode "subject=$TICKET_SUBJECT" \
    --data-urlencode "category=documents" --data-urlencode "priority=high" \
    --data-urlencode "body=Sigorta evrakı hatırlatması gelmiyor, kontrol eder misiniz?" "$BASE/panel/destek" -o /dev/null
TICKET_ID=$(MYSQL "SELECT id FROM support_tickets WHERE subject='$TICKET_SUBJECT' LIMIT 1")
check "destek talebi oluştu" "1" "$([ -n "$TICKET_ID" ] && echo 1 || echo 0)"
check "talep kategorisi/önceliği kaydedildi" "$(MYSQL "SELECT CONCAT(category,'|',priority,'|',status) FROM support_tickets WHERE id=$TICKET_ID")" "documents|high|open"
check "ilk mesaj yazıldı" "$(MYSQL "SELECT COUNT(*) FROM support_ticket_messages WHERE ticket_id=$TICKET_ID")" "1"
TICKET_NO=$(MYSQL "SELECT ticket_no FROM support_tickets WHERE id=$TICKET_ID")

curl -s -b "$UJ" "$BASE/panel/destek" -o "$OUT/support-list.html"
has "müşteri talebi listede" "$TICKET_SUBJECT" "$OUT/support-list.html"
has "talep numarası görünüyor" "$TICKET_NO" "$OUT/support-list.html"
curl -s -b "$UJ" "$BASE/panel/destek/$TICKET_ID" -o "$OUT/support-show.html"
has "talep detayı açılıyor" "Sigorta evrakı hatırlatması" "$OUT/support-show.html"
curl -s -b "$AJ" "$BASE/yonetim/destek" -o "$OUT/support-admin-list.html"
has "yönetim talebi görüyor" "$TICKET_SUBJECT" "$OUT/support-admin-list.html"
has "yönetimde yeni mesaj rozeti" "1 yeni" "$OUT/support-admin-list.html"
curl -s -b "$AJ" "$BASE/yonetim/destek/$TICKET_ID" -o "$OUT/support-admin-show.html"
has "yönetim talep detayı" "Sigorta evrakı hatırlatması" "$OUT/support-admin-show.html"
SAT=$(tok "$OUT/support-admin-show.html")
curl -s -b "$AJ" -X POST --data-urlencode "_token=$SAT" --data-urlencode "id=$TICKET_ID" \
    --data-urlencode "body=Yönetim: kaydınızı kontrol ettik, hatırlatma yeniden planlandı." "$BASE/yonetim/destek/yanit" -o /dev/null
check "yönetim yanıtı eklendi" "$(MYSQL "SELECT COUNT(*) FROM support_ticket_messages WHERE ticket_id=$TICKET_ID AND sender='admin'")" "1"
check "talep yanıtlandı durumuna geçti" "$(MYSQL "SELECT status FROM support_tickets WHERE id=$TICKET_ID")" "answered"
check "yönetim mesajları okundu işaretlendi" "$(MYSQL "SELECT COUNT(*) FROM support_ticket_messages WHERE ticket_id=$TICKET_ID AND sender='customer' AND read_by_admin_at IS NULL")" "0"

curl -s -b "$UJ" "$BASE/panel/destek/$TICKET_ID" -o "$OUT/support-customer2.html"
has "müşteri yönetim yanıtını görüyor" "hatırlatma yeniden planlandı" "$OUT/support-customer2.html"
check "müşteri tarafında okundu" "$(MYSQL "SELECT COUNT(*) FROM support_ticket_messages WHERE ticket_id=$TICKET_ID AND sender='admin' AND read_by_customer_at IS NULL")" "0"

UT=$(tok "$OUT/support-customer2.html")
curl -s -b "$UJ" -X POST --data-urlencode "_token=$UT" --data-urlencode "id=$TICKET_ID" \
    --data-urlencode "body=Teşekkürler, sorun çözüldü." "$BASE/panel/destek/yanit" -o /dev/null
check "müşteri yanıtı sonrası durum açık" "$(MYSQL "SELECT status FROM support_tickets WHERE id=$TICKET_ID")" "open"

curl -s -b "$AJ" -X POST --data-urlencode "_token=$SAT" --data-urlencode "id=$TICKET_ID" \
    --data-urlencode "status=closed" "$BASE/yonetim/destek/durum" -o /dev/null
check "yönetim talebi kapattı" "$(MYSQL "SELECT status FROM support_tickets WHERE id=$TICKET_ID")" "closed"
curl -s -b "$UJ" -X POST --data-urlencode "_token=$UT" --data-urlencode "id=$TICKET_ID" \
    --data-urlencode "body=Kapalı talebe yanıt denemesi" "$BASE/panel/destek/yanit" -o /dev/null
check "kapalı talebe yanıt eklenmedi" "$(MYSQL "SELECT COUNT(*) FROM support_ticket_messages WHERE ticket_id=$TICKET_ID")" "3"

echo "== Yetki ve CSRF =="
login "$EMAIL2" "$OUT/user2.jar"
check "başka kullanıcı talebi göremiyor" "$(curl -s -o /dev/null -w '%{http_code}' -b "$OUT/user2.jar" "$BASE/panel/destek/$TICKET_ID")" "302"
check "başka kullanıcı mesajı göremiyor" "$(curl -s -o /dev/null -w '%{http_code}' -b "$OUT/user2.jar" "$BASE/panel/mesajlar/$MSG_ID")" "302"
check "destek yanıtı CSRF'siz reddedildi" "$(curl -s -o /dev/null -w '%{http_code}' -b "$UJ" -X POST --data-urlencode "id=$TICKET_ID" --data-urlencode "body=x" "$BASE/panel/destek/yanit")" "419"
check "mesaj yanıtı CSRF'siz reddedildi" "$(curl -s -o /dev/null -w '%{http_code}' -b "$UJ" -X POST --data-urlencode "id=$MSG_ID" --data-urlencode "body=x" "$BASE/panel/mesajlar/yanit")" "419"
check "anonim destek listesi girişe yönlendirildi" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/panel/destek")" "302"
check "anonim yönetim araması girişe yönlendirildi" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/yonetim/arama?q=x")" "302"

echo
echo "== Sonuç: $PASS geçti, $FAIL kaldı =="
exit $([ "$FAIL" -eq 0 ] && echo 0 || echo 1)
