-- ------------------------------------------------------------
--  Görüntüleme para birimleri (paket fiyatları için kur)
--  2026_10_16_004
--  - Ziyaretçi footer'dan USD/EUR/GBP seçtiğinde paket fiyatları
--    bu kurlarla çevrilerek gösterilir (tahsilat taban birimde kalır).
--  - Değerler başlangıç kurudur (elle güncellenir: panel → Ayarlar → Ödeme).
--  - Kur 0 olan birim seçicide görünmez; bu yüzden varsayılanlar
--    INSERT IGNORE ile yalnızca eksikse yazılır, mevcut değer ezilmez.
-- ------------------------------------------------------------

INSERT IGNORE INTO settings (`group`, `key`, `value`, is_json, updated_at) VALUES
 ('payment', 'payment.rate_usd', '49.5652', 0, NOW()),
 ('payment', 'payment.rate_eur', '55.7087', 0, NOW()),
 ('payment', 'payment.rate_gbp', '66.0590', 0, NOW());
