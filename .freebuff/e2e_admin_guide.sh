#!/usr/bin/env bash
# Yönetim paneli kullanım rehberi (AI asistanı) — gerçek HTTP + MariaDB.
# Kapsam koruması, yerel bilgi tabanı, isteğe bağlı AI köprüsü, ayar sekmesi,
# CSRF/yetki ve hız sınırı bu betikte doğrulanır.
set -u
BASE="http://127.0.0.1:3001"
ROOT="/workspace/chats/9e03ed61266af80267d81f50"
export HOME="${HOME:-/workspace/homes/9e03ed61266af80267d81f50}"
PHP="$HOME/bin/php/php"
MY="$HOME/mariadb/bin/mariadb --no-defaults --socket=$HOME/mysql-tmp/mysql.sock -uroot evrako_live -N -B"
OUT=/tmp/guide_out; mkdir -p "$OUT"
J=/tmp/guide_admin.txt; rm -f "$J"
BACKUP=/tmp/guide_assistant_settings.json
FAKE_LOG=/tmp/guide_fake_llm.json
FAKE_PORT=3102
ADMIN_EMAIL="admin@evrako.test"
ADMIN_PASS='TempAdmin123!'
pass=0; fail=0
ok(){ echo "  ✓ $1"; pass=$((pass+1)); }
bad(){ echo "  ✗ $1"; fail=$((fail+1)); }
check(){ [ "$2" = "$3" ] && ok "$1 ($2)" || bad "$1 — beklenen '$3', gelen '$2'"; }
loc(){ grep -i '^location:' "$1" | tr -d '\r' | sed 's/^[Ll]ocation: *//'; }
has(){ grep -qF "$2" "$1" && ok "$1 içeriyor: $2" || bad "$1 eksik: $2"; }
nothas(){ grep -qF "$2" "$1" && bad "$1 içermemeliydi: $2" || ok "$1 beklendiği gibi: $2 yok"; }
tok(){ grep -o 'name="_token" value="[^"]*"' "$1" | head -1 | sed 's/.*value="//;s/"$//'; }
jget(){ $PHP -r '$d=json_decode(stream_get_contents(STDIN),true); $v=$d; foreach(explode(".",$argv[1]) as $p){ $v=is_array($v)&&array_key_exists($p,$v)?$v[$p]:null; } echo is_scalar($v)||$v===null?(string)$v:json_encode($v,JSON_UNESCAPED_UNICODE);' "$1"; }
jlen(){ $PHP -r '$d=json_decode(stream_get_contents(STDIN),true); $v=$d; foreach(explode(".",$argv[1]) as $p){ $v=is_array($v)&&array_key_exists($p,$v)?$v[$p]:null; } echo is_array($v)?count($v):0;' "$1"; }
ask(){ # $1 = soru, $2 = çıktı dosyası, $3 = token
  curl -s -b "$J" -c "$J" -H 'X-Requested-With: XMLHttpRequest' -H 'Accept: application/json' \
    --data-urlencode "_token=$3" --data-urlencode "question=$1" "$BASE/yonetim/rehber/sor" -o "$2"
}

OLD_HASH=$($MY -e "SELECT password_hash FROM users WHERE id=1")
NEW_HASH=$($PHP -r "echo password_hash('TempAdmin123!', PASSWORD_DEFAULT);")
$MY -e "UPDATE users SET password_hash='$NEW_HASH' WHERE id=1" >/dev/null
$PHP "$ROOT/.freebuff/guide-settings.php" export "$BACKUP" || true
# Testler yerel modu doğrulasın: Gemini anahtarı geçici olarak kapatılır.
$PHP "$ROOT/.freebuff/guide-settings.php" clear-gemini "$BACKUP" || true
FAKE_PID=""
cleanup(){
  [ -n "$FAKE_PID" ] && kill "$FAKE_PID" >/dev/null 2>&1
  $PHP "$ROOT/.freebuff/guide-settings.php" restore "$BACKUP" >/dev/null 2>&1
  $MY -e "UPDATE users SET password_hash='$OLD_HASH' WHERE id=1" >/dev/null 2>&1
  echo "  (rehber ayarları ve yönetici şifresi geri yüklendi)"
}
trap cleanup EXIT

