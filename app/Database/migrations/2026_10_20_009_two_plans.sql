-- İki paketli yeni fiyat modeli:
--   1) Deneme  : 6 ay ücretsiz, 1 araç
--   2) Standart: tek seferlik 490 TL; 2. araç %20, 3. araç %30, 4. ve sonrası %30 indirimli.
-- Araç sayısı limiti pakette tutulmaz; ek araçların ücreti araç bazlı indirim tablosundan hesaplanır.
-- Muayene, sigorta, kasko, seyrüsefer ve diğer evrakların eklenmesi ücretsizdir.

UPDATE plans SET is_active = 0 WHERE `key` IN ('free', 'family', 'pro', 'fleet');

DELETE p FROM plans p
LEFT JOIN subscriptions s ON s.plan_id = p.id
WHERE p.`key` IN ('free', 'family', 'pro', 'fleet') AND s.id IS NULL;

INSERT INTO plans
  (`key`, name, description, price, price_month, price_year, currency, billing_interval,
   trial_days, max_vehicles, max_users, max_documents_per_vehicle, channels, features_json,
   is_active, sort_order, created_at)
VALUES
  ('trial', '6 Ay Ücretsiz Deneme',
   'Tek araçla tüm özellikleri 6 ay boyunca ücretsiz deneyin.',
   0, NULL, NULL, 'TRY', 'month',
   180, 1, 1, 200, 'email',
   '{"labels":{"tr":"6 Ay Ücretsiz Deneme","en":"6-Month Free Trial","ru":"6 месяцев бесплатно","ar":"تجربة مجانية 6 أشهر","fa":"۶ ماه رایگان","el":"6 μήνες δωρεάν"},"bullets":["Tüm evrak türleri ücretsiz","Muayene, sigorta, kasko ve seyrüsefer takibi","E-posta hatırlatmaları","Tek araç"]}',
   1, 10, NOW()),
  ('standard', 'Standart Paket',
   'Tek seferlik 490 TL. Ek araçlarda %20–%30 indirim.',
   490, NULL, NULL, 'TRY', 'lifetime',
   0, 999, 5, 200, 'email',
   '{"labels":{"tr":"Standart Paket","en":"Standard Plan","ru":"Стандарт","ar":"الباقة القياسية","fa":"بسته استاندارد","el":"Βασικό πακέτο"},"bullets":["İlk araç 490 TL (tek seferlik)","2. araç %20 indirimli: 392 TL","3. araç %30 indirimli: 343 TL","4. ve sonraki araçlar %30 indirimli: 343 TL","Tüm evraklar ücretsiz","Süresiz kullanım"]}',
   1, 20, NOW())
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  description = VALUES(description),
  price = VALUES(price),
  price_month = VALUES(price_month),
  price_year = VALUES(price_year),
  currency = VALUES(currency),
  billing_interval = VALUES(billing_interval),
  trial_days = VALUES(trial_days),
  max_vehicles = VALUES(max_vehicles),
  max_users = VALUES(max_users),
  max_documents_per_vehicle = VALUES(max_documents_per_vehicle),
  channels = VALUES(channels),
  features_json = VALUES(features_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);
