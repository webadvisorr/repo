-- ------------------------------------------------------------
--  Site içi bildirim merkezi (zil + bildirimler sayfası)
--  2026_10_22_011
--  - inbox_broadcasts : panelden gönderilen özel duyurular (resim/metin/link)
--  - user_notifications: kullanıcı başına bildirim kaydı (hatırlatma + duyuru)
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS inbox_broadcasts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(160) NOT NULL,
  body TEXT NULL,
  image_media_id INT UNSIGNED NULL,
  image_path VARCHAR(255) NULL,
  link_url VARCHAR(500) NULL,
  link_label VARCHAR(80) NULL,
  audience_json TEXT NULL,
  recipient_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  sent_at DATETIME NULL,
  KEY idx_inbox_broadcast_sent (sent_at),
  CONSTRAINT fk_inbox_broadcast_media FOREIGN KEY (image_media_id) REFERENCES media(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  organization_id INT UNSIGNED NULL,
  kind ENUM('reminder','announcement','system') NOT NULL DEFAULT 'announcement',
  broadcast_id INT UNSIGNED NULL,
  vehicle_id INT UNSIGNED NULL,
  document_id INT UNSIGNED NULL,
  title VARCHAR(160) NOT NULL,
  body TEXT NULL,
  image_path VARCHAR(255) NULL,
  link_url VARCHAR(500) NULL,
  link_label VARCHAR(80) NULL,
  dedupe_key VARCHAR(160) NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uniq_user_notif_dedupe (dedupe_key),
  KEY idx_user_notif_user (user_id, created_at),
  KEY idx_user_notif_unread (user_id, read_at),
  KEY idx_user_notif_broadcast (broadcast_id),
  CONSTRAINT fk_user_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_notif_broadcast FOREIGN KEY (broadcast_id) REFERENCES inbox_broadcasts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
