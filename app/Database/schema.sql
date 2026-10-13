-- ============================================================
--  evrako — Araç Evrak Takip Sistemi (Kıbrıs)
--  MySQL 5.7+ / MariaDB 10.2+  •  utf8mb4
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------- Ayarlar / site kimliği ----------
CREATE TABLE IF NOT EXISTS settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `group` VARCHAR(40) NOT NULL DEFAULT 'general',
  `key` VARCHAR(80) NOT NULL,
  `value` LONGTEXT NULL,
  is_json TINYINT(1) NOT NULL DEFAULT 0,
  updated_by INT UNSIGNED NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY uniq_settings_key (`key`),
  KEY idx_settings_group (`group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Medya (logo, favicon, preloader, slider, belge dosyaları) ----------
CREATE TABLE IF NOT EXISTS media (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kind VARCHAR(30) NOT NULL DEFAULT 'generic',       -- logo,favicon,preloader,slide,og,document,avatar
  disk_path VARCHAR(255) NOT NULL,                   -- storage-relative
  original_name VARCHAR(255) NULL,
  mime VARCHAR(120) NULL,
  extension VARCHAR(12) NULL,
  size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
  width INT UNSIGNED NULL,
  height INT UNSIGNED NULL,
  alt_text VARCHAR(255) NULL,
  uploaded_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  KEY idx_media_kind (kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Kullanıcılar ----------
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(32) NULL,
  whatsapp VARCHAR(32) NULL,
  telegram_chat_id VARCHAR(64) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','editor','customer') NOT NULL DEFAULT 'customer',
  status ENUM('active','passive','suspended') NOT NULL DEFAULT 'active',
  locale VARCHAR(5) NOT NULL DEFAULT 'tr',
  theme VARCHAR(10) NOT NULL DEFAULT 'system',
  timezone VARCHAR(48) NOT NULL DEFAULT 'Europe/Nicosia',
  email_verified_at DATETIME NULL,
  twofa_secret VARCHAR(64) NULL,
  twofa_enabled TINYINT(1) NOT NULL DEFAULT 0,
  failed_logins SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  remember_token VARCHAR(64) NULL,
  notify_email TINYINT(1) NOT NULL DEFAULT 1,
  notify_sms TINYINT(1) NOT NULL DEFAULT 0,
  notify_whatsapp TINYINT(1) NOT NULL DEFAULT 0,
  notify_telegram TINYINT(1) NOT NULL DEFAULT 0,
  notify_push TINYINT(1) NOT NULL DEFAULT 0,
  marketing_opt_in TINYINT(1) NOT NULL DEFAULT 1,
  marketing_opt_in_at DATETIME NULL,
  reminder_offsets VARCHAR(60) NOT NULL DEFAULT '7,3,1,0',
  last_login_at DATETIME NULL,
  last_login_ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  deleted_at DATETIME NULL,
  UNIQUE KEY uniq_users_email (email),
  KEY idx_users_role (role),
  KEY idx_users_remember (remember_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Oturum / token tabloları ----------
CREATE TABLE IF NOT EXISTS auth_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  type ENUM('email_verify','password_reset','invite','login_link') NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uniq_token (token_hash),
  KEY idx_token_user (user_id, type),
  CONSTRAINT fk_token_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NULL,
  ip VARCHAR(45) NOT NULL,
  user_agent VARCHAR(255) NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  KEY idx_attempt_ip (ip, created_at),
  KEY idx_attempt_email (email, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Kurum / filo (çoklu kullanıcı) ----------
CREATE TABLE IF NOT EXISTS organizations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_user_id INT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  type ENUM('individual','business') NOT NULL DEFAULT 'individual',
  tax_number VARCHAR(32) NULL,
  phone VARCHAR(32) NULL,
  address VARCHAR(255) NULL,
  city VARCHAR(80) NULL,
  country VARCHAR(80) NOT NULL DEFAULT 'CY',
  credit_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  KEY idx_org_owner (owner_user_id),
  CONSTRAINT fk_org_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS organization_members (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  invited_email VARCHAR(190) NULL,
  role ENUM('owner','manager','viewer') NOT NULL DEFAULT 'viewer',
  can_manage_vehicles TINYINT(1) NOT NULL DEFAULT 0,
  can_manage_documents TINYINT(1) NOT NULL DEFAULT 0,
  can_manage_billing TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','invited','removed') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL,
  UNIQUE KEY uniq_member (organization_id, user_id),
  KEY idx_member_user (user_id),
  CONSTRAINT fk_member_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_member_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Planlar / abonelik / ödeme ----------
CREATE TABLE IF NOT EXISTS plans (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(40) NOT NULL,
  name VARCHAR(80) NOT NULL,
  description VARCHAR(255) NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL DEFAULT 'TRY',
  billing_interval ENUM('month','year','lifetime') NOT NULL DEFAULT 'month',
  trial_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  max_vehicles INT NOT NULL DEFAULT 3,
  max_users SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  max_documents_per_vehicle SMALLINT UNSIGNED NOT NULL DEFAULT 20,
  channels VARCHAR(80) NOT NULL DEFAULT 'email',
  features_json TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uniq_plan_key (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscriptions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  plan_id INT UNSIGNED NOT NULL,
  status ENUM('trialing','active','past_due','cancelled','expired') NOT NULL DEFAULT 'trialing',
  provider VARCHAR(30) NOT NULL DEFAULT 'manual',    -- manual, iyzico, paytr, bank_transfer
  provider_ref VARCHAR(120) NULL,                    -- abonelik/kart token referansı
  assigned_by INT UNSIGNED NULL,                     -- panelden atayan yönetici
  current_period_start DATETIME NULL,
  current_period_end DATETIME NULL,
  trial_ends_at DATETIME NULL,
  cancel_at_period_end TINYINT(1) NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  price_at_signup DECIMAL(10,2) NULL,
  cancelled_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  KEY idx_sub_org (organization_id),
  KEY idx_sub_status (status),
  CONSTRAINT fk_sub_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_sub_plan FOREIGN KEY (plan_id) REFERENCES plans(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  subscription_id INT UNSIGNED NULL,
  amount DECIMAL(10,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'TRY',
  status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  provider VARCHAR(30) NOT NULL DEFAULT 'manual',
  method VARCHAR(30) NOT NULL DEFAULT 'manual',
  kind VARCHAR(30) NOT NULL DEFAULT 'manual',            -- credit | subscription | manual
  reference VARCHAR(40) NULL,                            -- musteriye gosterilen odeme kodu (HV-2026-0001)
  user_id INT UNSIGNED NULL,                             -- odemeyi baslatan kullanici
  plan_id INT UNSIGNED NULL,                             -- abonelik satin aliminda plan
  gateway_token VARCHAR(191) NULL,                       -- odeme sayfasi saglayici tokeni (ileride iyzico/paytr)
  gateway_url VARCHAR(500) NULL,                         -- odeme sayfasi adresi (ileride)
  meta_json TEXT NULL,                                   -- havale bildirimi vb. ek alanlar
  expires_at DATETIME NULL,
  approved_by INT UNSIGNED NULL,                         -- havaleyi onaylayan yonetici
  applied_at DATETIME NULL,                              -- kredi/abonelik uygulanma ani
  recorded_by INT UNSIGNED NULL,
  provider_txn_id VARCHAR(120) NULL,
  provider_raw TEXT NULL,
  notes VARCHAR(255) NULL,
  paid_at DATETIME NULL,
  period_start DATETIME NULL,
  period_end DATETIME NULL,
  created_at DATETIME NOT NULL,
  KEY idx_pay_org (organization_id),
  KEY idx_pay_status (status),
  KEY idx_pay_kind (kind),
  UNIQUE KEY uniq_pay_ref (reference),
  CONSTRAINT fk_pay_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Odeme olay gunlugu: havale bildirimi, onay/red, saglayici cagrilari (izlenebilirlik)
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

-- ---------- Araç türleri / evrak türleri ----------
CREATE TABLE IF NOT EXISTS vehicle_types (
  id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(40) NOT NULL,
  labels_json TEXT NOT NULL,                          -- {"tr":"Otomobil","en":"Car",...}
  icon VARCHAR(60) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  UNIQUE KEY uniq_vtype_key (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_types (
  id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(40) NOT NULL,
  labels_json TEXT NOT NULL,
  default_validity_months SMALLINT UNSIGNED NOT NULL DEFAULT 12,
  default_offsets VARCHAR(60) NOT NULL DEFAULT '7,3,1,0',
  color CHAR(7) NOT NULL DEFAULT '#3B82F6',
  requires_file TINYINT(1) NOT NULL DEFAULT 0,
  applies_vehicle_types VARCHAR(255) NULL,            -- NULL = hepsi
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  UNIQUE KEY uniq_dtype_key (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Araçlar ----------
CREATE TABLE IF NOT EXISTS vehicles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  vehicle_type_id SMALLINT UNSIGNED NOT NULL,
  plate VARCHAR(24) NOT NULL,
  brand VARCHAR(80) NULL,
  model VARCHAR(80) NULL,
  model_year SMALLINT UNSIGNED NULL,
  vin VARCHAR(40) NULL,
  engine_no VARCHAR(40) NULL,
  color VARCHAR(40) NULL,
  odometer INT UNSIGNED NULL,
  driver_name VARCHAR(120) NULL,
  notes TEXT NULL,
  status ENUM('active','sold','archived') NOT NULL DEFAULT 'active',
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  deleted_at DATETIME NULL,
  UNIQUE KEY uniq_org_plate (organization_id, plate),
  KEY idx_vehicle_org (organization_id, status),
  CONSTRAINT fk_vehicle_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_vehicle_type FOREIGN KEY (vehicle_type_id) REFERENCES vehicle_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Evraklar ----------
CREATE TABLE IF NOT EXISTS vehicle_documents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vehicle_id INT UNSIGNED NOT NULL,
  document_type_id SMALLINT UNSIGNED NOT NULL,
  issuer VARCHAR(160) NULL,                           -- sigorta şirketi / kurum
  policy_no VARCHAR(80) NULL,
  start_date DATE NULL,
  end_date DATE NOT NULL,
  cost DECIMAL(10,2) NULL,
  currency CHAR(3) NOT NULL DEFAULT 'TRY',
  reminder_offsets VARCHAR(60) NULL,                  -- NULL = kullanıcı varsayılanı
  notes TEXT NULL,
  status ENUM('active','renewed','cancelled') NOT NULL DEFAULT 'active',
  renewed_from_id INT UNSIGNED NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  deleted_at DATETIME NULL,
  KEY idx_doc_vehicle (vehicle_id, status),
  KEY idx_doc_end (end_date, status),
  CONSTRAINT fk_doc_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
  CONSTRAINT fk_doc_type FOREIGN KEY (document_type_id) REFERENCES document_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_files (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_id INT UNSIGNED NOT NULL,
  media_id INT UNSIGNED NOT NULL,
  label VARCHAR(120) NULL,
  created_at DATETIME NOT NULL,
  KEY idx_docfile_doc (document_id),
  CONSTRAINT fk_docfile_doc FOREIGN KEY (document_id) REFERENCES vehicle_documents(id) ON DELETE CASCADE,
  CONSTRAINT fk_docfile_media FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Bildirimler (mail/sms/whatsapp/telegram/push) ----------
CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  organization_id INT UNSIGNED NULL,
  vehicle_id INT UNSIGNED NULL,
  document_id INT UNSIGNED NULL,
  channel ENUM('email','sms','whatsapp','telegram','push') NOT NULL DEFAULT 'email',
  template_key VARCHAR(60) NOT NULL,
  recipient VARCHAR(190) NOT NULL,
  subject VARCHAR(255) NULL,
  subject_rendered VARCHAR(255) NULL,
  body_html LONGTEXT NULL,
  status ENUM('queued','sent','failed','skipped','cancelled') NOT NULL DEFAULT 'queued',
  dedupe_key VARCHAR(120) NOT NULL,                   -- user:doc:offset:date  (tekrar gönderimi engeller)
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  error TEXT NULL,
  provider_ref VARCHAR(120) NULL,
  scheduled_for DATETIME NULL,
  sent_at DATETIME NULL,
  opened_at DATETIME NULL,
  clicked_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uniq_notif_dedupe (dedupe_key),
  KEY idx_notif_status (status, scheduled_for),
  KEY idx_notif_user (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Mail şablonları ----------
CREATE TABLE IF NOT EXISTS mail_templates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(60) NOT NULL,
  locale VARCHAR(5) NOT NULL DEFAULT 'tr',
  name VARCHAR(120) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  body_html LONGTEXT NOT NULL,
  body_text LONGTEXT NULL,
  variables_json TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY uniq_tpl_key_locale (`key`, locale)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Kampanya / reklam maili ----------
CREATE TABLE IF NOT EXISTS campaigns (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  template_id INT UNSIGNED NULL,
  body_html LONGTEXT NULL,
  audience_json TEXT NULL,                            -- {"plan":"pro","only_marketing_opt_in":true}
  recipient_count INT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('draft','scheduled','sending','sent','cancelled') NOT NULL DEFAULT 'draft',
  scheduled_at DATETIME NULL,
  started_at DATETIME NULL,
  finished_at DATETIME NULL,
  sent_count INT UNSIGNED NOT NULL DEFAULT 0,
  failed_count INT UNSIGNED NOT NULL DEFAULT 0,
  open_count INT UNSIGNED NOT NULL DEFAULT 0,
  click_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaign_recipients (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  campaign_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  email VARCHAR(190) NOT NULL,
  status ENUM('pending','sent','failed','unsubscribed') NOT NULL DEFAULT 'pending',
  error VARCHAR(255) NULL,
  sent_at DATETIME NULL,
  opened_at DATETIME NULL,
  clicked_at DATETIME NULL,
  UNIQUE KEY uniq_camp_user (campaign_id, user_id),
  KEY idx_camp_status (campaign_id, status),
  CONSTRAINT fk_camp_recip_camp FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
  CONSTRAINT fk_camp_recip_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS unsubscribes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  reason VARCHAR(190) NULL,
  scope ENUM('marketing','all') NOT NULL DEFAULT 'marketing',
  created_at DATETIME NOT NULL,
  UNIQUE KEY uniq_unsub_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- İçerik: slider, bölümler, sayfalar, SSS ----------
CREATE TABLE IF NOT EXISTS slides (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  media_id INT UNSIGNED NULL,
  image_path VARCHAR(255) NULL,
  title VARCHAR(200) NULL,
  subtitle VARCHAR(255) NULL,
  description TEXT NULL,
  button_text VARCHAR(80) NULL,
  button_url VARCHAR(255) NULL,
  button_target ENUM('_self','_blank') NOT NULL DEFAULT '_self',
  text_position ENUM('left','center','right') NOT NULL DEFAULT 'left',
  overlay_opacity TINYINT UNSIGNED NOT NULL DEFAULT 35,
  locale VARCHAR(5) NULL,                             -- NULL = tüm diller
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_blocks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(60) NOT NULL,                         -- how_it_works, how_to_register, ...
  locale VARCHAR(5) NOT NULL DEFAULT 'tr',
  title VARCHAR(200) NOT NULL,
  body_html LONGTEXT NULL,
  image_path VARCHAR(255) NULL,
  steps_json TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  updated_at DATETIME NULL,
  UNIQUE KEY uniq_block (`key`, locale)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS faqs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  locale VARCHAR(5) NOT NULL DEFAULT 'tr',
  question VARCHAR(255) NOT NULL,
  answer TEXT NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order SMALLINT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS posts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(190) NOT NULL,
  locale VARCHAR(5) NOT NULL DEFAULT 'tr',
  title VARCHAR(200) NOT NULL,
  excerpt VARCHAR(400) NULL,
  body_html LONGTEXT NULL,
  cover_path VARCHAR(255) NULL,
  status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  views INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY uniq_post_slug (slug, locale)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- İletişim / geri bildirim ----------
CREATE TABLE IF NOT EXISTS contact_messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(32) NULL,
  subject VARCHAR(200) NULL,
  message TEXT NOT NULL,
  ip VARCHAR(45) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Kredi bakiyesi ve hareket defteri ----------
CREATE TABLE IF NOT EXISTS credit_ledger (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  admin_user_id INT UNSIGNED NULL,
  direction ENUM('credit','debit') NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  balance_after DECIMAL(10,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'TRY',
  reason VARCHAR(60) NOT NULL DEFAULT 'manual',
  note VARCHAR(255) NULL,
  reference VARCHAR(120) NULL,
  created_at DATETIME NOT NULL,
  KEY idx_ledger_org (organization_id, created_at),
  CONSTRAINT fk_ledger_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_ledger_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Uygulanan migration kayitlari ----------
CREATE TABLE IF NOT EXISTS migrations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  filename VARCHAR(190) NOT NULL,
  applied_at DATETIME NOT NULL,
  UNIQUE KEY uniq_migration (filename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Denetim kaydı ----------
CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(60) NOT NULL,
  entity VARCHAR(60) NULL,
  entity_id VARCHAR(40) NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  meta_json TEXT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_audit_user (user_id, created_at),
  KEY idx_audit_entity (entity, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Zamanlanmış işler / kuyruk ----------
CREATE TABLE IF NOT EXISTS jobs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  queue VARCHAR(40) NOT NULL DEFAULT 'default',
  job_type VARCHAR(60) NOT NULL,
  payload_json TEXT NULL,
  status ENUM('pending','running','done','failed') NOT NULL DEFAULT 'pending',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  available_at DATETIME NOT NULL,
  reserved_at DATETIME NULL,
  finished_at DATETIME NULL,
  last_error TEXT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_jobs_pick (status, available_at),
  KEY idx_jobs_type (job_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
