-- ------------------------------------------------------------
--  Cihaz bildirimleri (Web Push / VAPID)
--  2026_10_22_013
--  - push_subscriptions : kullanıcının tarayıcı/cihaz abonelikleri
--  - push_queue         : cihazlara gönderilecek bildirim kuyruğu
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS push_subscriptions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  endpoint_hash CHAR(64) NOT NULL,
  endpoint TEXT NOT NULL,
  p256dh VARCHAR(255) NOT NULL,
  auth VARCHAR(64) NOT NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  last_used_at DATETIME NULL,
  last_error VARCHAR(255) NULL,
  UNIQUE KEY uniq_push_endpoint (endpoint_hash),
  KEY idx_push_user (user_id),
  CONSTRAINT fk_push_sub_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_queue (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL,
  body TEXT NULL,
  url VARCHAR(500) NULL,
  icon VARCHAR(500) NULL,
  image VARCHAR(500) NULL,
  tag VARCHAR(80) NULL,
  status ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  error VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  sent_at DATETIME NULL,
  KEY idx_push_queue_status (status, id),
  KEY idx_push_queue_user (user_id),
  CONSTRAINT fk_push_queue_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
