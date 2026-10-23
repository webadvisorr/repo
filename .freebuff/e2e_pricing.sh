#!/usr/bin/env bash
# Fiyat sayfası: iki paket + araç indirimleri + evrak ücretsizliği.
# Gerçek HTTP; fiyatlar veritabanındaki standart paketten okunur.
set -u
BASE="http://127.0.0.1:3001"
export HOME="${HOME:-/workspace/homes/9e03ed61266af80267d81f50}"
OUT=/tmp/pricing_out; mkdir -p "$OUT"
pass=0; fail=0
ok(){ echo "  ✓ $1"; pass=$((pass+1)); }
bad(){ echo "  ✗ $1"; fail=$((fail+1)); }
has(){ grep -qF "$2" "$1" && ok "$1 içeriyor: $2" || bad "$1 eksik: $2"; }
nothas(){ grep -qF "$2" "$1" && bad "$1 içermemeliydi: $2" || ok "$1 beklendiği gibi: $2 yok"; }

echo "== site fiyat sayfası: iki paket =="
CODE=$(curl -s -o "$OUT/pricing.html" -w '%{http_code}' "$BASE/fiyatlar")
[ "$CODE" = "200" ] && ok "fiyat sayfası 200" || bad "fiyat sayfası $CODE"
has "$OUT/pricing.html" '6 Ay Ücretsiz Deneme'
has "$OUT/pricing.html" 'Standart Paket'
has "$OUT/pricing.html" '490 ₺'
has "$OUT/pricing.html" '392 ₺'
has "$OUT/pricing.html" '343 ₺'
has "$OUT/pricing.html" '2. araç'
has "$OUT/pricing.html" '4. araç ve sonrası'
has "$OUT/pricing.html" 'Evraklar için ek ücret yok'
has "$OUT/pricing.html" 'Muayene, sigorta, kasko, seyrüsefer'
has "$OUT/pricing.html" 'data-vehicle-count'
has "$OUT/pricing.html" 'data-pricing-calc'
nothas "$OUT/pricing.html" 'Filo / Kurumsal'
nothas "$OUT/pricing.html" 'Profesyonel'
nothas "$OUT/pricing.html" 'Yıllık ödemede'

echo "== hesap makinesi kademeleri (sunucu tarafı) =="
CALC=$($HOME/bin/php/php -r '
require "app/bootstrap.php";
$q = App\Services\VehiclePricing::quote(4);
echo implode("|", array_map(fn($i) => $i["price"], $q["items"])) . "|" . $q["total"] . "|" . $q["saved"];
')
[ "$CALC" = "490|392|343|343|1568|392" ] && ok "4 araç toplamı ve tasarruf ($CALC)" || bad "4 araç hesabı: $CALC"

FIVE=$($HOME/bin/php/php -r '
require "app/bootstrap.php";
$q = App\Services\VehiclePricing::quote(5);
echo $q["items"][4]["price"] . "|" . $q["total"];
')
[ "$FIVE" = "343|1911" ] && ok "5. araç %30 indirimli ($FIVE)" || bad "5. araç hesabı: $FIVE"

echo "== plan kataloğu: yalnızca iki aktif paket =="
PLANS=$($HOME/bin/php/php -r '
require "app/bootstrap.php";
echo implode(",", array_map(fn($p) => $p["key"], App\Services\SubscriptionService::plans(true)));
')
[ "$PLANS" = "trial,standard" ] && ok "aktif planlar: $PLANS" || bad "aktif planlar: $PLANS"

TRIAL=$($HOME/bin/php/php -r '
require "app/bootstrap.php";
$p = App\Core\Database::first("SELECT * FROM plans WHERE `key` = \"trial\"");
echo (int) $p["trial_days"] . "|" . (int) $p["price"] . "|" . (int) $p["max_vehicles"];
')
[ "$TRIAL" = "180|0|1" ] && ok "deneme paketi 6 ay / 1 araç ($TRIAL)" || bad "deneme paketi: $TRIAL"

STD=$($HOME/bin/php/php -r '
require "app/bootstrap.php";
$p = App\Core\Database::first("SELECT * FROM plans WHERE `key` = \"standard\"");
echo (float) $p["price"] . "|" . $p["billing_interval"];
')
[ "$STD" = "490|lifetime" ] && ok "standart paket 490 TL tek seferlik ($STD)" || bad "standart paket: $STD"


echo "== standart pakette ek araç ücreti kademeleri =="
FEES=$($HOME/bin/php/php .freebuff/pricing-call.php fees 2>&1)
[ "$FEES" = "null,2:20:392:1,3:30:343:1,4:30:343:1,5:30:343:1|blocked" ] && ok "ek araç ücretleri ve kredi kontrolü ($FEES)" || bad "ek araç ücretleri: $FEES"

echo "SONUÇ: $pass geçti, $fail kaldı"
[ "$fail" -eq 0 ]
