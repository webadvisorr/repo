#!/usr/bin/env bash
# Faz 4 — Kampanya / reklam maili: hedef kitle, kuyruk, gönderim, açılma takibi, izinler
# Gerçek MariaDB + HTTP + yerel SMTP sink ile uçtan uca test.
set -u
BASE="http://127.0.0.1:3001"
ROOT="/workspace/chats/9e03ed61266af80267d81f50"
export HOME="${HOME:-/workspace/homes/9e03ed61266af80267d81f50}"
PHP="$HOME/bin/php/php"
MY="$HOME/mariadb/bin/mariadb --no-defaults --socket=$HOME/mysql-tmp/mysql.sock -uroot evrako_live -N -B"
CC="$PHP $ROOT/.freebuff/campaign-call.php"
J=/tmp/camp_admin_j.txt; rm -f "$J"
JC=/tmp/camp_customer_j.txt; rm -f "$JC"
OUT=/tmp/camp_out; mkdir -p "$OUT"
SINK_DIR=/tmp/camp-sink; rm -rf "$SINK_DIR"; mkdir -p "$SINK_DIR"
SINK_LOG=/tmp/camp-sink.log
STAMP=$(date +%s)
SETTINGS_KEYS="'smtp.host','smtp.port','smtp.encryption','smtp.auth','smtp.from_email','smtp.from_name','reminders.enabled','reminders.hour','reminders.daily_limit','reminders.batch','reminders.max_attempts'"
ADMIN_EMAIL="admin@evrako.test"
A_EMAIL="campaign-a-$STAMP@evrako.test"
B_EMAIL="campaign-b-$STAMP@evrako.test"
C_EMAIL="campaign-c-$STAMP@evrako.test"
CUSTOMER_PASS='CampaignPass123!'

