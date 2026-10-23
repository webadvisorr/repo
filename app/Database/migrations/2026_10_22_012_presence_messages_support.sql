-- ------------------------------------------------------------
--  Canlı ziyaretçi, mesaj yanıtları ve destek talepleri
--  2026_10_22_012_presence_messages_support.sql
-- ------------------------------------------------------------

-- Canlı ziyaretçi sayacı (son 5 dakika içinde sayfa görüntüleyenler).
CREATE TABLE IF NOT EXISTS visitor_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  visitor_key CHAR(40) NOT NULL,
  user_id INT UNSIGNED NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  path VARCHAR(255) NULL,
  first_seen DATETIME NOT NULL,
  last_seen DATETIME NOT NULL,
  UNIQUE KEY uniq_visitor_key (visitor_key),
  KEY idx_visitor_last (last_seen),
  KEY idx_visitor_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- İletişim mesajları giriş yapmış kullanıcıya bağlanır (panelden takip/yanıt).
ALTER TABLE contact_messages ADD COLUMN user_id INT UNSIGNED NULL AFTER id;
ALTER TABLE contact_messages ADD KEY idx_contact_user (user_id);

-- İletişim mesajı yanıtları (müşteri ↔ yönetim).
CREATE TABLE IF NOT EXISTS contact_replies (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  message_id INT UNSIGNED NOT NULL,
  sender ENUM('customer','admin') NOT NULL,
  user_id INT UNSIGNED NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_contact_reply_message (message_id, created_at),
  CONSTRAINT fk_contact_reply_message FOREIGN KEY (message_id) REFERENCES contact_messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Destek talepleri (müşteri paneli + yönetim).
CREATE TABLE IF NOT EXISTS support_tickets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_no VARCHAR(24) NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  organization_id INT UNSIGNED NULL,
  subject VARCHAR(200) NOT NULL,
  category ENUM('technical','billing','documents','account','other') NOT NULL DEFAULT 'other',
  priority ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
  status ENUM('open','answered','closed') NOT NULL DEFAULT 'open',
  last_reply_at DATETIME NULL,
  closed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY uniq_ticket_no (ticket_no),
  KEY idx_ticket_user (user_id, created_at),
  KEY idx_ticket_status (status, updated_at),
  CONSTRAINT fk_ticket_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_ticket_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT UNSIGNED NOT NULL,
  sender ENUM('customer','admin') NOT NULL,
  user_id INT UNSIGNED NULL,
  body TEXT NOT NULL,
  read_by_admin_at DATETIME NULL,
  read_by_customer_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  KEY idx_ticket_message (ticket_id, created_at),
  CONSTRAINT fk_ticket_message_ticket FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
