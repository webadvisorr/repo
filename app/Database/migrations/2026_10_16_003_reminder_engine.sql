-- ------------------------------------------------------------
--  Faz 3 — Hatırlatma motoru ve mail şablonları
--  2026_10_16_003
--  - Hatırlatma ayarları (motor aç/kapa, gün, saat, limit, deneme)
--  - Bildirim günlüğü için kanal + tarih indeksi
--  Not: mail_templates kayıtları uygulama tarafında (MailTemplateService)
--  idempotent şekilde oluşturulur; panelden "Şablonlar" açıldığında veya
--  cron çalıştığında eksikler varsayılanla doldurulur.
-- ------------------------------------------------------------

INSERT IGNORE INTO settings (`group`, `key`, `value`, is_json, updated_at) VALUES
 ('reminders', 'reminders.enabled', '1', 0, NOW()),
 ('reminders', 'reminders.offsets', '7,3,1,0', 0, NOW()),
 ('reminders', 'reminders.hour', '8', 0, NOW()),
 ('reminders', 'reminders.daily_limit', '500', 0, NOW()),
 ('reminders', 'reminders.batch', '50', 0, NOW()),
 ('reminders', 'reminders.max_attempts', '3', 0, NOW());

ALTER TABLE notifications ADD KEY idx_notif_channel (channel, created_at);
