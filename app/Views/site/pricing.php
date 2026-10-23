<?php

use App\Services\CurrencyService;
use App\Services\VehiclePricing;

$baseCurrency = VehiclePricing::currency();
$base = VehiclePricing::basePrice();
$viewCurrency = CurrencyService::current();
$displayBase = CurrencyService::convert($base, $baseCurrency);
$money = static fn (float $amount): string => CurrencyService::format(CurrencyService::convert($amount, $baseCurrency));
$symbol = CurrencyService::symbol($viewCurrency);
$tiers = VehiclePricing::tiers();
$plans = array_values(array_filter($plans ?? [], static fn (array $p): bool => in_array((string) $p['key'], ['trial', 'standard'], true)));
$plan = static function (string $key) use ($plans): ?array {
    foreach ($plans as $row) {
        if ((string) $row['key'] === $key) {
            return $row;
        }
    }
    return null;
};
$trial = $plan('trial');
$standard = $plan('standard');
$standardPrice = $standard !== null ? (float) $standard['price'] : $base;
$displayStandard = (float) CurrencyService::convert($standardPrice, $baseCurrency);
$trialDays = $trial !== null ? (int) $trial['trial_days'] : 180;
$trialMonths = max(1, (int) round($trialDays / 30));
?>
<section class="hero">
    <div class="container">
        <h1><?= e(t('nav.pricing')) ?></h1>
        <p class="lead"><?= e(__('İki basit paket: 6 ay ücretsiz deneme veya tek seferlik ödeme. Evrak eklemek her zaman ücretsiz.')) ?></p>
    </div>
</section>