pass=0; fail=0
ok(){ echo "  ✓ $1"; pass=$((pass+1)); }
bad(){ echo "  ✗ $1"; fail=$((fail+1)); }
check(){ [ "$2" = "$3" ] && ok "$1 ($2)" || bad "$1 — beklenen '$3', gelen '$2'"; }
has(){ grep -qF "$2" "$1" && ok "$1 içeriyor: $2" || bad "$1 eksik: $2"; }
nothas(){ grep -qF "$2" "$1" && bad "$1 içermemeliydi: $2" || ok "$1 beklendiği gibi: $2 yok"; }
tok(){ grep -o 'name="_token" value="[^"]*"' "$1" | head -1 | sed 's/.*value="//;s/"$//'; }
sinkcount(){ ls "$SINK_DIR"/*.eml 2>/dev/null | wc -l | tr -d ' '; }

# ---- Geri yükleme planı ----
$MY -e "SELECT CONCAT('UPDATE settings SET \`value\`=',QUOTE(\`value\`),' WHERE \`key\`=',QUOTE(\`key\`),';') FROM settings WHERE \`key\` IN ($SETTINGS_KEYS)" > /tmp/camp_settings_backup.sql
MAXNOTIF=$($MY -e "SELECT COALESCE(MAX(id),0) FROM notifications")
MAXCAMP=$($MY -e "SELECT COALESCE(MAX(id),0) FROM campaigns")
MAXUNSUB=$($MY -e "SELECT COALESCE(MAX(id),0) FROM unsubscribes")
OLD_HASH=$($MY -e "SELECT password_hash FROM users WHERE id=1")
$MY -e "SELECT CONCAT('UPDATE vehicle_documents SET end_date=',QUOTE(end_date),' WHERE id=',id,';') FROM vehicle_documents WHERE end_date IS NOT NULL AND end_date < DATE_ADD(CURDATE(), INTERVAL 400 DAY)" > /tmp/camp_docs_backup.sql
SCAN_NEIGHBOURS=$($MY -e "SELECT COUNT(*) FROM vehicle_documents WHERE end_date IS NOT NULL AND end_date < DATE_ADD(CURDATE(), INTERVAL 400 DAY)")
SINK_PID=""
ORG_ID=0

cleanup(){
    [ "${SINK_PID:-}" != "" ] && kill "$SINK_PID" 2>/dev/null
    # Test öncesi var olan kuyruk kayıtları (ör. panelden planlanmış gerçek kampanya)
    # kendi SMTP'lerine değil sink'e gitmesin diye park edilir; burada geri alınır.
    [ -s /tmp/camp_park.sql ] && $MY < /tmp/camp_park.sql >/dev/null 2>&1
    [ "${ORG_ID:-0}" -gt 0 ] || return 0
    $MY -e "DELETE FROM notifications WHERE id > $MAXNOTIF" >/dev/null 2>&1
    $MY -e "DELETE FROM campaigns WHERE id > $MAXCAMP" >/dev/null 2>&1
    $MY -e "DELETE FROM unsubscribes WHERE id > $MAXUNSUB" >/dev/null 2>&1
    $MY -e "DELETE FROM organization_members WHERE user_id IN (SELECT id FROM users WHERE email IN ('$A_EMAIL','$B_EMAIL','$C_EMAIL'))" >/dev/null 2>&1
    $MY -e "DELETE FROM organizations WHERE id = $ORG_ID" >/dev/null 2>&1
    $MY -e "DELETE FROM users WHERE email IN ('$A_EMAIL','$B_EMAIL','$C_EMAIL')" >/dev/null 2>&1
    [ -f /tmp/camp_docs_backup.sql ] && $MY < /tmp/camp_docs_backup.sql >/dev/null 2>&1
    [ -f /tmp/camp_settings_backup.sql ] && $MY < /tmp/camp_settings_backup.sql >/dev/null 2>&1
    $MY -e "UPDATE users SET password_hash='$OLD_HASH' WHERE id=1" >/dev/null 2>&1
    echo "  (ayarlar, kampanya kayıtları, geçici kullanıcılar ve evrak tarihleri geri yüklendi)"
}

# ---- Kurulum: SMTP sink + ayarlar + yönetici girişi ----
echo "== hazırlık: SMTP sink, ayarlar, yönetici girişi =="
setsid "$PHP" "$ROOT/.freebuff/smtp-sink.php" 2525 "$SINK_DIR" 40 600 > "$SINK_LOG" 2>&1 &
SINK_PID=$!
for i in $(seq 1 15); do
    grep -q "sink listening" "$SINK_LOG" 2>/dev/null && break
    sleep 0.3
done
grep -q "sink listening" "$SINK_LOG" && ok "yerel SMTP sink dinliyor (2525)" || bad "SMTP sink başlamadı"

$MY -e "UPDATE settings SET \`value\`='127.0.0.1' WHERE \`key\`='smtp.host'" >/dev/null
$MY -e "UPDATE settings SET \`value\`='2525' WHERE \`key\`='smtp.port'" >/dev/null

# Yalıtım: testten önce kuyrukta bekleyen yabancı bildirimler (gerçek kampanya/hatırlatma)
# geçici olarak ileri tarihe alınır — aksi hâlde testin kuyruk işlemesi onları sink'e gönderip
# "gönderildi" işaretlerdi. Planları cleanup'ta aynen geri yazılır.
$MY -e "SELECT CONCAT('UPDATE notifications SET scheduled_for=',IFNULL(QUOTE(scheduled_for),'NULL'),' WHERE id=',id,';') FROM notifications WHERE status='queued' AND id <= $MAXNOTIF" > /tmp/camp_park.sql
PARKED=$($MY -e "UPDATE notifications SET scheduled_for = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE status='queued' AND id <= $MAXNOTIF; SELECT ROW_COUNT()")
if [ "$PARKED" -gt 0 ]; then
    echo "  (yalıtım: $PARKED yabancı kuyruk kaydı geçici olarak park edildi)"
else
    rm -f /tmp/camp_park.sql
fi
$MY -e "UPDATE settings SET \`value\`='none' WHERE \`key\`='smtp.encryption'" >/dev/null
$MY -e "UPDATE settings SET \`value\`='0' WHERE \`key\`='smtp.auth'" >/dev/null
$MY -e "UPDATE settings SET \`value\`='noreply@evrako.test' WHERE \`key\`='smtp.from_email'" >/dev/null
$MY -e "UPDATE settings SET \`value\`='1' WHERE \`key\`='reminders.enabled'" >/dev/null
$MY -e "UPDATE settings SET \`value\`='250' WHERE \`key\`='reminders.daily_limit'" >/dev/null
$MY -e "UPDATE settings SET \`value\`='50' WHERE \`key\`='reminders.batch'" >/dev/null
$MY -e "UPDATE settings SET \`value\`='3' WHERE \`key\`='reminders.max_attempts'" >/dev/null
# Komşu evrakları uzağa taşı: cron yalnızca kampanya bildirimlerini göndersin.
$MY -e "UPDATE vehicle_documents SET end_date=DATE_ADD(CURDATE(), INTERVAL 400 DAY) WHERE end_date IS NOT NULL AND end_date < DATE_ADD(CURDATE(), INTERVAL 400 DAY)" >/dev/null
echo "  (yalıtım: $SCAN_NEIGHBOURS komşu evrak geçici olarak uzağa taşındı)"

NEW=$($PHP -r "echo password_hash('TempAdmin123!', PASSWORD_DEFAULT);")
$MY -e "UPDATE users SET password_hash='$NEW' WHERE id=1" >/dev/null
curl -s -c "$J" "$BASE/yonetim/giris" -o "$OUT/login.html"
T=$(tok "$OUT/login.html")
curl -s -b "$J" -c "$J" -o /dev/null --data-urlencode "_token=$T" \
  --data-urlencode "email=$ADMIN_EMAIL" --data-urlencode "password=TempAdmin123!" "$BASE/yonetim/giris"
CODE=$(curl -s -b "$J" -c "$J" -o "$OUT/campaigns.html" -w '%{http_code}' "$BASE/yonetim/kampanya")
check "GET /yonetim/kampanya (yönetici)" "$CODE" "200"
has "$OUT/campaigns.html" 'Gönderim kuyruğu'
has "$OUT/campaigns.html" 'Yeni kampanya'
CODE=$(curl -s -b "$J" -c "$J" -o "$OUT/campaign_new.html" -w '%{http_code}' "$BASE/yonetim/kampanya/yeni")
check "GET /yonetim/kampanya/yeni" "$CODE" "200"
has "$OUT/campaign_new.html" 'Hedef kitle'
has "$OUT/campaign_new.html" '{abonelikten_cik}'

# ---- Fixture: geçici kurum + 3 kullanıcı (izinli / izinsiz / abonelikten çıkmış) ----
echo "== hedef kitle: izin ve abonelik kuralları =="
HASH=$($PHP -r "echo password_hash('CampaignPass123!', PASSWORD_DEFAULT);")
$MY -e "INSERT INTO users (full_name,email,password_hash,role,status,email_verified_at,locale,notify_email,marketing_opt_in,created_at) VALUES ('Kampanya A','$A_EMAIL','$HASH','customer','active',NOW(),'ru',1,1,NOW())" >/dev/null
$MY -e "INSERT INTO users (full_name,email,password_hash,role,status,email_verified_at,locale,notify_email,marketing_opt_in,created_at) VALUES ('Kampanya B','$B_EMAIL','$HASH','customer','active',NOW(),'tr',1,0,NOW())" >/dev/null
$MY -e "INSERT INTO users (full_name,email,password_hash,role,status,email_verified_at,locale,notify_email,marketing_opt_in,created_at) VALUES ('Kampanya C','$C_EMAIL','$HASH','customer','active',NOW(),'tr',1,1,NOW())" >/dev/null
A_ID=$($MY -e "SELECT id FROM users WHERE email='$A_EMAIL'")
B_ID=$($MY -e "SELECT id FROM users WHERE email='$B_EMAIL'")
C_ID=$($MY -e "SELECT id FROM users WHERE email='$C_EMAIL'")
$MY -e "INSERT INTO organizations (owner_user_id,name,type,country,created_at) VALUES ($A_ID,'Kampanya Test Kurumu $STAMP','business','CY',NOW())" >/dev/null
ORG_ID=$($MY -e "SELECT id FROM organizations WHERE name='Kampanya Test Kurumu $STAMP'")
for U in "$A_ID:owner" "$B_ID:viewer" "$C_ID:viewer"; do
    UID_=${U%%:*}; ROLE_=${U##*:}
    $MY -e "INSERT INTO organization_members (organization_id,user_id,role,status,created_at) VALUES ($ORG_ID,$UID_,'$ROLE_','active',NOW())" >/dev/null
done
$MY -e "INSERT INTO unsubscribes (email,reason,scope,created_at) VALUES ('$C_EMAIL','e2e test','marketing',NOW())" >/dev/null

trap cleanup EXIT
check "izinli kullanıcı hedef kitlede" "$($CC audience $ORG_ID optin)" "1"
check "tüm kullanıcılar (izin filtresiz)" "$($CC audience $ORG_ID all)" "2"

# ---- Kampanya oluştur ----
echo "== kampanya oluşturma =="
CAMP_NAME="E2E Kampanya $STAMP"
CAMP_SUBJECT="{ad} · {marka_adi} yaz kampanyası"
CAMP_BODY='<p>Merhaba {ad},</p><p>{marka_adi} sizi {site_url} adresinde bekliyor. Plan: {plan}</p>'
T=$(tok "$OUT/campaign_new.html")
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$T" \
  --data-urlencode "campaign_id=0" \
  --data-urlencode "name=$CAMP_NAME" \
  --data-urlencode "subject=$CAMP_SUBJECT" \
  --data-urlencode "body_html=$CAMP_BODY" \
  --data-urlencode "audience_org_id=$ORG_ID" \
  --data-urlencode "audience_optin=1" \
  "$BASE/yonetim/kampanya/kaydet")
check "POST /yonetim/kampanya/kaydet" "$CODE" "302"
CAMP1=$($CC last-id)
check "kampanya taslak olarak kaydedildi" "$($CC campaign $CAMP1 | $PHP -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["status"];')" "draft"
check "hedef kitle JSON'a yazıldı" "$($CC campaign $CAMP1 | $PHP -r '$d=json_decode(stream_get_contents(STDIN),true); $a=json_decode($d["audience"],true); echo $a["org_id"];')" "$ORG_ID"

CODE=$(curl -s -b "$J" -c "$J" -o "$OUT/campaign_edit.html" -w '%{http_code}' "$BASE/yonetim/kampanya/$CAMP1/duzenle")
check "GET kampanya düzenleme sayfası" "$CODE" "200"
has "$OUT/campaign_edit.html" 'Kullanılabilir değişkenler'
has "$OUT/campaign_edit.html" 'Kuyruğa al ve gönder'

# ---- Test gönderimi ----
echo "== test gönderimi =="
BEFORE=$(sinkcount)
T=$(tok "$OUT/campaign_edit.html")
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$T" --data-urlencode "campaign_id=$CAMP1" \
  --data-urlencode "test_email=$ADMIN_EMAIL" "$BASE/yonetim/kampanya/test")
check "POST /yonetim/kampanya/test" "$CODE" "302"
sleep 1
check "sink'e 1 test e-postası düştü" "$(( $(sinkcount) - BEFORE ))" "1"
LAST_EML=$(ls -t "$SINK_DIR"/*.eml 2>/dev/null | head -1)
SUBJ=$($PHP -r 'if (!$argv[1]) { exit; } $raw=file_get_contents($argv[1]); preg_match("/^Subject:\s*(.*)$/mi", $raw, $m); echo mb_decode_mimeheader(trim($m[1] ?? ""));' "$LAST_EML")
case "$SUBJ" in
    '[TEST]'*) ok "test konusu [TEST] ile işaretli" ;;
    *) bad "test konusu işaretlenmedi — '$SUBJ'" ;;
esac
check "test gönderimi kuyruğa yazılmadı" "$($CC notifications-count $CAMP1)" "0"

# ---- Gönderim ----
echo "== kuyruğa al ve gönder =="
BEFORE=$(sinkcount)
T=$(tok "$OUT/campaign_edit.html")
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$T" --data-urlencode "campaign_id=$CAMP1" "$BASE/yonetim/kampanya/gonder")
check "POST /yonetim/kampanya/gonder" "$CODE" "302"
sleep 1
check "sink'e 1 kampanya e-postası düştü" "$(( $(sinkcount) - BEFORE ))" "1"
check "alıcı sayısı izinli kullanıcı ile sınırlı" "$($CC stats $CAMP1 | $PHP -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["total"]."|".$d["sent"];')" "1|1"

NOTIF=$($CC notification $CAMP1 $A_ID)
NOTIF_ID=$(echo "$NOTIF" | $PHP -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["id"] ?? 0;')
check "kampanya bildirimi gönderildi" "$(echo "$NOTIF" | $PHP -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["status"] ?? "-";')" "sent"
echo "$NOTIF" | grep -q "$A_EMAIL" && ok "bildirim alıcısı izinli kullanıcı" || bad "bildirim alıcısı yanlış"
echo "$NOTIF" | grep -q "Kampanya A" && ok "değişkenler konuya işlendi ({ad})" || bad "konu değişkeni işlenmedi"
echo "$NOTIF" | grep -q "yaz kampanyası" && ok "konu metni korundu" || bad "konu metni bozuk"

FLAGS=$($CC body-flags "$NOTIF_ID")
check "gövdede açılma pikseli var" "$(echo "$FLAGS" | $PHP -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["pixel"] ? "1" : "0";')" "1"
check "gövdede abonelikten çıkma bağlantısı var" "$(echo "$FLAGS" | $PHP -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["unsub"] ? "1" : "0";')" "1"
check "gövde alıcının dilinde (ru) yazıldı" "$(echo "$FLAGS" | $PHP -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["ru"] ? "1" : "0";')" "1"
check "İngilizce/Türkçe alt bilgi sızmadı" "$(echo "$FLAGS" | $PHP -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["tr"] ? "1" : "0";')" "0"

RECIP=$($CC recipients $CAMP1)
echo "$RECIP" | grep -q '"status":"sent"' && ok "alıcı kaydı gönderildi olarak işaretlendi" || bad "alıcı kaydı güncellenmedi"
echo "$RECIP" | grep -q "$B_EMAIL" && bad "izinsiz kullanıcı alıcı listesinde" || ok "izinsiz kullanıcı alıcı listesine girmedi"
echo "$RECIP" | grep -q "$C_EMAIL" && bad "abonelikten çıkmış kullanıcı alıcı listesinde" || ok "abonelikten çıkmış kullanıcı alıcı listesine girmedi"
check "kampanya gönderildi olarak kapandı" "$($CC campaign $CAMP1 | $PHP -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["status"]."|".$d["sent_count"];')" "sent|1"

# ---- Tekrar gönderim = engellenir (dedupe) ----
echo "== tekrar gönderim engeli =="
BEFORE=$(sinkcount)
curl -s -b "$J" -c "$J" -o /dev/null \
  --data-urlencode "_token=$T" --data-urlencode "campaign_id=$CAMP1" "$BASE/yonetim/kampanya/gonder"
sleep 1
check "ikinci gönderim yeni bildirim üretmedi" "$($CC notifications-count $CAMP1)" "1"
check "ikinci gönderim yeni e-posta göndermedi" "$(( $(sinkcount) - BEFORE ))" "0"

# ---- Açılma takibi (piksel) ----
echo "== açılma takibi =="
PIXEL=$($CC pixel-url "$NOTIF_ID")
PIXEL_PATH=$(echo "$PIXEL" | sed 's#^https\?://[^/]*##')
[ -n "$PIXEL_PATH" ] || PIXEL_PATH="$PIXEL"
CODE=$(curl -s -o "$OUT/pixel.gif" -w '%{http_code}' "$BASE$PIXEL_PATH")
check "GET açılma pikseli" "$CODE" "200"
check "kampanya açılma sayacı" "$($CC campaign $CAMP1 | $PHP -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["open_count"];')" "1"
echo "$($CC recipients $CAMP1)" | grep -q '"opened_at":"20' && ok "alıcı açılma zamanı kaydedildi" || bad "alıcı açılma zamanı yok"

# ---- Planlı gönderim (cron) ----
echo "== planlı kampanya: cron ile gönderim =="
T=$(tok "$OUT/campaign_new.html")
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$T" \
  --data-urlencode "campaign_id=0" \
  --data-urlencode "name=Planlı $STAMP" \
  --data-urlencode "subject={marka_adi} planlı duyuru" \
  --data-urlencode "body_html=<p>{ad} için planlı duyuru.</p>" \
  --data-urlencode "audience_org_id=$ORG_ID" \
  --data-urlencode "audience_optin=1" \
  --data-urlencode "scheduled_at=$(date -d '5 minutes ago' '+%Y-%m-%dT%H:%M')" \
  "$BASE/yonetim/kampanya/kaydet")
check "POST planlı kampanya" "$CODE" "302"
CAMP2=$($CC last-id)
check "planlı kampanya durumu" "$($CC campaign $CAMP2 | $PHP -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["status"];')" "scheduled"

BEFORE=$(sinkcount)
CLI=$($PHP "$ROOT/cron/reminders.php" 2>&1)
CLI_CODE=$?
echo "$CLI" > "$OUT/cron_cli.txt"
check "cron CLI çıkış kodu" "$CLI_CODE" "0"
has "$OUT/cron_cli.txt" 'Kampanya kuyruğa eklenen: 1'
check "planlı kampanya gönderildi" "$($CC campaign $CAMP2 | $PHP -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["status"]."|".$d["sent_count"];')" "sent|1"
sleep 1
check "cron 1 kampanya e-postası gönderdi" "$(( $(sinkcount) - BEFORE ))" "1"

# ---- İptal: kuyruktaki bildirimler durdurulur ----
echo "== iptal =="
T=$(tok "$OUT/campaign_new.html")
curl -s -b "$J" -c "$J" -o /dev/null \
  --data-urlencode "_token=$T" \
  --data-urlencode "campaign_id=0" \
  --data-urlencode "name=İptal $STAMP" \
  --data-urlencode "subject={marka_adi} iptal testi" \
  --data-urlencode "body_html=<p>{ad} iptal testi.</p>" \
  --data-urlencode "audience_org_id=$ORG_ID" \
  --data-urlencode "audience_optin=1" \
  "$BASE/yonetim/kampanya/kaydet"
CAMP3=$($CC last-id)
T=$(tok "$OUT/campaign_edit.html")
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$T" --data-urlencode "campaign_id=$CAMP3" "$BASE/yonetim/kampanya/kuyruk")
check "POST /yonetim/kampanya/kuyruk" "$CODE" "302"
check "kuyrukta bekleyen bildirim oluştu" "$($MY -e "SELECT COUNT(*) FROM notifications WHERE dedupe_key LIKE 'campaign:$CAMP3:%' AND status='queued'")" "1"
BEFORE=$(sinkcount)
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$T" --data-urlencode "campaign_id=$CAMP3" "$BASE/yonetim/kampanya/iptal")
check "POST /yonetim/kampanya/iptal" "$CODE" "302"
check "kampanya iptal edildi" "$($CC campaign $CAMP3 | $PHP -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["status"];')" "cancelled"
check "kuyruktaki bildirim durduruldu" "$($MY -e "SELECT COUNT(*) FROM notifications WHERE dedupe_key LIKE 'campaign:$CAMP3:%' AND status='cancelled'")" "1"
$PHP "$ROOT/cron/reminders.php" > /dev/null 2>&1
sleep 1
check "iptal sonrası e-posta gönderilmedi" "$(( $(sinkcount) - BEFORE ))" "0"

# ---- Yetki: müşteri kampanya paneline giremez ----
echo "== yetki denetimi =="
curl -s -c "$JC" "$BASE/giris" -o "$OUT/customer_login.html"
T=$(tok "$OUT/customer_login.html")
curl -s -b "$JC" -c "$JC" -o /dev/null --data-urlencode "_token=$T" \
  --data-urlencode "email=$A_EMAIL" --data-urlencode "password=$CUSTOMER_PASS" "$BASE/giris"
CODE=$(curl -s -b "$JC" -c "$JC" -o "$OUT/customer_campaigns.html" -w '%{http_code}' "$BASE/yonetim/kampanya")
check "GET /yonetim/kampanya (müşteri)" "$CODE" "403"

echo
echo "SONUÇ: $pass geçti, $fail kaldı"
exit $fail
