#!/usr/bin/env bash
# Profil güncelleme: mevcut şifre doğrulaması + yönetici onayı zorunlu.
# Gerçek MariaDB + HTTP (müşteri paneli + yönetim paneli) ile uçtan uca test.
set -u
BASE="http://127.0.0.1:3001"
ROOT="/workspace/chats/9e03ed61266af80267d81f50"
export HOME="${HOME:-/workspace/homes/9e03ed61266af80267d81f50}"
PHP="$HOME/bin/php/php"
MY() { "$HOME/mariadb/bin/mariadb" --no-defaults --socket="$HOME/mysql-tmp/mysql.sock" -uroot evrako_live -N -B -e "$1"; }
OUT=/tmp/profile_out; mkdir -p "$OUT"
STAMP=$(date +%s)
EMAIL="prof-$STAMP@evrako.test"
PASS='ProfPass123!'
NEWNAME="Yeni Isim $STAMP"
USER_ID=""; ORG_ID=""
J=/tmp/prof_cust.jar; JC=/tmp/prof_admin.jar
rm -f "$J" "$JC"
pass=0; fail=0
ok(){ echo "  ✓ $1"; pass=$((pass+1)); }
bad(){ echo "  ✗ $1"; fail=$((fail+1)); }
check(){ [ "$2" = "$3" ] && ok "$1 ($2)" || bad "$1 — beklenen '$3', gelen '$2'"; }
has(){ grep -qF "$2" "$1" && ok "$(basename "$1") içeriyor: $2" || bad "$(basename "$1") eksik: $2"; }
tok(){ grep -o 'name="_token" value="[^"]*"' "$1" | head -1 | sed 's/.*value="//;s/"$//'; }

OLD_HASH=$(MY "SELECT password_hash FROM users WHERE id=1")
cleanup(){
  [ -n "$USER_ID" ] && MY "DELETE FROM profile_change_requests WHERE user_id=$USER_ID" >/dev/null 2>&1
  [ -n "$ORG_ID" ] && MY "DELETE FROM organizations WHERE id=$ORG_ID" >/dev/null 2>&1
  [ -n "$USER_ID" ] && MY "DELETE FROM users WHERE id=$USER_ID" >/dev/null 2>&1
  MY "UPDATE users SET password_hash='$OLD_HASH' WHERE id=1" >/dev/null 2>&1
  echo "  (geçici üyelik silindi, yönetici şifresi geri yüklendi)"
}
trap cleanup EXIT

echo "== hazırlık =="
HASH=$($PHP -r "echo password_hash('$PASS', PASSWORD_DEFAULT);")
USER_ID=$(MY "INSERT INTO users (full_name,email,password_hash,role,status,locale,email_verified_at,created_at) VALUES ('Profil Test','$EMAIL','$HASH','customer','active','tr',NOW(),NOW()); SELECT LAST_INSERT_ID();")
ORG_ID=$(MY "INSERT INTO organizations (owner_user_id,name,type,created_at) VALUES ($USER_ID,'Profil Test $STAMP','individual',NOW()); SELECT LAST_INSERT_ID();")
MY "INSERT INTO organization_members (organization_id,user_id,role,status,created_at) VALUES ($ORG_ID,$USER_ID,'owner','active',NOW())" >/dev/null

curl -s -c "$J" "$BASE/giris" -o "$OUT/login.html"
UT=$(tok "$OUT/login.html")
curl -s -b "$J" -c "$J" -o /dev/null --data-urlencode "_token=$UT" \
  --data-urlencode "email=$EMAIL" --data-urlencode "password=$PASS" "$BASE/giris"

echo "== panelim: bilgiler görünür =="
check "GET /panel/profil" "$(curl -s -b "$J" -c "$J" -o "$OUT/profile.html" -w '%{http_code}' "$BASE/panel/profil")" "200"
has "$OUT/profile.html" 'Profil Test'
has "$OUT/profile.html" "$EMAIL"
has "$OUT/profile.html" 'Kayıtlı bilgiler'