login(){
  rm -f "$J"
  curl -s -c "$J" "$BASE/yonetim/giris" -o "$OUT/login.html"
  local t; t=$(tok "$OUT/login.html")
  curl -s -b "$J" -c "$J" -o /dev/null --data-urlencode "_token=$t" \
    --data-urlencode "email=$ADMIN_EMAIL" --data-urlencode "password=$ADMIN_PASS" "$BASE/yonetim/giris"
  curl -s -b "$J" -c "$J" "$BASE/yonetim/" -o "$OUT/dashboard.html"
  tok "$OUT/dashboard.html"
}

echo "== widget panelde görünüyor =="
T=$(login)
check "GET /yonetim/" "$(curl -s -b "$J" -c "$J" -o "$OUT/dashboard2.html" -w '%{http_code}' "$BASE/yonetim/")" "200"
has "$OUT/dashboard2.html" 'data-guide'
has "$OUT/dashboard2.html" 'admin-guide__toggle'
has "$OUT/dashboard2.html" 'data-guide-form'
has "$OUT/dashboard2.html" 'Panel Rehberi'
has "$OUT/dashboard2.html" 'Yerel rehber'
has "$OUT/dashboard2.html" 'name="question"'
check "widget açma düğmesi sayısı (üst bar + yüzen)" "$(grep 'data-guide-toggle' "$OUT/dashboard2.html" | grep -vc 'querySelectorAll')" "2"
check "hızlı soru örneği sayısı" "$(grep -o 'data-guide-question=' "$OUT/dashboard2.html" | wc -l | tr -d ' ')" "7"

echo "== yerel rehber: gerçek panel ekranlarına yönlendiriyor =="
ask "kredi nasıl yüklerim" "$OUT/a_credit.json" "$T"
check "kredi → konu" "$(jget topic < "$OUT/a_credit.json")" "members"
check "kredi → mod" "$(jget mode < "$OUT/a_credit.json")" "guide"
check "kredi → adım sayısı" "$(jlen steps < "$OUT/a_credit.json")" "5"
check "kredi → doğru sayfa" "$(jget link.url < "$OUT/a_credit.json" | grep -o '/yonetim/uyeler$')" "/yonetim/uyeler"
has "$OUT/a_credit.json" 'Kredi yönetimi'

ask "plan fiyatı nasıl değişir" "$OUT/a_plan.json" "$T"
check "plan → konu" "$(jget topic < "$OUT/a_plan.json")" "plans"
check "plan → doğru sayfa" "$(jget link.url < "$OUT/a_plan.json" | grep -o '/yonetim/planlar$')" "/yonetim/planlar"
has "$OUT/a_plan.json" 'Aylık fiyat'

ask "havale onayı nerede" "$OUT/a_transfer.json" "$T"
check "havale → konu" "$(jget topic < "$OUT/a_transfer.json")" "payments"
check "havale → doğru sayfa" "$(jget link.url < "$OUT/a_transfer.json" | grep -o '/yonetim/odemeler$')" "/yonetim/odemeler"

ask "SMTP ayarları nerede" "$OUT/a_smtp.json" "$T"
check "SMTP → konu" "$(jget topic < "$OUT/a_smtp.json")" "settings_mail"
check "SMTP → doğru sayfa" "$(jget link.url < "$OUT/a_smtp.json" | grep -o '/yonetim/ayarlar/mail$')" "/yonetim/ayarlar/mail"

ask "kampanya nasıl gönderirim" "$OUT/a_campaign.json" "$T"
check "kampanya → konu" "$(jget topic < "$OUT/a_campaign.json")" "campaigns"
check "kampanya → doğru sayfa" "$(jget link.url < "$OUT/a_campaign.json" | grep -o '/yonetim/kampanya$')" "/yonetim/kampanya"

ask "yıllık paket fiyatını nereden değiştiririm" "$OUT/a_year.json" "$T"
check "yıllık fiyat → planlar" "$(jget topic < "$OUT/a_year.json")" "plans"
has "$OUT/a_year.json" 'Yıllık fiyat'

ask "araç durumunu nasıl değiştiririm" "$OUT/a_vehicle.json" "$T"
check "araç durumu → araçlar" "$(jget topic < "$OUT/a_vehicle.json")" "vehicles"
check "araç → doğru sayfa" "$(jget link.url < "$OUT/a_vehicle.json" | grep -o '/yonetim/araclar$')" "/yonetim/araclar"

ask "evrak listesi nerede" "$OUT/a_documents.json" "$T"
check "evrak listesi → evraklar" "$(jget topic < "$OUT/a_documents.json")" "documents"
check "evrak → doğru sayfa" "$(jget link.url < "$OUT/a_documents.json" | grep -o '/yonetim/evraklar$')" "/yonetim/evraklar"

