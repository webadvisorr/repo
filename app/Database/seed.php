<?php
declare(strict_types=1);

use App\Core\Database;

/**
 * Kurulum sonrasi varsayilan veriler: arac turleri, evrak turleri, planlar.
 */
final class Seeder
{
    public static function run(): void
    {
        self::vehicleTypes();
        self::documentTypes();
        self::plans();
    }

    private static function vehicleTypes(): void
    {
        $types = [
            ['car',        ['tr' => 'Otomobil', 'en' => 'Car', 'ru' => 'Легковой автомобиль', 'ar' => 'سيارة', 'fa' => 'خودرو', 'el' => 'Επιβατικό αυτοκίνητο'], 'car'],
            ['motorcycle', ['tr' => 'Motosiklet / Scooter', 'en' => 'Motorcycle / Scooter', 'ru' => 'Мотоцикл / Скутер', 'ar' => 'دراجة نارية', 'fa' => 'موتورسیکلت', 'el' => 'Μοτοσικλέτα'], 'motorcycle'],
            ['pickup',     ['tr' => 'Kamyonet / Pickup', 'en' => 'Pickup / Light truck', 'ru' => 'Пикап', 'ar' => 'شاحنة صغيرة', 'fa' => 'وانت', 'el' => 'Ημιφορτηγό'], 'truck'],
            ['truck',      ['tr' => 'Kamyon / Ağır vasıta', 'en' => 'Truck / Heavy vehicle', 'ru' => 'Грузовик', 'ar' => 'شاحنة', 'fa' => 'کامیون', 'el' => 'Φορτηγό'], 'truck'],
            ['minibus',    ['tr' => 'Minibüs / Otobüs', 'en' => 'Minibus / Bus', 'ru' => 'Минивэн / Автобус', 'ar' => 'حافلة صغيرة', 'fa' => 'مینی‌بوس / اتوبوس', 'el' => 'Μίνι λεωφορείο'], 'bus'],
            ['taxi',       ['tr' => 'Ticari taksi / Kiralık araç', 'en' => 'Taxi / Rental vehicle', 'ru' => 'Такси / Аренда', 'ar' => 'سيارة أجرة', 'fa' => 'تاکسی / خودروی کرایه', 'el' => 'Ταξί / Ενοικιαζόμενο'], 'taxi'],
            ['tractor',    ['tr' => 'Traktör / Tarım aracı', 'en' => 'Tractor / Farm vehicle', 'ru' => 'Трактор', 'ar' => 'جرار زراعي', 'fa' => 'تراکتور', 'el' => 'Τρακτέρ'], 'tractor'],
            ['atv',        ['tr' => 'ATV / Quad', 'en' => 'ATV / Quad', 'ru' => 'Квадроцикл', 'ar' => 'دراجة رباعية', 'fa' => 'ای‌تی‌وی', 'el' => 'Γουρούνα'], 'atv'],
            ['caravan',    ['tr' => 'Karavan / Römork', 'en' => 'Caravan / Trailer', 'ru' => 'Караван / Прицеп', 'ar' => 'مقطورة', 'fa' => 'کاروان / تریلر', 'el' => 'Τροχόσπιτο'], 'caravan'],
            ['boat',       ['tr' => 'Tekne / Deniz aracı', 'en' => 'Boat / Marine vehicle', 'ru' => 'Лодка / Катер', 'ar' => 'قارب', 'fa' => 'قایق', 'el' => 'Σκάφος'], 'boat'],
            ['other',      ['tr' => 'Diğer', 'en' => 'Other', 'ru' => 'Другое', 'ar' => 'أخرى', 'fa' => 'سایر', 'el' => 'Άλλο'], 'other'],
        ];
        $sort = 10;
        foreach ($types as [$key, $labels, $icon]) {
            if (Database::scalar('SELECT id FROM vehicle_types WHERE `key` = ?', [$key])) {
                continue;
            }
            Database::insert('vehicle_types', [
                'key'        => $key,
                'labels_json' => json_encode($labels, JSON_UNESCAPED_UNICODE),
                'icon'       => $icon,
                'sort_order' => $sort,
            ]);
            $sort += 10;
        }
    }

