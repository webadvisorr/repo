-- Eski tek/aylık paket kalıntılarını temizler; yalnızca iki paket satışta kalır:
-- trial (6 ay ücretsiz) ve standard (tek seferlik 490 TL).
-- Aboneliği olan eski planlar silinmez, yalnızca satıştan kaldırılır.

UPDATE plans SET is_active = 0
WHERE `key` IN ('free', 'family', 'pro', 'fleet', 'business', 'premium_monthly');

DELETE p FROM plans p
LEFT JOIN subscriptions s ON s.plan_id = p.id
WHERE p.`key` IN ('free', 'family', 'pro', 'fleet', 'business', 'premium_monthly')
  AND s.id IS NULL;

UPDATE plans SET is_active = 1 WHERE `key` IN ('trial', 'standard');