ask "mesajları nasıl okurum" "$OUT/a_messages.json" "$T"
check "mesajlar → mesajlar" "$(jget topic < "$OUT/a_messages.json")" "messages"
check "mesaj → doğru sayfa" "$(jget link.url < "$OUT/a_messages.json" | grep -o '/yonetim/mesajlar$')" "/yonetim/mesajlar"

ask "slayt nasıl eklerim" "$OUT/a_slides.json" "$T"
check "slayt → slider" "$(jget topic < "$OUT/a_slides.json")" "slides"
check "slayt → doğru sayfa" "$(jget link.url < "$OUT/a_slides.json" | grep -o '/yonetim/slider$')" "/yonetim/slider"

ask "denetim kaydı nerede" "$OUT/a_audit.json" "$T"
check "denetim → denetim" "$(jget topic < "$OUT/a_audit.json")" "audit"
check "denetim → doğru sayfa" "$(jget link.url < "$OUT/a_audit.json" | grep -o '/yonetim/denetim$')" "/yonetim/denetim"

ask "neler yapabilirsin" "$OUT/a_overview.json" "$T"
check "genel bakış konusu" "$(jget topic < "$OUT/a_overview.json")" "overview"
check "genel bakış bağlantı sayısı (tüm bölümler)" "$(jlen related < "$OUT/a_overview.json")" "28"

echo "== kapsam koruması: yalnızca yönetim paneli =="
ask "bugün hava nasıl" "$OUT/a_weather.json" "$T"
check "hava durumu → reddedildi" "$(jget refused < "$OUT/a_weather.json")" "1"
check "hava durumu → bağlantı yok" "$(jget link < "$OUT/a_weather.json")" ""
has "$OUT/a_weather.json" 'yalnızca evrako YÖNETİM PANELİ'

ask "bana kısa bir şiir yaz" "$OUT/a_poem.json" "$T"
check "şiir → reddedildi" "$(jget refused < "$OUT/a_poem.json")" "1"

ask "müşteri paneli nasıl kullanılır" "$OUT/a_customer.json" "$T"
check "müşteri paneli → reddedildi" "$(jget refused < "$OUT/a_customer.json")" "1"

ask "python ile dosya okuma kodu yaz" "$OUT/a_code.json" "$T"
check "kod sorusu → reddedildi" "$(jget refused < "$OUT/a_code.json")" "1"

echo "== doğrulama, yetki ve CSRF =="
CODE=$(curl -s -b "$J" -c "$J" -o "$OUT/a_empty.json" -w '%{http_code}' -H 'Accept: application/json' \
  --data-urlencode "_token=$T" --data-urlencode "question=a" "$BASE/yonetim/rehber/sor")
check "tek karakterli soru → 422" "$CODE" "422"
LONG=$($PHP -r 'echo str_repeat("a", 401);')
CODE=$(curl -s -b "$J" -c "$J" -o "$OUT/a_long.json" -w '%{http_code}' -H 'Accept: application/json' \
  --data-urlencode "_token=$T" --data-urlencode "question=$LONG" "$BASE/yonetim/rehber/sor")
check "401 karakterlik soru → 422" "$CODE" "422"
CODE=$(curl -s -b "$J" -c "$J" -o "$OUT/a_nocsrf.html" -w '%{http_code}' \
  --data-urlencode "question=kredi" "$BASE/yonetim/rehber/sor")
check "CSRF token olmadan → 419" "$CODE" "419"

rm -f /tmp/guide_guest.txt
CODE=$(curl -s -c /tmp/guide_guest.txt -D "$OUT/guest_headers.txt" -o /dev/null -w '%{http_code}' \
  --data-urlencode "question=kredi" "$BASE/yonetim/rehber/sor")
check "oturumsuz istek → girişe yönlendirme" "$CODE" "302"
check "oturumsuz istek hedefi" "$(loc "$OUT/guest_headers.txt" | grep -o '/yonetim/giris')" "/yonetim/giris"

echo "== ayar sekmesi: yerel mod =="
curl -s -b "$J" -c "$J" "$BASE/yonetim/ayarlar/assistant" -o "$OUT/settings_guide.html"
has "$OUT/settings_guide.html" 'name="assistant_enabled"'
has "$OUT/settings_guide.html" 'name="assistant_endpoint"'
has "$OUT/settings_guide.html" 'name="assistant_model"'
has "$OUT/settings_guide.html" 'name="assistant_api_key"'
has "$OUT/settings_guide.html" 'Yerel rehber modu'
has "$OUT/settings_guide.html" 'yalnızca bu panelin kullanımıyla'
nothas "$OUT/settings_guide.html" 'value="test-key-123"'

