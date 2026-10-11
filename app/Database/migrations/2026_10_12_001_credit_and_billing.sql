-- ------------------------------------------------------------
--  Kredi bakiyesi, kredi hareket defteri ve ödeme/abonelik alanları
--  2026_10_12_001
-- ------------------------------------------------------------

-- Kurum (tenant) kredi bakiyesi
ALTER TABLE organizations
    ADD COLUMN credit_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER country;

-- Kredi hareket defteri: her ekleme/çıkarma kayıt altında (izlenebilir)
CREATE TABLE IF NOT EXISTS credit_ledger (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,                     -- işlemi yaptıran/yararlanan kullanıcı
    admin_user_id INT UNSIGNED NULL,               -- panelden ekleyen yönetici
    direction ENUM('credit','debit') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    balance_after DECIMAL(10,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'TRY',
    reason VARCHAR(60) NOT NULL DEFAULT 'manual',
    note VARCHAR(255) NULL,
    reference VARCHAR(120) NULL,                   -- sipariş/ödeme referansı
    created_at DATETIME NOT NULL,
    KEY idx_ledger_org (organization_id, created_at),
    KEY idx_ledger_reason (reason),
    CONSTRAINT fk_ledger_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_ledger_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ödeme kaydı: yöntem ve kaydı giren kişi
ALTER TABLE payments
    ADD COLUMN method VARCHAR(30) NOT NULL DEFAULT 'manual' AFTER provider,
    ADD COLUMN recorded_by INT UNSIGNED NULL AFTER method,
    ADD COLUMN notes VARCHAR(255) NULL AFTER provider_raw;

-- Abonelik: atayan yönetici + not + kredi ile ödeme izi
ALTER TABLE subscriptions
    ADD COLUMN assigned_by INT UNSIGNED NULL AFTER provider_ref,
    ADD COLUMN notes VARCHAR(255) NULL AFTER cancel_at_period_end,
    ADD COLUMN price_at_signup DECIMAL(10,2) NULL AFTER notes;

-- Uygulanan migration kayıtları (SSH'sız güncelleme takibi)
CREATE TABLE IF NOT EXISTS migrations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(190) NOT NULL,
    applied_at DATETIME NOT NULL,
    UNIQUE KEY uniq_migration (filename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