<section class="section--tight">
    <div class="container">
        <div class="grid grid-2 pricing-plans">
            <div class="card plan">
                <span class="badge badge--info"><?= e(__('1. Paket')) ?></span>
                <h3 class="mb-0"><?= e(__('6 Ay Ücretsiz Deneme')) ?></h3>
                <div class="plan__price"><?= e('0') ?> <small>/ <?= e($trialMonths . ' ay') ?></small></div>
                <p class="muted mb-0"><?= e(__('Tek araçla, hiçbir ödeme yapmadan tüm sistemi 6 ay boyunca deneyin.')) ?></p>
                <ul>
                    <li><?= e(__('6 ay boyunca tam erişim, ücret yok')) ?></li>
                    <li><?= e(__('Tek araç kaydı')) ?></li>
                    <li><?= e(__('Muayene, sigorta, kasko, seyrüsefer ve tüm evraklar ücretsiz')) ?></li>
                    <li><?= e(__('E-posta ile süre hatırlatmaları')) ?></li>
                    <li><?= e(__('Deneme sonunda isterseniz Standart Pakete geçin')) ?></li>
                </ul>
                <a class="btn btn-outline btn-block mt-2" href="<?= e(url('/kayit')) ?>"><?= e(__('Ücretsiz başla')) ?></a>
            </div>

            <div class="card plan plan--featured">
                <span class="badge badge--ok"><?= e(__('2. Paket · En çok tercih edilen')) ?></span>
                <h3 class="mb-0"><?= e(__('Standart Paket')) ?></h3>
                <div class="plan__price"><?= e($money($standardPrice)) ?> <small>/ <?= e(__('tek seferlik')) ?></small></div>
                <p class="muted mb-0"><?= e(__('İlk araç için tek seferlik ödeme. Ek araçlarda kademeli indirim uygulanır.')) ?></p>
                <ul>
                    <li><?= e(__('İlk araç: ') . $money($standardPrice)) ?></li>
                    <li><?= e(__('2. araç %20 indirimli: ') . $money(VehiclePricing::priceFor(2, $standardPrice))) ?></li>
                    <li><?= e(__('3. araç %30 indirimli: ') . $money(VehiclePricing::priceFor(3, $standardPrice))) ?></li>
                    <li><?= e(__('4. araç ve sonrası %30 indirimli: ') . $money(VehiclePricing::priceFor(4, $standardPrice))) ?></li>
                    <li><?= e(__('Tüm evrak türleri ücretsiz, süresiz kullanım')) ?></li>
                </ul>
                <a class="btn btn-accent btn-block mt-2" href="<?= e(url('/kayit')) ?>"><?= e(__('Standart paketle başla')) ?></a>
            </div>
        </div>

        <div class="alert alert--success mt-4">
            <span aria-hidden="true">✓</span>
            <div>
                <strong><?= e(__('Evraklar için ek ücret yok.')) ?></strong>
                <?= e(__(' Muayene, sigorta, kasko, seyrüsefer ve diğer tüm evrakların eklenmesi, takibi ve hatırlatılması paket fiyatına dahildir. Araç başına ayrıca evrak ücreti alınmaz.')) ?>
            </div>
        </div>

        <div class="card mt-4">
            <div class="row row--between mb-2" style="align-items:flex-start;flex-wrap:wrap;gap:12px">
                <div>
                    <h2 style="font-size:1.2rem;margin:0 0 4px"><?= e(__('Ek araç indirimleri')) ?></h2>
                    <p class="muted mb-0" style="font-size:14px"><?= e(__('Aynı hesaba eklediğiniz her araç için geçerli tek seferlik ücret.')) ?></p>
                </div>
                <span class="badge badge--info"><?= e(__('İlk araç ') . $money($standardPrice)) ?></span>
            </div>
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th><?= e(__('Araç sırası')) ?></th>
                            <th><?= e(__('İndirim')) ?></th>
                            <th><?= e(__('Araç başı fiyat')) ?></th>
                            <th><?= e(__('Açıklama')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tiers as $tier): ?>
                            <tr>
                                <td><strong><?= e((string) $tier['label']) ?></strong></td>
                                <td>
                                    <?php if ((int) $tier['discount'] > 0): ?>
                                        <span class="badge badge--ok">%<?= (int) $tier['discount'] ?> <?= e('indirim') ?></span>
                                    <?php else: ?>
                                        <span class="muted"><?= e(__('İndirim yok')) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="mono"><strong><?= e($money((float) $tier['price'])) ?></strong></td>
                                <td class="muted"><?= e((string) $tier['note']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="hint mt-2"><?= e(__('Fiyatlar tek seferliktir, KDV hariçtir ve yalnızca araç kaydı için alınır. Evrak işlemleri ücretsizdir.')) ?></p>
        </div>

        <div class="card mt-4 pricing-calc" data-pricing-calc
             data-base="<?= e((string) $displayStandard) ?>"
             data-currency="<?= e($viewCurrency) ?>"
             data-symbol="<?= e($symbol) ?>">
            <h2 style="font-size:1.2rem;margin:0 0 4px"><?= e(__('Kaç aracınız var?')) ?></h2>
            <p class="muted" style="font-size:14px"><?= e(__('Araç sayısını seçin; indirimli toplam tutarı anında görün.')) ?></p>
            <div class="pricing-calc__controls">
                <label for="vehicle-count" class="sr-only"><?= e(__('Araç sayısı')) ?></label>
                <input id="vehicle-count" type="number" min="1" max="20" value="1" inputmode="numeric" data-vehicle-count>
                <div class="pricing-calc__quick">
                    <?php foreach ([1, 2, 3, 4, 5] as $quick): ?>
                        <button type="button" class="btn btn-sm btn-outline" data-vehicle-quick="<?= $quick ?>"><?= $quick ?> <?= e(__('araç')) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="pricing-calc__result" aria-live="polite">
                <div>
                    <span class="muted" style="font-size:13px"><?= e(__('Toplam')) ?></span>
                    <div class="pricing-calc__total" data-calc-total><?= e($money($standardPrice)) ?></div>
                </div>
                <div>
                    <span class="muted" style="font-size:13px"><?= e(__('Tasarruf')) ?></span>
                    <div class="pricing-calc__saved" data-calc-saved><?= e($money(0)) ?></div>
                </div>
            </div>
            <div class="table-wrap mt-3">
                <table class="data">
                    <thead>
                        <tr>
                            <th><?= e(__('Araç')) ?></th>
                            <th><?= e(__('İndirim')) ?></th>
                            <th><?= e(__('Fiyat')) ?></th>
                        </tr>
                    </thead>
                    <tbody data-calc-rows>
                        <tr><td>1</td><td>—</td><td class="mono"><?= e($money($standardPrice)) ?></td></tr>
                    </tbody>
                </table>
            </div>
            <a class="btn btn-accent mt-3" href="<?= e(url('/kayit')) ?>"><?= e(__('Hemen başla')) ?></a>
        </div>

        <?php $currencyNote = \App\Services\CurrencyService::note(); ?>
        <?php if ($currencyNote !== ''): ?>
            <p class="center muted mt-3" style="font-size:13.5px"><?= e($currencyNote) ?></p>
        <?php endif; ?>
        <p class="center muted mt-3" style="font-size:13.5px">
            <?= e(__('Fiyatlar KDV hariçtir. Ödemeler banka havalesi veya kredi bakiyesi ile alınmaktadır.')) ?>
        </p>
    </div>
</section>

<script nonce="<?= e(csrf_nonce()) ?>">
    (function () {
        var root = document.querySelector('[data-pricing-calc]');
        if (!root) { return; }
        var base = parseFloat(root.getAttribute('data-base')) || 0;
        var currency = root.getAttribute('data-currency') || 'TRY';
        var symbol = root.getAttribute('data-symbol') || '₺';
        var input = root.querySelector('[data-vehicle-count]');
        var totalEl = root.querySelector('[data-calc-total]');
        var savedEl = root.querySelector('[data-calc-saved]');
        var rowsEl = root.querySelector('[data-calc-rows]');
        var vehicleWord = <?= json_attr(__('araç')) ?>;
        var fmt = function (value) {
            var whole = Math.abs(value - Math.round(value)) < 0.005;
            var decimals = (currency !== 'TRY' || !whole) ? 2 : 0;
            var number = value.toLocaleString('tr-TR', { minimumFractionDigits: decimals, maximumFractionDigits: 2 });
            return currency === 'TRY' ? number + ' ₺' : symbol + number;
        };
        var discountFor = function (index) {
            if (index <= 1) { return 0; }
            if (index === 2) { return 20; }
            return 30;
        };
        var render = function () {
            var count = Math.max(1, Math.min(20, parseInt(input.value, 10) || 1));
            input.value = count;
            var total = 0;
            var rows = '';
            for (var i = 1; i <= count; i++) {
                var discount = discountFor(i);
                var price = Math.round(base * (1 - discount / 100) * 100) / 100;
                total += price;
                rows += '<tr><td>' + i + '. ' + vehicleWord + '</td><td>' + (discount ? '%' + discount : '—') + '</td>'
                    + '<td class="mono">' + fmt(price) + '</td></tr>';
            }
            total = Math.round(total * 100) / 100;
            totalEl.textContent = fmt(total);
            savedEl.textContent = fmt(Math.round((base * count - total) * 100) / 100);
            rowsEl.innerHTML = rows;
        };
        input.addEventListener('input', render);
        root.querySelectorAll('[data-vehicle-quick]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                input.value = btn.getAttribute('data-vehicle-quick');
                render();
            });
        });
        render();
    })();
</script>
