#!/usr/bin/env bash
# 6 dilde site + musteri paneli taramasi: cevrilmemis anahtar ve Turkce artiklari raporlar.
set -u
BASE="http://127.0.0.1:3001"
ROOT="/workspace/chats/9e03ed61266af80267d81f50"
export HOME="${HOME:-/workspace/homes/9e03ed61266af80267d81f50}"
PHP="$HOME/bin/php/php"
MYSQL() { "$HOME/mariadb/bin/mariadb" --no-defaults --socket="$HOME/mysql-tmp/mysql.sock" -uroot evrako_live -N -B -e "$1"; }
OUT=/tmp/locale_out; mkdir -p "$OUT"
SUF=$(date +%s)
EMAIL="locale-test-$SUF@example.test"
PASSWORD="TempLocale123!"
ORG_NAME="Locale Test $SUF"
USER_ID=""; ORG_ID=""
pass=0; fail=0
ok(){ echo "  ✓ $1"; pass=$((pass+1)); }
bad(){ echo "  ✗ $1"; fail=$((fail+1)); }
tok(){ grep -o 'name="_token" value="[^"]*"' "$1" | head -1 | sed 's/.*value="//;s/"$//'; }

cleanup(){
  [ -n "$ORG_ID" ] && MYSQL "DELETE FROM organizations WHERE id=$ORG_ID" >/dev/null 2>&1
  [ -n "$USER_ID" ] && MYSQL "DELETE FROM users WHERE id=$USER_ID" >/dev/null 2>&1
  [ -n "$USER_ID" ] && MYSQL "DELETE FROM user_notifications WHERE user_id=$USER_ID" >/dev/null 2>&1
  rm -f "$OUT"/*.html "$OUT"/*.jar 2>/dev/null
}
trap cleanup EXIT

HASH=$($PHP -r "echo password_hash('$PASSWORD', PASSWORD_DEFAULT);")
USER_ID=$(MYSQL "INSERT INTO users (full_name,email,password_hash,role,status,locale,email_verified_at,created_at) VALUES ('Locale Test','$EMAIL','$HASH','customer','active','tr',NOW(),NOW()); SELECT LAST_INSERT_ID();")
ORG_ID=$(MYSQL "INSERT INTO organizations (owner_user_id,name,type,created_at) VALUES ($USER_ID,'$ORG_NAME','individual',NOW()); SELECT LAST_INSERT_ID();")
MYSQL "INSERT INTO organization_members (organization_id,user_id,role,status,created_at) VALUES ($ORG_ID,$USER_ID,'owner','active',NOW())" >/dev/null
echo "hazırlık: kullanıcı #$USER_ID kurum #$ORG_ID"

JAR="$OUT/user.jar"
curl -s -c "$JAR" "$BASE/giris" -o "$OUT/login.html"
T=$(tok "$OUT/login.html")
curl -s -c "$JAR" -b "$JAR" -X POST \
  --data-urlencode "_token=$T" --data-urlencode "email=$EMAIL" --data-urlencode "password=$PASSWORD" \
  "$BASE/giris" -o /dev/null

report_page(){
  local locale="$1" path="$2" code file
  file="$OUT/page-$locale.html"
  code=$(curl -s -b "$JAR" -o "$file" -w '%{http_code}' "$BASE$path?dil=$locale")
  [ "$code" = "200" ] || { bad "$locale $path HTTP $code"; return; }
  python3 - "$file" "$locale" "$path" <<'PY'
import re, sys
path, loc, url = sys.argv[1], sys.argv[2], sys.argv[3]
html = open(path, encoding='utf-8', errors='replace').read()
body = re.sub(r'<(script|style)[^>]*>.*?</\1>', '', html, flags=re.S)
raw_keys = sorted(set(re.findall(r'>\s*((?:nav|auth|common|inbox|admin|billing|profile|support|team|calendar|vehicles|documents|settings|footer)\.[a-z0-9_.]+)\s*<', body)))
text = re.sub(r'<[^>]+>', ' ', body)
tr = {}
for word in re.findall(r'[A-Za-zÇĞİÖŞÜçğıöşüâî]+', text):
    if re.search(r'[ğşıöçĞÜŞİÖÇâî]', word):
        tr[word] = tr.get(word, 0) + 1
top = sorted(tr.items(), key=lambda x: -x[1])[:8]
print(f'  {loc:3s} {url:34s} keys={len(raw_keys)} tr_words={len(tr)} ' + (f'| ör: ' + ', '.join(f'{w}({c})' for w, c in top) if top else ''))
if raw_keys:
    print(f'      çevrilmemiş anahtar: {", ".join(raw_keys[:6])}')
PY
}

echo "== Genel sayfalar =="
for loc in tr en ru ar fa el; do
  report_page "$loc" "/"
done
echo "== Müşteri paneli sayfaları =="
for loc in tr en ru ar fa el; do
  for p in /panel /panel/profil /panel/bildirimler /panel/odeme /panel/mesajlar /panel/destek /panel/takvim /panel/araclar; do
    report_page "$loc" "$p"
  done
done

echo "== Panel içerikleri dil değişince değişiyor mu =="
for loc in en ru ar fa el; do
  curl -s -b "$JAR" "$BASE/panel?dil=$loc" -o "$OUT/panel-$loc.html"
  grep -qi 'dir="rtl"' "$OUT/panel-$loc.html" && rtl="rtl" || rtl="ltr"
  echo "  $loc -> html lang/dir: $(grep -o 'lang="[a-z]*" dir="[a-z]*"' "$OUT/panel-$loc.html" | head -1) ($rtl)"
done

echo "== Sonuç: $pass geçti, $fail kaldı =="
exit 0
