-- ------------------------------------------------------------
--  KKTC uyarlaması — evrak türü adları
--  2026_10_16_005
--  - "Muayene (Emüasyon)" -> "Muayene"
--  - "Yol Vergisi"        -> "Seyrüsefer"
--  - "İşletme / Kiralama İzni" türü kaldırılır: kayıtlı evrak yoksa silinir,
--    varsa pasife alınır (mevcut evrak kayıtları bozulmasın).
--  Idempotent: tekrar çalıştırılabilir.
-- ------------------------------------------------------------

UPDATE document_types
   SET labels_json = REPLACE(labels_json, 'Muayene (Emüasyon)', 'Muayene')
 WHERE `key` = 'inspection' AND labels_json LIKE '%Emüasyon%';

UPDATE document_types
   SET labels_json = REPLACE(labels_json, 'Yol Vergisi', 'Seyrüsefer')
 WHERE `key` = 'road_tax' AND labels_json LIKE '%Yol Vergisi%';

UPDATE document_types SET is_active = 0 WHERE `key` = 'permit';

DELETE FROM document_types
 WHERE `key` = 'permit'
   AND NOT EXISTS (SELECT 1 FROM vehicle_documents vd WHERE vd.document_type_id = document_types.id);
