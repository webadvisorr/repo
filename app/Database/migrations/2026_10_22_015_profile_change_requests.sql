-- ------------------------------------------------------------
--  Profil bilgisi değişiklik talepleri
--  2026_10_22_015
--  Müşteri profilini doğrudan değiştiremez: mevcut şifresiyle
--  talep oluşturur, yönetici onayladıktan sonra uygulanır.
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS profile_change_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  changes_json TEXT NOT NULL,
  status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  review_note VARCHAR(250) NULL,
  reviewed_by INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  KEY idx_profile_req_status (status, created_at),
  KEY idx_profile_req_user (user_id, status),
  CONSTRAINT fk_profile_req_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_profile_req_admin FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
