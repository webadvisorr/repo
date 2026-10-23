-- ------------------------------------------------------------
--  Marka adı: "Evrako KKTC" → "Evrako"
--  2026_10_22_014
--  Yalnızca eski marka adını taşıyan kaydı günceller; panelden
--  özelleştirilmiş farklı bir ad varsa dokunmaz.
-- ------------------------------------------------------------

UPDATE settings
SET `value` = 'Evrako'
WHERE `key` = 'brand.title'
  AND `value` LIKE '%KKTC%';
