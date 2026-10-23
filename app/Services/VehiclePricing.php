<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Araç bazlı fiyatlandırma.
 *
 * Standart paket tek seferlik ücretlidir; aynı hesaba eklenen her yeni araç
 * kademeli indirim alır:
 *   1. araç  → tam fiyat (490 TL)
 *   2. araç  → %20 indirim
 *   3. araç  → %30 indirim
 *   4. ve sonrası → %30 indirim (sabit)
 *
 * Evrak ekleme (muayene, sigorta, kasko, seyrüsefer ve diğerleri) hiçbir
 * pakette ek ücret gerektirmez; bu yüzden burada evrak başına ücret yoktur.
 */
final class VehiclePricing
{
    public const DEFAULT_BASE_PRICE = 490.0;

    /** Araç sırasına göre indirim yüzdeleri. 4+ için son değer geçerlidir. */
    public const DISCOUNTS = [
        1 => 0,
        2 => 20,
        3 => 30,
        4 => 30,
    ];

    /** Standart paketin güncel taban fiyatı (panelden değiştirilebilir). */
    public static function basePrice(): float
    {
        try {
            $price = Database::scalar("SELECT price FROM plans WHERE `key` = 'standard' LIMIT 1");
            if ($price !== null && (float) $price > 0) {
                return round((float) $price, 2);
            }
        } catch (\Throwable) {
            // Kurulum öncesi/DB hatasında varsayılan fiyat kullanılır.
        }
        return self::DEFAULT_BASE_PRICE;
    }

    public static function currency(): string
    {
        try {
            $currency = Database::scalar("SELECT currency FROM plans WHERE `key` = 'standard' LIMIT 1");
            if (is_string($currency) && $currency !== '') {
                return $currency;
            }
        } catch (\Throwable) {
        }
        return 'TRY';
    }

    /** Verilen sıradaki aracın indirim yüzdesi (1 tabanlı). */
    public static function discountFor(int $vehicleNo): int
    {
        if ($vehicleNo <= 1) {
            return 0;
        }
        if ($vehicleNo >= 4) {
            return self::DISCOUNTS[4];
        }
        return self::DISCOUNTS[$vehicleNo] ?? self::DISCOUNTS[4];
    }

    /** Verilen sıradaki aracın indirimli fiyatı (1 tabanlı). */
    public static function priceFor(int $vehicleNo, ?float $basePrice = null): float
    {
        $base = $basePrice ?? self::basePrice();
        $discount = self::discountFor($vehicleNo);
        return round($base * (1 - $discount / 100), 2);
    }

    /**
     * Araç sayısına göre kalem kalem teklif.
     *
     * @return array{count:int,total:float,saved:float,base:float,currency:string,items:array<int,array{no:int,discount:int,price:float,full:float}>}
     */
    public static function quote(int $count, ?float $basePrice = null): array
    {
        $count = max(0, min(50, $count));
        $base = $basePrice ?? self::basePrice();
        $items = [];
        $total = 0.0;
        for ($no = 1; $no <= $count; $no++) {
            $discount = self::discountFor($no);
            $price = round($base * (1 - $discount / 100), 2);
            $items[] = ['no' => $no, 'discount' => $discount, 'price' => $price, 'full' => $base];
            $total += $price;
        }
        $total = round($total, 2);

        return [
            'count'    => $count,
            'total'    => $total,
            'saved'    => round($base * $count - $total, 2),
            'base'     => round($base, 2),
            'currency' => self::currency(),
            'items'    => $items,
        ];
    }

    /** Tabloda gösterilecek örnek kademeler (1–4 ve sonrası). */
    public static function tiers(): array
    {
        return [
            1 => ['label' => '1. araç', 'discount' => 0, 'price' => self::priceFor(1), 'note' => 'Paket fiyatı'],
            2 => ['label' => '2. araç', 'discount' => 20, 'price' => self::priceFor(2), 'note' => 'Ek araç'],
            3 => ['label' => '3. araç', 'discount' => 30, 'price' => self::priceFor(3), 'note' => 'Ek araç'],
            4 => ['label' => '4. araç ve sonrası', 'discount' => 30, 'price' => self::priceFor(4), 'note' => 'Her ek araç'],
        ];
    }

    public static function money(float $amount, string $currency = 'TRY'): string
    {
        return number_format($amount, 2, ',', '.') . ' ' . $currency;
    }
}
