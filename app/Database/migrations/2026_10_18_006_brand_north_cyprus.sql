-- Marka adresi: sokak/cadde bilgisi yayından kaldırılır, yerine bölge adı yazılır.
UPDATE settings SET value = '' WHERE `key` = 'brand.address';
UPDATE settings SET value = 'Kuzey Kıbrıs' WHERE `key` = 'brand.city';
UPDATE settings SET value = '' WHERE `key` = 'brand.country';
