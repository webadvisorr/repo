#!/usr/bin/env bash
# Faz 3 — Hatırlatma motoru, premium mail şablonu, bildirim günlüğü
# Gerçek MariaDB + HTTP + yerel SMTP sink ile uçtan uca test.
set -u
BASE="http://127.0.0.1:3001"
ROOT="/workspace/chats/9e03ed61266af80267d81f50"
export HOME="${HOME:-/workspace/homes/9e03ed61266af80267d81f50}"
PHP="$HOME/bin/php/php"
MY="$HOME/mariadb/bin/mariadb --no-defaults --socket=$HOME/mysql-tmp/mysql.sock -uroot evrako_live -N -B"
REM="$PHP $ROOT/.freebuff/rem-call.php"
PART="$PHP $ROOT/.freebuff/mail-part.php"
J=/tmp/rem_j.txt; rm -f "$J"
OUT=/tmp/rem_out; mkdir -p "$OUT"
SINK_DIR=/tmp/smtp-sink; rm -rf "$SINK_DIR"; mkdir -p "$SINK_DIR"
SINK_LOG=/tmp/smtp-sink.log
RECIPIENT="admin@evrako.test"
DOC_ID=7
VEHICLE_ID=7
SETTINGS_KEYS="'smtp.host','smtp.port','smtp.encryption','smtp.auth','smtp.username','smtp.password','smtp.from_email','smtp.from_name','reminders.enabled','reminders.offsets','reminders.hour','reminders.daily_limit','reminders.batch','reminders.max_attempts','site.default_reminder_offsets'"

