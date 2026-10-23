-- Ücretsiz deneme paketi aylık/yıllık dönem satmaz: tek (ücretsiz) fiyat modeline geçir.
UPDATE plans
SET billing_interval = 'lifetime',
    price_month = NULL,
    price_year = NULL
WHERE `key` = 'trial';
