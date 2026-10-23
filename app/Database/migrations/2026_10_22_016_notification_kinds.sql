-- ------------------------------------------------------------
--  Bildirim türleri: destek ve mesaj sekmeleri
--  2026_10_22_016
-- ------------------------------------------------------------

ALTER TABLE user_notifications
  MODIFY COLUMN kind ENUM('reminder','announcement','support','message','system') NOT NULL DEFAULT 'announcement';