pass=0; fail=0
ok(){ echo "  ✓ $1"; pass=$((pass+1)); }
bad(){ echo "  ✗ $1"; fail=$((fail+1)); }
check(){ [ "$2" = "$3" ] && ok "$1 ($2)" || bad "$1 — beklenen '$3', gelen '$2'"; }
has(){ grep -qF "$2" "$1" && ok "$1 içeriyor: $2" || bad "$1 eksik: $2"; }
nothas(){ grep -qF "$2" "$1" && bad "$1 içermemeliydi: $2" || ok "$1 beklendiği gibi: $2 yok"; }
tok(){ grep -o 'name="_token" value="[^"]*"' "$1" | head -1 | sed 's/.*value="//;s/"$//'; }
sinkcount(){ ls "$SINK_DIR"/*.eml 2>/dev/null | wc -l | tr -d ' '; }

# ---- Geri yükleme planı ----
$MY -e "SELECT CONCAT('UPDATE settings SET \`value\`=',QUOTE(\`value\`),' WHERE \`key\`=',QUOTE(\`key\`),';') FROM settings WHERE \`key\` IN ($SETTINGS_KEYS)" > /tmp/rem_settings_backup.sql
MAXNOTIF=$($MY -e "SELECT COALESCE(MAX(id),0) FROM notifications")
MAXUNSUB=$($MY -e "SELECT COALESCE(MAX(id),0) FROM unsubscribes")
DOC_END=$($MY -e "SELECT end_date FROM vehicle_documents WHERE id=$DOC_ID")
DOC_OFFSETS=$($MY -e "SELECT COALESCE(reminder_offsets,'NULL') FROM vehicle_documents WHERE id=$DOC_ID")
OLD_HASH=$($MY -e "SELECT password_hash FROM users WHERE id=1")
# Yalnızca kendi fixture'ımız taranmalı: yakın tarihli diğer evraklar geri yükleme
# planıyla geçici olarak uzağa taşınır (aksi hâlde kuyruk/gönderim sayaçları kayar).
$MY -e "SELECT CONCAT('UPDATE vehicle_documents SET end_date=',QUOTE(end_date),' WHERE id=',id,';')
         FROM vehicle_documents
         WHERE id <> $DOC_ID AND end_date IS NOT NULL AND end_date < DATE_ADD(CURDATE(), INTERVAL 400 DAY)" > /tmp/rem_docs_backup.sql
SCAN_NEIGHBOURS=$($MY -e "SELECT COUNT(*) FROM vehicle_documents WHERE id <> $DOC_ID AND end_date IS NOT NULL AND end_date < DATE_ADD(CURDATE(), INTERVAL 400 DAY)")
SINK_PID=""

cleanup(){
    [ -n "$SINK_PID" ] && kill "$SINK_PID" 2>/dev/null
    # Test öncesi var olan kuyruk kayıtları (ör. panelden planlanmış gerçek kampanya)
    # kendi SMTP'lerine değil sink'e gitmesin diye park edilir; burada geri alınır.
    [ -s /tmp/rem_park.sql ] && $MY < /tmp/rem_park.sql >/dev/null 2>&1
    [ -f /tmp/rem_settings_backup.sql ] && $MY < /tmp/rem_settings_backup.sql
    $MY -e "DELETE FROM notifications WHERE id > $MAXNOTIF" >/dev/null 2>&1
    $MY -e "DELETE FROM unsubscribes WHERE id > $MAXUNSUB" >/dev/null 2>&1
    if [ "$DOC_OFFSETS" = "NULL" ]; then
        $MY -e "UPDATE vehicle_documents SET end_date='$DOC_END', reminder_offsets=NULL WHERE id=$DOC_ID" >/dev/null 2>&1
    else
        $MY -e "UPDATE vehicle_documents SET end_date='$DOC_END', reminder_offsets='$DOC_OFFSETS' WHERE id=$DOC_ID" >/dev/null 2>&1
    fi
    [ -f /tmp/rem_docs_backup.sql ] && $MY < /tmp/rem_docs_backup.sql >/dev/null 2>&1
    $MY -e "UPDATE users SET password_hash='$OLD_HASH' WHERE id=1" >/dev/null 2>&1
    echo "  (ayarlar, test bildirimleri, evrak tarihleri ve yönetici şifresi geri yüklendi)"
}
trap cleanup EXIT

# ---- Kurulum ----
echo "== hazırlık: ayarlar + evrak tarihi =="
$MY -e "UPDATE settings SET \`value\`='127.0.0.1' WHERE \`key\`='smtp.host'" >/dev/null
$MY -e "UPDATE settings SET \`value\`='2525' WHERE \`key\`='smtp.port'" >/dev/null
$MY -e "UPDATE settings SET \`value\`='none' WHERE \`key\`='smtp.encryption'" >/dev/null
$MY -e "UPDATE settings SET \`value\`='0' WHERE \`key\`='smtp.auth'" >/dev/null

# Yalıtım: kuyrukta bekleyen yabancı bildirimler geçici olarak ileri tarihe alınır
# (testin kuyruk işlemesi onları sink'e gönderip "gönderildi" işaretlemesin).
$MY -e "SELECT CONCAT('UPDATE notifications SET scheduled_for=',IFNULL(QUOTE(scheduled_for),'NULL'),' WHERE id=',id,';') FROM notifications WHERE status='queued' AND id <= $MAXNOTIF" > /tmp/rem_park.sql
PARKED=$($MY -e "UPDATE notifications SET scheduled_for = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE status='queued' AND id <= $MAXNOTIF; SELECT ROW_COUNT()")
if [ "$PARKED" -gt 0 ]; then
    echo "  (yalıtım: $PARKED yabancı kuyruk kaydı geçici olarak park edildi)"
else
    rm -f /tmp/rem_park.sql
fi
$MY -e "UPDATE settings SET \`value\`='noreply@evrako.test' WHERE \`key\`='smtp.from_email'" >/dev/null
$MY -e "UPDATE settings SET \`value\`='0' WHERE \`key\`='reminders.hour'" >/dev/null
$MY -e "UPDATE settings SET \`value\`='50' WHERE \`key\`='reminders.daily_limit'" >/dev/null
$MY -e "UPDATE settings SET \`value\`='10' WHERE \`key\`='reminders.batch'" >/dev/null
$MY -e "UPDATE settings SET \`value\`='3' WHERE \`key\`='reminders.max_attempts'" >/dev/null
$MY -e "UPDATE settings SET \`value\`='1' WHERE \`key\`='reminders.enabled'" >/dev/null
$MY -e "UPDATE vehicle_documents SET end_date=DATE_ADD(CURDATE(), INTERVAL 400 DAY) WHERE id <> $DOC_ID AND end_date IS NOT NULL AND end_date < DATE_ADD(CURDATE(), INTERVAL 400 DAY)" >/dev/null
$MY -e "DELETE FROM notifications WHERE document_id=$DOC_ID" >/dev/null
$MY -e "UPDATE vehicle_documents SET end_date=DATE_ADD(CURDATE(), INTERVAL 5 DAY), reminder_offsets='5' WHERE id=$DOC_ID" >/dev/null
echo "  (yalıtım: $SCAN_NEIGHBOURS komşu evrak geçici olarak uzağa taşındı)"
GUN=$($MY -e "SELECT DATEDIFF(end_date, CURDATE()) FROM vehicle_documents WHERE id=$DOC_ID")
check "test evrakı 5 gün sonra bitiyor" "$GUN" "5"

# SMTP sink'i başlat (uzun ömürlü)
setsid "$PHP" "$ROOT/.freebuff/smtp-sink.php" 2525 "$SINK_DIR" 40 600 > "$SINK_LOG" 2>&1 &
SINK_PID=$!
for i in $(seq 1 15); do
    grep -q "sink listening" "$SINK_LOG" 2>/dev/null && break
    sleep 0.3
done
grep -q "sink listening" "$SINK_LOG" && ok "yerel SMTP sink dinliyor (2525)" || bad "SMTP sink başlamadı"

echo "== web cron ucu: token koruması =="
TOKEN=$($REM cron-token)
[ -n "$TOKEN" ] && ok "cron token tanımlı" || bad "cron token boş"
# Token denetimi yan etki üretmesin diye motor geçici olarak kapatılır.
$MY -e "UPDATE settings SET \`value\`='0' WHERE \`key\`='reminders.enabled'" >/dev/null
CODE=$(curl -s -o "$OUT/cron_notoken.txt" -w '%{http_code}' "$BASE/cron-hatirlatma")
check "GET /cron-hatirlatma (tokensiz)" "$CODE" "403"
CODE=$(curl -s -o "$OUT/cron_bad.txt" -w '%{http_code}' "$BASE/cron-hatirlatma?token=yanlis")
check "GET /cron-hatirlatma (yanlış token)" "$CODE" "403"
CODE=$(curl -s -o "$OUT/cron_ok.txt" -w '%{http_code}' "$BASE/cron-hatirlatma?token=$TOKEN")
check "GET /cron-hatirlatma (doğru token)" "$CODE" "200"
has "$OUT/cron_ok.txt" 'evrako cron'
has "$OUT/cron_ok.txt" 'Taranan evrak'
has "$OUT/cron_ok.txt" 'Hatırlatma motoru kapalı'
$MY -e "UPDATE settings SET \`value\`='1' WHERE \`key\`='reminders.enabled'" >/dev/null
check "motorsuz çalıştırmada kuyruk boş kaldı" "$($MY -e "SELECT COUNT(*) FROM notifications WHERE document_id=$DOC_ID")" "0"

echo "== tarama: idempotent kuyruk =="
SCAN1=$($REM scan)
echo "  ($SCAN1)"
echo "$SCAN1" | grep -q '"created":1' && ok "aynı gün için hatırlatma kuyruğa alındı" || bad "kuyruk kaydı oluşmadı"
SCAN2=$($REM scan)
echo "  ($SCAN2)"
echo "$SCAN2" | grep -q '"created":0' && ok "ikinci tarama yeni kayıt üretmedi" || bad "tekrar gönderim engeli çalışmıyor"
echo "$SCAN2" | grep -q '"duplicates":1' && ok "tekrar (dedupe) sayıldı" || bad "dedupe sayacı hatalı"
ROW=$($MY -e "SELECT CONCAT(status,'|',channel,'|',template_key,'|',recipient,'|',attempts) FROM notifications WHERE document_id=$DOC_ID ORDER BY id DESC LIMIT 1")
check "kuyruk kaydı alanları" "$ROW" "queued|email|reminder|$RECIPIENT|0"
NOTIF_ID=$($MY -e "SELECT MAX(id) FROM notifications WHERE document_id=$DOC_ID")
DEDUPE=$($MY -e "SELECT dedupe_key FROM notifications WHERE id=$NOTIF_ID")
case "$DEDUPE" in
    email:reminder:1:$DOC_ID:5:*) ok "dedupe anahtarı kullanıcı+evrak+gün+tarih içeriyor ($DEDUPE)";;
    *) bad "dedupe anahtarı beklenen biçimde değil: $DEDUPE";;
esac

echo "== gönderim: SMTP + premium şablon =="
DISPATCH=$($REM dispatch)
echo "  ($DISPATCH)"
echo "$DISPATCH" | grep -q '"sent":1' && ok "bildirim gönderildi" || bad "gönderim başarısız: $DISPATCH"
check "sink'e ulaşan mesaj sayısı" "$(sinkcount)" "1"
EML="$SINK_DIR/1.eml"
$PART "$EML" headers > "$OUT/eml_headers.txt"
$PART "$EML" html > "$OUT/eml_html.html"
$PART "$EML" text > "$OUT/eml_text.txt"
has "$OUT/eml_headers.txt" 'List-Unsubscribe'
has "$OUT/eml_headers.txt" 'List-Unsubscribe-Post: List-Unsubscribe=One-Click'
has "$OUT/eml_headers.txt" 'Content-Type: multipart/alternative'
has "$OUT/eml_html.html" 'KC609'
has "$OUT/eml_html.html" 'Araç:'
has "$OUT/eml_html.html" 'Evrak:'
has "$OUT/eml_html.html" 'Bitiş tarihi:'
has "$OUT/eml_html.html" 'Kalan süre:'
has "$OUT/eml_html.html" '#F97316'                  # turuncu durum şeridi (5 gün)
has "$OUT/eml_html.html" '/panel/araclar/7'          # CTA bağlantısı
has "$OUT/eml_html.html" '/abonelikten-cikar?'       # KVKK: abonelikten çık
has "$OUT/eml_html.html" '/mail-piksel/'             # açılma takibi
has "$OUT/eml_html.html" 'Evrako'               # marka adı (şablondan)
has "$OUT/eml_text.txt" 'KC609'
ROW=$($MY -e "SELECT CONCAT(status,'|',attempts,'|',IF(sent_at IS NOT NULL,'sent_at_var','yok'),'|',IF(body_html IS NULL,'govde_yok','govde_var')) FROM notifications WHERE id=$NOTIF_ID")
check "gönderim sonrası kayıt" "$ROW" "sent|1|sent_at_var|govde_var"
SUBJ=$($MY -e "SELECT subject_rendered FROM notifications WHERE id=$NOTIF_ID")
echo "$SUBJ" | grep -q 'KC609' && ok "gönderilen konu başlığı işlendi ($SUBJ)" || bad "konu başlığı işlenmemiş: $SUBJ"

echo "== açılma takibi (piksel) =="
PIXEL_PATH=$(grep -o '/mail-piksel/[0-9]*/[a-f0-9]*\.gif' "$OUT/eml_html.html" | head -1)
[ -n "$PIXEL_PATH" ] && ok "piksel adresi bulundu ($PIXEL_PATH)" || bad "piksel adresi yok"
CODE=$(curl -s -o "$OUT/pixel.gif" -w '%{http_code}' "$BASE$PIXEL_PATH")
check "GET piksel" "$CODE" "200"
CT=$(curl -s -D - -o /dev/null "$BASE$PIXEL_PATH" | grep -i '^Content-Type' | tr -d '\r')
echo "$CT" | grep -qi 'image/gif' && ok "piksel içerik tipi ($CT)" || bad "piksel içerik tipi hatalı: $CT"
PLEN=$(wc -c < "$OUT/pixel.gif" | tr -d ' ')
check "piksel gövdesi 1x1 GIF (42 bayt)" "$PLEN" "42"
PCL=$(curl -s -D - -o /dev/null "$BASE$PIXEL_PATH" | grep -i '^Content-Length' | tr -d '\r')
check "piksel Content-Length başlığı" "$PCL" "Content-Length: $PLEN"
OPENED=$($MY -e "SELECT IF(opened_at IS NULL,'yok','var') FROM notifications WHERE id=$NOTIF_ID")
check "açılma zamanı kaydedildi" "$OPENED" "var"

echo "== web cron ucu: gerçek çalıştırma (tekrar göndermez) =="
CODE=$(curl -s -o "$OUT/cron_real.txt" -w '%{http_code}' "$BASE/cron-hatirlatma?token=$TOKEN")
check "GET /cron-hatirlatma (gerçek çalıştırma)" "$CODE" "200"
has "$OUT/cron_real.txt" 'Tekrar (atlandı): 1'
has "$OUT/cron_real.txt" 'Gönderilen: 0'
check "web cron yeni bildirim üretmedi" "$($MY -e "SELECT COUNT(*) FROM notifications WHERE document_id=$DOC_ID")" "1"

echo "== günlük gönderim limiti =="
SENT_TODAY=$($REM sent-today)
$MY -e "UPDATE settings SET \`value\`='$SENT_TODAY' WHERE \`key\`='reminders.daily_limit'" >/dev/null
LIMIT=$($REM dispatch)
echo "  ($LIMIT)"
echo "$LIMIT" | grep -q 'limit' && ok "limit dolduğunda gönderim duruyor" || bad "limit denetimi çalışmadı: $LIMIT"
echo "$LIMIT" | grep -q '"sent":0' && ok "limit doluyken hiç gönderim yok" || bad "limit aşımı: $LIMIT"
$MY -e "UPDATE settings SET \`value\`='50' WHERE \`key\`='reminders.daily_limit'" >/dev/null

echo "== hata + geri çekilme (retry/backoff) =="
# Kuyrukta bekleyen bir bildirim gibi davran (gönderim hatası senaryosu).
$MY -e "UPDATE notifications SET status='queued', attempts=0, sent_at=NULL, scheduled_for=NULL WHERE id=$NOTIF_ID" >/dev/null
$MY -e "UPDATE settings SET \`value\`='9' WHERE \`key\`='smtp.port'" >/dev/null
FAILRES=$($REM dispatch)
echo "  ($FAILRES)"
echo "$FAILRES" | grep -q '"skipped":1' && ok "başarısız gönderim yeniden denemeye alındı" || bad "yeniden deneme kuyruğu çalışmadı: $FAILRES"
ROW=$($MY -e "SELECT CONCAT(status,'|',attempts,'|',IF(scheduled_for IS NULL,'zaman_yok','zaman_var'),'|',IF(error IS NULL,'hata_yok','hata_var')) FROM notifications WHERE id=$NOTIF_ID")
check "hata sonrası kayıt" "$ROW" "queued|1|zaman_var|hata_var"
BACKOFF=$($MY -e "SELECT TIMESTAMPDIFF(MINUTE, NOW(), scheduled_for) FROM notifications WHERE id=$NOTIF_ID")
[ "$BACKOFF" -ge 10 ] && ok "geri çekilme gecikmesi uygulandı (${BACKOFF} dk)" || bad "geri çekilme gecikmesi beklenmedik: $BACKOFF dk"
$MY -e "UPDATE settings SET \`value\`='2525' WHERE \`key\`='smtp.port'" >/dev/null
# Yönetici "Yeniden dene" derse kuyruk zamanı sıfırlanır ve hemen gönderilir.
$REM requeue "$NOTIF_ID" > /dev/null

echo "== panelden kuyruğu işleme (yönetici) =="
NEW=$($PHP -r "echo password_hash('TempAdmin123!', PASSWORD_DEFAULT);")
$MY -e "UPDATE users SET password_hash='$NEW' WHERE id=1" >/dev/null
curl -s -c "$J" "$BASE/yonetim/giris" -o "$OUT/login.html"
T=$(tok "$OUT/login.html")
curl -s -b "$J" -c "$J" -o /dev/null --data-urlencode "_token=$T" \
  --data-urlencode "email=$RECIPIENT" --data-urlencode "password=TempAdmin123!" "$BASE/yonetim/giris"
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' "$BASE/yonetim/")
check "yönetici girişi" "$CODE" "200"
T=$(curl -s -b "$J" -c "$J" "$BASE/yonetim/bildirimler" | grep -o 'name="_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//')
CODE=$(curl -s -b "$J" -c "$J" -o "$OUT/runnow.html" -w '%{http_code}' \
  --data-urlencode "_token=$T" "$BASE/yonetim/bildirimler/kuyrugu-isle")
check "POST /yonetim/bildirimler/kuyrugu-isle" "$CODE" "302"
curl -s -b "$J" -c "$J" "$BASE/yonetim/bildirimler" -o "$OUT/notif_after.html"
has "$OUT/notif_after.html" 'Kuyruk işlendi'
check "yeniden deneme sonrası gönderim" "$($MY -e "SELECT status FROM notifications WHERE id=$NOTIF_ID")" "sent"
check "sink'e ulaşan mesaj sayısı (2)" "$(sinkcount)" "2"

echo "== abonelikten çıkma =="
UNSUB_URL=$($REM unsub-url "$RECIPIENT")
UNSUB_PATH=$(echo "$UNSUB_URL" | sed 's|https\?://[^/]*||')
Q=$(echo "$UNSUB_PATH" | sed 's|^/abonelikten-cikar?||')
E=$(echo "$Q" | sed -n 's/.*e=\([^&]*\).*/\1/p')
TK=$(echo "$Q" | sed -n 's/.*t=\([^&]*\).*/\1/p')
CODE=$(curl -s -b "$J" -c "$J" -o "$OUT/unsub.html" -w '%{http_code}' "$BASE$UNSUB_PATH")
check "GET /abonelikten-cikar" "$CODE" "200"
has "$OUT/unsub.html" "$RECIPIENT"
has "$OUT/unsub.html" 'E-postaları durdur'
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$(tok "$OUT/unsub.html")" --data-urlencode "e=$E" --data-urlencode "t=$TK" "$BASE/abonelikten-cikar")
check "POST /abonelikten-cikar (onay)" "$CODE" "302"
check "abonelik kaydı oluştu" "$($REM is-unsubscribed "$RECIPIENT")" "1"
SCOPE=$($MY -e "SELECT CONCAT(scope,'|',reason) FROM unsubscribes WHERE email='$RECIPIENT'")
check "abonelik kaydı kapsamı" "$SCOPE" "all|email_link"

# çıkmış alıcıya hatırlatma gitmemeli
$MY -e "UPDATE vehicle_documents SET end_date=DATE_ADD(CURDATE(), INTERVAL 9 DAY), reminder_offsets='9' WHERE id=$DOC_ID" >/dev/null
SCAN3=$($REM scan)
echo "  ($SCAN3)"
echo "$SCAN3" | grep -q '"created":0' && ok "listeden çıkan alıcıya hatırlatma yazılmadı" || bad "çıkmış alıcıya bildirim oluşturuldu"
check "offset 9 için kayıt yok" "$($MY -e "SELECT COUNT(*) FROM notifications WHERE document_id=$DOC_ID AND dedupe_key LIKE '%:9:%'")" "0"

echo "== yeniden açma + tek tık =="
curl -s -b "$J" -c "$J" -o "$OUT/unsub2.html" "$BASE$UNSUB_PATH"
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$(tok "$OUT/unsub2.html")" --data-urlencode "e=$E" --data-urlencode "t=$TK" "$BASE/abonelikten-cikar/yeniden-ac")
check "POST /abonelikten-cikar/yeniden-ac" "$CODE" "302"
check "abonelik kaydı silindi" "$($REM is-unsubscribed "$RECIPIENT")" "0"
OC_URL=$($REM oneclick-url "tek-tik@example.com")
OC_PATH=$(echo "$OC_URL" | sed 's|https\?://[^/]*||')
CODE=$(curl -s -o "$OUT/oneclick.txt" -w '%{http_code}' -X POST "$BASE$OC_PATH")
check "POST tek tık (CSRF'siz)" "$CODE" "200"
has "$OUT/oneclick.txt" 'listemizden çıkarıldı'
check "tek tık kaydı" "$($REM is-unsubscribed "tek-tik@example.com")" "1"
SCAN4=$($REM scan)
echo "  ($SCAN4)"
echo "$SCAN4" | grep -q '"created":1' && ok "yeniden açan alıcıya hatırlatma yeniden yazıldı" || bad "yeniden açma sonrası bildirim oluşmadı"
$MY -e "DELETE FROM notifications WHERE document_id=$DOC_ID AND dedupe_key LIKE '%:9:%'" >/dev/null

echo "== mail şablonları: liste / düzenle =="
curl -s -b "$J" -c "$J" "$BASE/yonetim/sablonlar" -o "$OUT/tpl_list.html"
has "$OUT/tpl_list.html" 'Evrak bitiş hatırlatması'
has "$OUT/tpl_list.html" 'E-posta doğrulama kodu'
has "$OUT/tpl_list.html" 'English'
has "$OUT/tpl_list.html" '{plaka}'
CODE=$(curl -s -b "$J" -c "$J" -o "$OUT/tpl_tr.html" -w '%{http_code}' "$BASE/yonetim/sablonlar/reminder/tr")
check "GET /yonetim/sablonlar/reminder/tr" "$CODE" "200"
has "$OUT/tpl_tr.html" 'HTML gövdesi'
CODE=$(curl -s -b "$J" -c "$J" -o "$OUT/tpl_en.html" -w '%{http_code}' "$BASE/yonetim/sablonlar/reminder/en")
check "GET /yonetim/sablonlar/reminder/en" "$CODE" "200"
has "$OUT/tpl_en.html" 'Document expiry reminder'
has "$OUT/tpl_en.html" 'HTML gövdesi'
CODE=$(curl -s -b "$J" -c "$J" -o "$OUT/tpl_verify.html" -w '%{http_code}' "$BASE/yonetim/sablonlar/verification/tr")
check "GET /yonetim/sablonlar/verification/tr" "$CODE" "200"
has "$OUT/tpl_verify.html" 'E-posta doğrulama'
has "$OUT/tpl_verify.html" '{kod}'
has "$OUT/tpl_verify.html" '{gecerlilik}'

echo "== mail şablonu: önizleme (örnek veri) =="
T=$(tok "$OUT/tpl_tr.html")
CODE=$(curl -s -b "$J" -c "$J" -o "$OUT/preview.html" -w '%{http_code}' \
  --data-urlencode "_token=$T" --data-urlencode "key=reminder" --data-urlencode "locale=tr" \
  "$BASE/yonetim/sablonlar/onizleme")
check "POST önizleme" "$CODE" "200"
has "$OUT/preview.html" 'KZ 123 A'
nothas "$OUT/preview.html" '{plaka}'
nothas "$OUT/preview.html" '{kalan_gun}'
has "$OUT/preview.html" '#F97316'

echo "== mail şablonu: kaydet + sıfırla =="
T=$(tok "$OUT/tpl_tr.html")
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$T" --data-urlencode "key=reminder" --data-urlencode "locale=tr" \
  --data-urlencode "name=Evrak bitiş hatırlatması" \
  --data-urlencode "subject=[TEST-MARKER] {plaka} · {evrak}" \
  --data-urlencode "body_html=<html><body><p>{ad} — {plaka} — {link}</p></body></html>" \
  --data-urlencode "is_active=1" "$BASE/yonetim/sablonlar/kaydet")
check "POST şablon kaydet" "$CODE" "302"
SUBJ=$($REM subject reminder tr)
check "şablon konusu güncellendi" "$SUBJ" "[TEST-MARKER] {plaka} · {evrak}"
LEN=$($MY -e "SELECT LENGTH(body_html) FROM mail_templates WHERE \`key\`='reminder' AND locale='tr'")
[ "$LEN" -lt 200 ] && ok "gövde kaydedildi (kısa test gövdesi)" || bad "gövde kaydedilmedi (len=$LEN)"
T=$(curl -s -b "$J" -c "$J" "$BASE/yonetim/sablonlar/reminder/tr" | grep -o 'name="_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//')
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$T" --data-urlencode "key=reminder" --data-urlencode "locale=tr" \
  "$BASE/yonetim/sablonlar/sifirla")
check "POST şablon sıfırla" "$CODE" "302"
SUBJ=$($REM subject reminder tr)
echo "$SUBJ" | grep -q 'TEST-MARKER' && bad "sıfırlama şablonu geri getirmedi" || ok "şablon varsayılana döndü ($SUBJ)"
LEN=$($MY -e "SELECT LENGTH(body_html) FROM mail_templates WHERE \`key\`='reminder' AND locale='tr'")
[ "$LEN" -gt 2000 ] && ok "premium gövde geri yüklendi ($LEN bayt)" || bad "gövde varsayılana dönmedi (len=$LEN)"

echo "== mail şablonu: test gönderimi =="
BEFORE_SINK=$(sinkcount)
T=$(tok "$OUT/tpl_tr.html")
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$T" --data-urlencode "key=reminder" --data-urlencode "locale=tr" \
  --data-urlencode "test_email=test@example.com" "$BASE/yonetim/sablonlar/test")
check "POST test gönderimi" "$CODE" "302"
sleep 1
AFTER_SINK=$(sinkcount)
[ "$AFTER_SINK" -gt "$BEFORE_SINK" ] && ok "test e-postası sink'e ulaştı ($BEFORE_SINK → $AFTER_SINK)" || bad "test e-postası gönderilmedi"

echo "== 6 dil şablon kontrolü =="
for L in tr en ru ar fa el; do
    R=$($REM render reminder "$L")
    echo "$R" | grep -q '<!doctype html>' && ok "$L şablonu render edildi" || bad "$L şablonu render edilemedi"
    echo "$R" | grep -q '{plaka}' && bad "$L şablonunda {plaka} işlenmemiş" || ok "$L şablonu değişkenleri işledi"
    echo "$R" | grep -q 'abonelikten-cikar' && ok "$L şablonunda abonelikten çık bağlantısı" || bad "$L şablonunda abonelik bağlantısı yok"
done
check "toplam şablon sayısı (2 şablon × 6 dil)" "$($MY -e "SELECT COUNT(*) FROM mail_templates")" "12"
AR_MARK=$($REM render reminder ar | grep -c 'dir="rtl"')
[ "$AR_MARK" -ge 1 ] && ok "Arapça şablon RTL olarak render edildi" || bad "Arapça şablon RTL değil"
for L in tr en ru ar fa el; do
    R=$($REM render verification "$L")
    echo "$R" | grep -q '482913' && ok "$L doğrulama şablonu kodu işledi" || bad "$L doğrulama şablonu kod üretmedi"
done
AR_VERIFY=$($REM render verification ar | grep -c 'dir="rtl"')
[ "$AR_VERIFY" -ge 1 ] && ok "Arapça doğrulama şablonu RTL" || bad "Arapça doğrulama şablonu RTL değil"

echo "== bildirim günlüğü =="
curl -s -b "$J" -c "$J" "$BASE/yonetim/bildirimler" -o "$OUT/log.html"
has "$OUT/log.html" 'Bugün gönderilen'
has "$OUT/log.html" 'Gönderildi'
has "$OUT/log.html" 'Açıldı'
has "$OUT/log.html" 'KC609'
CODE=$(curl -s -b "$J" -c "$J" -o "$OUT/log_failed.html" -w '%{http_code}' "$BASE/yonetim/bildirimler?durum=failed")
check "GET günlük (durum filtresi)" "$CODE" "200"
# Hatalı bir bildirim gibi davran: detay sayfası hata + yeniden dene göstermeli.
$MY -e "UPDATE notifications SET status='failed', error='Test: SMTP bağlantısı kurulamadı' WHERE id=$NOTIF_ID" >/dev/null
CODE=$(curl -s -b "$J" -c "$J" -o "$OUT/log_detail.html" -w '%{http_code}' "$BASE/yonetim/bildirimler/$NOTIF_ID")
check "GET bildirim detayı" "$CODE" "200"
has "$OUT/log_detail.html" 'iframe'
has "$OUT/log_detail.html" 'dedupe'
has "$OUT/log_detail.html" "$DEDUPE"
has "$OUT/log_detail.html" 'SMTP bağlantısı kurulamadı'
has "$OUT/log_detail.html" 'Yeniden dene'
curl -s -b "$J" -c "$J" -o "$OUT/log_detail2.html" "$BASE/yonetim/bildirimler/$NOTIF_ID"
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$(tok "$OUT/log_detail2.html")" --data-urlencode "id=$NOTIF_ID" "$BASE/yonetim/bildirimler/yeniden-dene")
check "POST yeniden dene" "$CODE" "302"
check "yeniden deneme kuyruğa aldı" "$($MY -e "SELECT status FROM notifications WHERE id=$NOTIF_ID")" "queued"
curl -s -b "$J" -c "$J" -o "$OUT/log_detail3.html" "$BASE/yonetim/bildirimler/$NOTIF_ID"
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$(tok "$OUT/log_detail3.html")" --data-urlencode "id=$NOTIF_ID" "$BASE/yonetim/bildirimler/iptal")
check "POST iptal" "$CODE" "302"
check "iptal durumu" "$($MY -e "SELECT status FROM notifications WHERE id=$NOTIF_ID")" "cancelled"
$MY -e "UPDATE notifications SET status='failed' WHERE id=$NOTIF_ID" >/dev/null
T=$(curl -s -b "$J" -c "$J" "$BASE/yonetim/bildirimler" | grep -o 'name="_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//')
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$T" "$BASE/yonetim/bildirimler/basarisizlari-dene")
check "POST başarısızları yeniden dene" "$CODE" "302"
check "başarısız kayıt yeniden kuyrukta" "$($MY -e "SELECT status FROM notifications WHERE id=$NOTIF_ID")" "queued"
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$T" "$BASE/yonetim/bildirimler/tara")
check "POST tarama (panelden)" "$CODE" "302"
curl -s -b "$J" -c "$J" "$BASE/yonetim/bildirimler" -o "$OUT/log_after_scan.html"
has "$OUT/log_after_scan.html" 'Tarama çalıştı'

echo "== ayarlar: hatırlatma sekmesi =="
CODE=$(curl -s -b "$J" -c "$J" -o "$OUT/set_rem.html" -w '%{http_code}' "$BASE/yonetim/ayarlar/reminders")
check "GET /yonetim/ayarlar/reminders" "$CODE" "200"
has "$OUT/set_rem.html" 'Hatırlatma motoru açık'
has "$OUT/set_rem.html" 'Varsayılan hatırlatma günleri'
T=$(tok "$OUT/set_rem.html")
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$T" --data-urlencode "tab=reminders" \
  --data-urlencode "reminders_enabled=1" --data-urlencode "reminders_offsets=7,3,1,0" \
  --data-urlencode "reminders_hour=0" --data-urlencode "reminders_daily_limit=77" \
  --data-urlencode "reminders_batch=10" --data-urlencode "reminders_max_attempts=3" \
  "$BASE/yonetim/ayarlar/kaydet")
check "POST hatırlatma ayarları" "$CODE" "302"
check "günlük limit kaydedildi" "$($MY -e "SELECT \`value\` FROM settings WHERE \`key\`='reminders.daily_limit'")" "77"
$MY -e "UPDATE settings SET \`value\`='50' WHERE \`key\`='reminders.daily_limit'" >/dev/null

echo "== sistem sayfası: cron + kuyruk =="
CODE=$(curl -s -b "$J" -c "$J" -o "$OUT/system.html" -w '%{http_code}' "$BASE/yonetim/sistem")
check "GET /yonetim/sistem" "$CODE" "200"
has "$OUT/system.html" '/cron-hatirlatma?token='
has "$OUT/system.html" 'Kuyrukta bekleyen'
has "$OUT/system.html" 'Mail şablonu:'

echo "== cron CLI =="
CLI=$($PHP "$ROOT/cron/reminders.php"); CLI_EXIT=$?
echo "  ($CLI)"
[ "$CLI_EXIT" = "0" ] && ok "cron CLI çıkış kodu 0" || bad "cron CLI hata kodu: $CLI_EXIT"
echo "$CLI" | grep -q 'Kuyrukta bekleyen' && ok "cron CLI özet üretti" || bad "cron CLI özeti eksik"
echo "$CLI" | grep -q 'süre:' && ok "cron CLI süre bildirdi" || bad "cron CLI süre bildirmedi"

echo
echo "SONUÇ: $pass geçti, $fail kaldı"
exit "$fail"