    private static function documentTypes(): void
    {
        // key, labels, varsayilan gecerlilik (ay), renk, dosya zorunlu
        $types = [
            ['inspection', ['tr' => 'Muayene (Emüasyon)', 'en' => 'Vehicle inspection', 'ru' => 'Техосмотр', 'ar' => 'فحص المركبة', 'fa' => 'معاینه فنی', 'el' => 'Τεχνικός έλεγχος'], 12, '#3B82F6', 0],
            ['insurance',  ['tr' => 'Trafik Sigortası', 'en' => 'Compulsory insurance', 'ru' => 'ОСАГО', 'ar' => 'تأمين إلزامي', 'fa' => 'بیمه اجباری', 'el' => 'Υποχρεωτική ασφάλεια'], 12, '#E5484D', 1],
            ['kasko',      ['tr' => 'Kasko', 'en' => 'Comprehensive insurance', 'ru' => 'КАСКО', 'ar' => 'تأمين شامل', 'fa' => 'بیمه بدنه', 'el' => 'Ασφάλεια οχήματος'], 12, '#8B5CF6', 1],
            ['road_tax',   ['tr' => 'Yol Vergisi', 'en' => 'Road tax', 'ru' => 'Дорожный налог', 'ar' => 'ضريبة الطريق', 'fa' => 'مالیات راه', 'el' => 'Τέλη κυκλοφορίας'], 12, '#F5A524', 0],
            ['license',    ['tr' => 'Ehliyet (sürücü)', 'en' => 'Driving licence', 'ru' => 'Водительские права', 'ar' => 'رخصة القيادة', 'fa' => 'گواهینامه رانندگی', 'el' => 'Άδεια οδήγησης'], 60, '#0EA5E9', 0],
            ['service',    ['tr' => 'Periyodik Bakım', 'en' => 'Scheduled service', 'ru' => 'Плановое ТО', 'ar' => 'صيانة دورية', 'fa' => 'سرویس دوره‌ای', 'el' => 'Περιοδική συντήρηση'], 12, '#14B8A6', 0],
            ['tires',      ['tr' => 'Lastik / Akü', 'en' => 'Tyres / Battery', 'ru' => 'Шины / Аккумулятор', 'ar' => 'إطارات / بطارية', 'fa' => 'لاستیک / باتری', 'el' => 'Ελαστικά / Μπαταρία'], 24, '#64748B', 0],
            ['permit',     ['tr' => 'İşletme / Kiralama İzni', 'en' => 'Operating permit', 'ru' => 'Разрешение на деятельность', 'ar' => 'تصريح تشغيل', 'fa' => 'مجوز فعالیت', 'el' => 'Άδεια λειτουργίας'], 12, '#EC4899', 1],
        ];
        $sort = 10;
        foreach ($types as [$key, $labels, $months, $color, $requiresFile]) {
            if (Database::scalar('SELECT id FROM document_types WHERE `key` = ?', [$key])) {
                continue;
            }
            Database::insert('document_types', [
                'key'                     => $key,
                'labels_json'             => json_encode($labels, JSON_UNESCAPED_UNICODE),
                'default_validity_months' => $months,
                'default_offsets'         => '7,3,1,0',
                'color'                   => $color,
                'requires_file'           => $requiresFile,
                'sort_order'              => $sort,
            ]);
            $sort += 10;
        }
    }

    private static function plans(): void
    {
        $plans = [
            ['free',  ['tr' => 'Ücretsiz', 'en' => 'Free', 'ru' => 'Бесплатно', 'ar' => 'مجاني', 'fa' => 'رایگان', 'el' => 'Δωρεάν'], 0, 'month', 1, 1, 2, 'email'],
            ['family', ['tr' => 'Aile', 'en' => 'Family', 'ru' => 'Семья', 'ar' => 'عائلة', 'fa' => 'خانواده', 'el' => 'Οικογένεια'], 149, 'month', 5, 3, 20, 'email,sms,telegram'],
            ['pro',   ['tr' => 'Profesyonel', 'en' => 'Pro', 'ru' => 'Профи', 'ar' => 'احترافي', 'fa' => 'حرفه‌ای', 'el' => 'Επαγγελματικό'], 349, 'month', 25, 10, 50, 'email,sms,whatsapp,telegram'],
            ['fleet', ['tr' => 'Filo / Kurumsal', 'en' => 'Fleet / Business', 'ru' => 'Автопарк', 'ar' => 'أسطول', 'fa' => 'ناوگان', 'el' => 'Στόλος'], 1490, 'month', 250, 50, 100, 'email,sms,whatsapp,telegram'],
        ];
        $sort = 10;
        foreach ($plans as [$key, $names, $price, $interval, $maxVehicles, $maxUsers, $maxDocs, $channels]) {
            if (Database::scalar('SELECT id FROM plans WHERE `key` = ?', [$key])) {
                continue;
            }
            Database::insert('plans', [
                'key'                     => $key,
                'name'                    => $names['tr'],
                'description'             => null,
                'price'                   => $price,
                'currency'                => 'TRY',
                'billing_interval'        => $interval,
                'trial_days'              => $key === 'free' ? 0 : 14,
                'max_vehicles'            => $maxVehicles,
                'max_users'               => $maxUsers,
                'max_documents_per_vehicle' => $maxDocs,
                'channels'                => $channels,
                'features_json'           => json_encode(['labels' => $names], JSON_UNESCAPED_UNICODE),
                'is_active'               => 1,
                'sort_order'              => $sort,
                'created_at'              => date('Y-m-d H:i:s'),
            ]);
            $sort += 10;
        }
    }
}
