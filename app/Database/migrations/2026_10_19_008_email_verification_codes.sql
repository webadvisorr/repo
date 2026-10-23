-- ------------------------------------------------------------
--  E-posta doğrulama kodları (6 hane, 2 dakika geçerli)
--  2026_10_19_008
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS email_verification_codes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_sent_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uniq_evc_user (user_id),
  KEY idx_evc_expiry (expires_at),
  CONSTRAINT fk_evc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bu sürümden önce oluşturulmuş hesaplar mağdur olmasın: mevcut kullanıcılar
-- doğrulanmış sayılır. Yeni kayıtlar email_verified_at = NULL ile başlar.
UPDATE users SET email_verified_at = COALESCE(created_at, NOW()) WHERE email_verified_at IS NULL;