echo "== AI modu: sahte OpenAI uyumlu uç nokta =="
FAKE_LLM_LOG="$FAKE_LOG" setsid "$PHP" -S "127.0.0.1:$FAKE_PORT" "$ROOT/.freebuff/fake-llm.php" \
  > "$OUT/fake_llm.log" 2>&1 < /dev/null &
FAKE_PID=$!
sleep 1
T=$(tok "$OUT/settings_guide.html")
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$T" --data-urlencode "tab=assistant" \
  --data-urlencode "assistant_enabled=1" \
  --data-urlencode "assistant_endpoint=http://127.0.0.1:$FAKE_PORT/v1/chat/completions" \
  --data-urlencode "assistant_model=fake-model" \
  --data-urlencode "assistant_api_key=test-key-123" \
  "$BASE/yonetim/ayarlar/kaydet")
check "POST ayarlar/kaydet (rehber)" "$CODE" "302"
check "assistant.model kaydedildi" "$($MY -e "SELECT \`value\` FROM settings WHERE \`key\`='assistant.model'")" "fake-model"
check "assistant.api_key kaydedildi" "$($MY -e "SELECT \`value\` FROM settings WHERE \`key\`='assistant.api_key'")" "test-key-123"

curl -s -b "$J" -c "$J" "$BASE/yonetim/" -o "$OUT/dashboard_ai.html"
has "$OUT/dashboard_ai.html" 'data-guide-mode="ai"'
has "$OUT/dashboard_ai.html" 'AI modu'
nothas "$OUT/dashboard_ai.html" 'test-key-123'

ask "kredi nasıl yüklerim" "$OUT/a_ai.json" "$T"
check "AI yanıtı modu" "$(jget mode < "$OUT/a_ai.json")" "ai"
has "$OUT/a_ai.json" 'Sahte AI yanıtı'
check "AI yanıtında doğru sayfa bağlantısı" "$(jget link.url < "$OUT/a_ai.json" | grep -o '/yonetim/uyeler$')" "/yonetim/uyeler"

check "AI isteği diske yazıldı" "$([ -s "$FAKE_LOG" ] && echo yes)" "yes"
PROMPT_CHECK=$($PHP -r '$d=json_decode(file_get_contents($argv[1]),true); $s=$d["messages"][0]["content"]??""; $u=$d["messages"][1]["content"]??""; echo (($d["model"]??"")==="fake-model"?"model-ok":"model-bad")."|".(str_contains($s,"STRICT SCOPE")?"scope-ok":"scope-bad")."|".(str_contains($s,"ADMIN PANEL")?"admin-ok":"admin-bad")."|".(str_contains($s,"/yonetim/uyeler")?"kb-ok":"kb-bad")."|".($u==="kredi nasıl yüklerim"?"user-ok":"user-bad");' "$FAKE_LOG")
check "AI isteği: rol, kapsam, bilgi tabanı, kullanıcı sorusu" "$PROMPT_CHECK" "model-ok|scope-ok|admin-ok|kb-ok|user-ok"

echo "== AI anahtarı boş kaydedilirse korunuyor =="
curl -s -b "$J" -c "$J" "$BASE/yonetim/ayarlar/assistant" -o "$OUT/settings_ai.html"
T=$(tok "$OUT/settings_ai.html")
nothas "$OUT/settings_ai.html" 'test-key-123'
CODE=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$T" --data-urlencode "tab=assistant" \
  --data-urlencode "assistant_enabled=1" \
  --data-urlencode "assistant_endpoint=http://127.0.0.1:$FAKE_PORT/v1/chat/completions" \
  --data-urlencode "assistant_model=fake-model" \
  --data-urlencode "assistant_api_key=" \
  "$BASE/yonetim/ayarlar/kaydet")
check "boş anahtarla kaydetme" "$CODE" "302"
check "anahtar korundu" "$($MY -e "SELECT \`value\` FROM settings WHERE \`key\`='assistant.api_key'")" "test-key-123"

echo "== güvenlik: uzak http uç noktası kabul edilmez (yerel moda düşer) =="
T=$(tok "$OUT/settings_ai.html")
curl -s -b "$J" -c "$J" -o /dev/null \
  --data-urlencode "_token=$T" --data-urlencode "tab=assistant" \
  --data-urlencode "assistant_enabled=1" \
  --data-urlencode "assistant_endpoint=http://example.com/v1/chat/completions" \
  --data-urlencode "assistant_model=fake-model" \
  "$BASE/yonetim/ayarlar/kaydet"
curl -s -b "$J" -c "$J" "$BASE/yonetim/" -o "$OUT/dashboard_http.html"
has "$OUT/dashboard_http.html" 'data-guide-mode="guide"'
ask "kredi nasıl yüklerim" "$OUT/a_http.json" "$T"
check "güvenli olmayan uç nokta → yerel yanıt" "$(jget mode < "$OUT/a_http.json")" "guide"

echo "== rehber kapatılınca widget ve uç nokta kapanır =="
curl -s -b "$J" -c "$J" "$BASE/yonetim/ayarlar/assistant" -o "$OUT/settings_off.html"
T=$(tok "$OUT/settings_off.html")
curl -s -b "$J" -c "$J" -o /dev/null \
  --data-urlencode "_token=$T" --data-urlencode "tab=assistant" \
  --data-urlencode "assistant_endpoint=http://127.0.0.1:$FAKE_PORT/v1/chat/completions" \
  --data-urlencode "assistant_model=fake-model" \
  "$BASE/yonetim/ayarlar/kaydet"
curl -s -b "$J" -c "$J" "$BASE/yonetim/" -o "$OUT/dashboard_off.html"
nothas "$OUT/dashboard_off.html" 'data-guide-toggle'
nothas "$OUT/dashboard_off.html" 'data-guide-panel'
CODE=$(curl -s -b "$J" -c "$J" -o "$OUT/a_off.json" -w '%{http_code}' -H 'Accept: application/json' \
  --data-urlencode "_token=$T" --data-urlencode "question=kredi" "$BASE/yonetim/rehber/sor")
check "kapalı rehber → 403" "$CODE" "403"
check "kapalı rehber mesajı" "$(jget error < "$OUT/a_off.json" | grep -o 'kapatıldı')" "kapatıldı"

# Yeniden aç (hız sınırı testi için yeni oturum)
curl -s -b "$J" -c "$J" "$BASE/yonetim/ayarlar/assistant" -o "$OUT/settings_on.html"
T=$(tok "$OUT/settings_on.html")
curl -s -b "$J" -c "$J" -o /dev/null \
  --data-urlencode "_token=$T" --data-urlencode "tab=assistant" --data-urlencode "assistant_enabled=1" \
  --data-urlencode "assistant_endpoint=" --data-urlencode "assistant_model=fake-model" \
  --data-urlencode "assistant_api_key=" "$BASE/yonetim/ayarlar/kaydet"
check "rehber yeniden açıldı" "$($MY -e "SELECT \`value\` FROM settings WHERE \`key\`='assistant.enabled'")" "1"
check "boş uç nokta → yerel mod" "$($MY -e "SELECT \`value\` FROM settings WHERE \`key\`='assistant.endpoint'")" ""
curl -s -b "$J" -c "$J" "$BASE/yonetim/" -o "$OUT/dashboard_local.html"
has "$OUT/dashboard_local.html" 'data-guide-mode="guide"'

echo "== hız sınırı: 10 dakikada 20 soru =="
T=$(login)
for i in $(seq 1 20); do
  C=$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}' -H 'Accept: application/json' \
    --data-urlencode "_token=$T" --data-urlencode "question=kredi" "$BASE/yonetim/rehber/sor")
  [ "$i" = "20" ] && check "20. soru kabul" "$C" "200"
done
C=$(curl -s -b "$J" -c "$J" -o "$OUT/a_limit.json" -w '%{http_code}' -H 'Accept: application/json' \
  --data-urlencode "_token=$T" --data-urlencode "question=kredi" "$BASE/yonetim/rehber/sor")
check "21. soru → 429" "$C" "429"
check "hız sınırı mesajı" "$(jget error < "$OUT/a_limit.json" | grep -o 'Çok sık')" "Çok sık"

echo "== Gemini anahtarı rehberi AI moduna alır =="
$PHP "$ROOT/.freebuff/guide-settings.php" restore-gemini "$BACKUP" >/dev/null 2>&1
curl -s -b "$J" "$BASE/yonetim/" -o "$OUT/dashboard_gemini.html"
has "$OUT/dashboard_gemini.html" 'data-guide-mode="ai"'
has "$OUT/dashboard_gemini.html" 'AI modu'

echo
echo "SONUÇ: $pass geçti, $fail kaldı"
exit $fail
