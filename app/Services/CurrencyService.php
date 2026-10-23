<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Core\Settings;

/**
 * Görüntüleme para birimi (site fiyatları).
 *
 * Taban (tahsilat) birimi `payment.currency` ayarıdır; ödemeler her zaman bu birimde alınır.
 * Ziyaretçi footer'dan başka bir birim seçerse paket fiyatları o birime çevrilerek gösterilir.
 *
 * Kurlar panelden girilir (`Ayarlar → Ödeme`):
 *   payment.rate_usd / payment.rate_eur / payment.rate_gbp = "1 birim kaç taban para" (ör. 1 USD = 49.57 TRY)
 * Kuru 0 olan birim seçicide listelenmez, yani döviz gösterimi varsayılan olarak kapalıdır.
 */
final class CurrencyService
{
    /** Seçimin saklandığı çerez. */
    public const COOKIE = 'evrako_currency';

    /** Birim simgeleri (sembolü olmayan birim kodu ile gösterilir). */
    private const SYMBOLS = ['TRY' => '₺', 'USD' => '$', 'EUR' => '€', 'GBP' => '£'];

    /** Desteklenen taban-dışı birimler ve kur ayar anahtarları. */
    private const RATE_KEYS = [
        'USD' => 'payment.rate_usd',
        'EUR' => 'payment.rate_eur',
        'GBP' => 'payment.rate_gbp',
    ];

    /** Aynı istek içinde seçilen birim (çerez ancak sonraki istekte okunur). */
    private static ?string $override = null;

    /** Tahsilat (taban) birimi. */
    public static function base(): string
    {
        $base = strtoupper(trim((string) Settings::get('payment.currency', 'TRY')));
        return $base !== '' ? $base : 'TRY';
    }

    /** Panelden kur girilmiş birimler (taban her zaman dahil). */
    public static function options(): array
    {
        $options = [self::base() => self::label(self::base())];
        foreach (self::RATE_KEYS as $code => $key) {
            if ($code === self::base() || self::rate($code) <= 0) {
                continue;
            }
            $options[$code] = self::label($code);
        }
        return $options;
    }

    /** 1 birim = kaç taban para (taban birim için 1.0). */
    public static function rate(string $code): float
    {
        $code = strtoupper(trim($code));
        if ($code === self::base()) {
            return 1.0;
        }
        $key = self::RATE_KEYS[$code] ?? null;
        if ($key === null) {
            return 0.0;
        }
        return max(0.0, (float) Settings::get($key, '0'));
    }

    /** O an geçerli görüntüleme birimi. */
    public static function current(): string
    {
        if (self::$override !== null) {
            return self::$override;
        }
        $cookie = (new Request())->cookie(self::COOKIE);
        if (is_string($cookie) && $cookie !== '') {
            $code = strtoupper(trim($cookie));
            if (array_key_exists($code, self::options())) {
                return $code;
            }
        }
        return self::base();
    }

    /** Seçimi kaydeder (?para=USD). Geçersiz birim yok sayılır. */
    public static function select(string $code): bool
    {
        $code = strtoupper(trim($code));
        if (!array_key_exists($code, self::options())) {
            return false;
        }
        self::$override = $code;
        if (!headers_sent()) {
            $request = new Request();
            setcookie(self::COOKIE, $code, [
                'expires'  => time() + 31536000,
                'path'     => '/',
                'secure'   => $request->isSecure(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        return true;
    }

    /** Tutarı bir birimden diğerine çevirir (kurlar taban birim üzerinden oranlanır). */
    public static function convert(float $amount, string $from, ?string $to = null): float
    {
        $to = strtoupper(trim((string) ($to ?? self::current())));
        $from = strtoupper(trim($from));
        if ($from === '' || $from === $to) {
            return $amount;
        }
        $fromRate = self::rate($from);
        $toRate = self::rate($to);
        if ($fromRate <= 0 || $toRate <= 0) {
            return $amount;
        }
        return $amount * $fromRate / $toRate;
    }

    /** Tutarı seçili birimde biçimler: "1.450 ₺", "$42,03". */
    public static function format(float $amount, ?string $code = null): string
    {
        $code = strtoupper(trim((string) ($code ?? self::current())));
        $rounded = round($amount, 2);
        // Taban birimde tam sayılar kısa yazılır (49 ₺); dövizde kuruş her zaman gösterilir ($2,00).
        $isWhole = abs($rounded - round($rounded)) < 0.005;
        $decimals = ($code !== self::base() || !$isWhole) ? 2 : 0;
        $number = number_format($rounded, $decimals, ',', '.');
        return $code === 'TRY'
            ? $number . ' ' . self::symbol($code)
            : self::symbol($code) . $number;
    }

    /**
     * Plan fiyatını seçili birimde biçimler; ücretsiz planda null döner.
     *
     * @param array<string,mixed> $plan plans tablosu satırı
     */
    public static function planPrice(array $plan): ?string
    {
        return self::planPriceFor($plan, 'month');
    }

    /**
     * Planın belirli dönemdeki (month/year/lifetime) fiyatını seçili birimde biçimler.
     * Ücretsiz/satılmayan dönemde null döner.
     *
     * @param array<string,mixed> $plan plans tablosu satırı
     */
    public static function planPriceFor(array $plan, string $interval): ?string
    {
        $amount = \App\Services\SubscriptionService::priceFor($plan, $interval);
        $available = \App\Services\SubscriptionService::intervals($plan);
        if ($amount <= 0 || !array_key_exists(\App\Services\SubscriptionService::intervalFor($plan, $interval), $available)) {
            return null;
        }
        $from = strtoupper(trim((string) ($plan['currency'] ?? self::base())));
        return self::format(self::convert($amount, $from !== '' ? $from : self::base()));
    }

    public static function symbol(string $code): string
    {
        return self::SYMBOLS[strtoupper(trim($code))] ?? strtoupper(trim($code));
    }

    /** Seçicide gösterilen etiket: "USD ($)". */
    public static function label(string $code): string
    {
        $code = strtoupper(trim($code));
        $symbol = self::SYMBOLS[$code] ?? null;
        return $symbol === null ? $code : $code . ' (' . $symbol . ')';
    }

    /** Fiyatların çevrilerek gösterildiğini açıklayan dipnot (taban birimdeyken boş). */
    public static function note(): string
    {
        $current = self::current();
        if ($current === self::base()) {
            return '';
        }
        return t('plans.currency_note', [
            'currency' => $current,
            'base'     => self::base(),
            'rate'     => number_format(self::rate($current), 2, ',', '.'),
        ]);
    }

}
