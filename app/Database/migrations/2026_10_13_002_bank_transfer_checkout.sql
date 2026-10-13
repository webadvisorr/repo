-- ------------------------------------------------------------
--  Havale/EFT ile kendi kendine ödeme (checkout) altyapısı
--  2026_10_13_002
--  Not: ödeme sayfası (iyzico/PayTR) ileride ekleneceği için
--  gateway_token / gateway_url kolonları şimdiden hazır.
-- ------------------------------------------------------------

ALTER TABLE payments
    ADD COLUMN kind VARCHAR(30) NOT NULL DEFAULT 'manual' AFTER method,
    ADD COLUMN reference VARCHAR(40) NULL AFTER kind,
    ADD COLUMN user_id INT UNSIGNED NULL AFTER reference,
    ADD COLUMN plan_id INT UNSIGNED NULL AFTER user_id,
    ADD COLUMN gateway_token VARCHAR(191) NULL AFTER plan_id,
    ADD COLUMN gateway_url VARCHAR(500) NULL AFTER gateway_token,
    ADD COLUMN meta_json TEXT NULL AFTER gateway_url,
    ADD COLUMN expires_at DATETIME NULL AFTER meta_json,
    ADD COLUMN approved_by INT UNSIGNED NULL AFTER expires_at,
    ADD COLUMN applied_at DATETIME NULL AFTER approved_by;

ALTER TABLE payments ADD UNIQUE KEY uniq_pay_ref (reference);
ALTER TABLE payments ADD KEY idx_pay_kind (kind);

CREATE TABLE IF NOT EXISTS payment_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payment_id BIGINT UNSIGNED NOT NULL,
  gateway VARCHAR(30) NOT NULL DEFAULT 'bank_transfer',
  event VARCHAR(60) NOT NULL,
  payload TEXT NULL,
  ip VARCHAR(45) NULL,
  actor_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  KEY idx_pe_payment (payment_id),
  CONSTRAINT fk_pe_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
