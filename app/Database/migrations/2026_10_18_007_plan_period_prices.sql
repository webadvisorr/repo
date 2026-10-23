-- ------------------------------------------------------------
--  Aylık / yıllık paket fiyatları ve seçilen ödeme dönemi
--  2026_10_18_007
-- ------------------------------------------------------------

-- Plan başına iki fiyat: aylık ve yıllık (ömür boyu planlarda `price` kullanılır).
ALTER TABLE plans
    ADD COLUMN price_month DECIMAL(10,2) NULL AFTER price,
    ADD COLUMN price_year DECIMAL(10,2) NULL AFTER price_month;

-- Mevcut planlar: aylık fiyat eski `price` alanından taşınır, yıllık 12 aylık tutar olarak başlar.
UPDATE plans SET price_month = price WHERE billing_interval = 'month' AND price_month IS NULL;
UPDATE plans SET price_year = ROUND(price * 12, 2) WHERE billing_interval = 'month' AND price_year IS NULL;

-- Abonelik hangi dönem için satın alındı? (yenilemede de bu dönem kullanılır)
ALTER TABLE subscriptions
    ADD COLUMN billing_interval ENUM('month','year','lifetime') NOT NULL DEFAULT 'month' AFTER plan_id;

UPDATE subscriptions s
    JOIN plans p ON p.id = s.plan_id
SET s.billing_interval = p.billing_interval;

-- Ödeme talebinde seçilen dönem (havale onayı doğru süreyi uygular)
ALTER TABLE payments
    ADD COLUMN billing_interval ENUM('month','year','lifetime') NULL AFTER plan_id;