echo "== yanlış şifreyle talep reddedilir =="
PT=$(tok "$OUT/profile.html")
curl -s -b "$J" -c "$J" -o /dev/null --data-urlencode "_token=$PT" \
  --data-urlencode "full_name=$NEWNAME" --data-urlencode "email=$EMAIL" \
  --data-urlencode "current_password=YanlisSifre123!" "$BASE/panel/profil"
check "talep oluşmadı" "$(MY "SELECT COUNT(*) FROM profile_change_requests WHERE user_id=$USER_ID")" "0"
check "isim değişmedi" "$(MY "SELECT full_name FROM users WHERE id=$USER_ID")" "Profil Test"

echo "== doğru şifreyle talep onaya düşer =="
curl -s -b "$J" -c "$J" -o /dev/null --data-urlencode "_token=$PT" \
  --data-urlencode "full_name=$NEWNAME" --data-urlencode "email=$EMAIL" \
  --data-urlencode "current_password=$PASS" "$BASE/panel/profil"
check "bekleyen talep" "$(MY "SELECT status FROM profile_change_requests WHERE user_id=$USER_ID ORDER BY id DESC LIMIT 1")" "pending"
check "isim hâlâ eski (onay yok)" "$(MY "SELECT full_name FROM users WHERE id=$USER_ID")" "Profil Test"
REQ_ID=$(MY "SELECT id FROM profile_change_requests WHERE user_id=$USER_ID ORDER BY id DESC LIMIT 1")
curl -s -b "$J" -c "$J" "$BASE/panel/profil" -o "$OUT/profile_pending.html"
has "$OUT/profile_pending.html" 'Onay bekleyen talebiniz var.'
has "$OUT/profile_pending.html" "$NEWNAME"

echo "== yönetici onayı uygular =="
NEW_HASH=$($PHP -r "echo password_hash('TempAdmin123!', PASSWORD_DEFAULT);")
MY "UPDATE users SET password_hash='$NEW_HASH' WHERE id=1" >/dev/null
curl -s -c "$JC" "$BASE/yonetim/giris" -o "$OUT/admin_login.html"
AT=$(tok "$OUT/admin_login.html")
curl -s -b "$JC" -c "$JC" -o /dev/null --data-urlencode "_token=$AT" \
  --data-urlencode "email=admin@evrako.test" --data-urlencode "password=TempAdmin123!" "$BASE/yonetim/giris"
check "GET /yonetim/profil-talepleri" "$(curl -s -b "$JC" -c "$JC" -o "$OUT/requests.html" -w '%{http_code}' "$BASE/yonetim/profil-talepleri")" "200"
has "$OUT/requests.html" "$NEWNAME"
RT=$(tok "$OUT/requests.html")
curl -s -b "$JC" -c "$JC" -o /dev/null --data-urlencode "_token=$RT" --data-urlencode "id=$REQ_ID" "$BASE/yonetim/profil-talepleri/onayla"
check "talep onaylandı" "$(MY "SELECT status FROM profile_change_requests WHERE id=$REQ_ID")" "approved"
check "isim güncellendi" "$(MY "SELECT full_name FROM users WHERE id=$USER_ID")" "$NEWNAME"

echo "== talep geri çekilebilir =="
curl -s -b "$J" -c "$J" -o /dev/null --data-urlencode "_token=$PT" \
  --data-urlencode "full_name=Ikinci Deneme" --data-urlencode "email=$EMAIL" \
  --data-urlencode "current_password=$PASS" "$BASE/panel/profil"
REQ2=$(MY "SELECT id FROM profile_change_requests WHERE user_id=$USER_ID ORDER BY id DESC LIMIT 1")
check "ikinci talep beklemede" "$(MY "SELECT status FROM profile_change_requests WHERE id=$REQ2")" "pending"
curl -s -b "$J" -c "$J" -o /dev/null --data-urlencode "_token=$PT" --data-urlencode "id=$REQ2" "$BASE/panel/profil/iptal"
check "talep geri çekildi" "$(MY "SELECT status FROM profile_change_requests WHERE id=$REQ2")" "cancelled"
check "isim değişmedi" "$(MY "SELECT full_name FROM users WHERE id=$USER_ID")" "$NEWNAME"

echo ""
echo "SONUÇ: $pass geçti, $fail kaldı"
