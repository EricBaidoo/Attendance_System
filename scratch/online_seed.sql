-- =========================================================
-- One-shot seed for u420775839_bmi (online)
-- Generated: 2026-05-02 from local attendance_system
--
-- Pushes:
--   1. 16 local-only system_settings (SMS templates, SMS gateway config, ministerial statuses, branding logo)
--   2. UPDATE for 3 settings that already exist online but with default values (institution_logo, tithe_format_*)
--   3. 3 tithe_books rows (currently empty online)
--   4. 3 tithers rows (currently empty online)
--
-- Safe to run on a database that has already had db_updates.sql applied
-- (i.e. system_settings.category is VARCHAR(50), tithers has tither_type/age_group/company_tin columns).
-- Run db_updates.sql / apply_updates.php FIRST.
--
-- Uses INSERT ... ON DUPLICATE KEY UPDATE so re-running is safe (idempotent).
-- =========================================================

START TRANSACTION;

-- ---------------------------------------------------------
-- 1. Push local-only system_settings (all 16 keys not on online)
-- ---------------------------------------------------------
INSERT INTO system_settings (setting_key, setting_value, category, description) VALUES
('institution_logo',              'assets/images/logo_1777001018.png',                                                                                                            'branding',      NULL),
('ministerial_statuses',          'Levite,Shepherd,Minister,Junior Pastor,Senior Pastor,General Overseer',                                                                        'communication', 'Comma-separated list of recognized ministerial statuses'),
('sms_api_key',                   '',                                                                                                                                              'communication', 'API Key for the SMS Gateway'),
('sms_batch_size',                '40',                                                                                                                                            'communication', 'Maximum number of SMS messages to process in a single batch script execution.'),
('sms_batch_time_limit',          '18',                                                                                                                                            'communication', 'Time limit in seconds for a single SMS batch run to prevent server timeout/lockup.'),
('sms_currency',                  'GHS',                                                                                                                                           'communication', 'Currency symbol for SMS costs'),
('sms_provider',                  'bulksmsgh',                                                                                                                                     'communication', 'SMS Gateway Provider (bulksmsgh, twilio, none)'),
('sms_sender_id',                 'BRIDGE MIN.',                                                                                                                                   'communication', 'Default Sender ID (max 11 chars)'),
('sms_template_birthday',         'Dear [FIRST_NAME], Bridge Ministries wishes you a happy birthday! May God grant you your heart desires and bless your new age. Enjoy your day!', 'communication', 'SMS template for daily birthday greetings. Use [FIRST_NAME] for personalization.'),
('sms_template_member_welcome',   'Welcome to the Family, [FIRST_NAME]! You are now officially a Full Member of Bridge Ministries. We are excited to grow together in Christ. Stay blessed!', 'communication', 'SMS template for new member welcomes.'),
('sms_template_tithe_receipt',    'Dear [FIRST_NAME], thank you for your Tithe of GHS [AMOUNT]. We pray for God''s divine provision and blessings upon your life. Stay blessed.',  'communication', 'SMS template for tithe acknowledgements.'),
('sms_template_visitor_welcome',  'Dear [FIRST_NAME], we were honored to have you at Bridge Ministries. Thank you for visiting! We hope you felt the love of God. Our team will reach out to you soon.', 'communication', 'SMS template for visitor follow-ups.'),
('sms_template_welfare_followup', 'Dear [FIRST_NAME], we missed you at Bridge Ministries recently. We hope you are doing well and we are praying for you. Hope to see you soon! Stay blessed.', 'communication', 'SMS template for welfare follow-ups (absentees).'),
('sms_unit_cost',                 '0.02',                                                                                                                                          'communication', 'Cost per SMS unit in local currency'),
('tithe_format_company',          'BMI-CORP{SEQ}-{YY}',                                                                                                                            '',              'Format for corporate tithers.'),
('tithe_format_member',           'BMI-MEM{SEQ}-{YY}',                                                                                                                             '',              'Format for individual tithers. {SEQ} is number, {YY} is year')
ON DUPLICATE KEY UPDATE
  setting_value = VALUES(setting_value),
  category      = VALUES(category),
  description   = VALUES(description);


-- ---------------------------------------------------------
-- 3. Tithe books (online has 0 rows; using exact local IDs to preserve tithers FK)
-- ---------------------------------------------------------
INSERT INTO tithe_books (id, book_number, issued_date, status, notes, created_at, updated_at) VALUES
(8,  'BMI0001-26',      '2026-04-24', 'assigned', NULL, '2026-04-24 02:24:04', '2026-04-24 02:24:04'),
(9,  'BMI-CORP0001-26', '2026-04-24', 'assigned', NULL, '2026-04-24 02:35:17', '2026-04-24 02:35:17'),
(10, 'BMI-MEM0001-26',  '2026-04-24', 'assigned', NULL, '2026-04-24 02:35:51', '2026-04-24 02:35:51')
ON DUPLICATE KEY UPDATE
  book_number = VALUES(book_number),
  issued_date = VALUES(issued_date),
  status      = VALUES(status);


-- ---------------------------------------------------------
-- 4. Tithers (online has 0 rows)
--
-- IMPORTANT: tithers.member_id below references member_roles.id from your LOCAL database.
-- Online member_roles IDs may not match. After running this, verify in the UI that:
--   - "Lord William"   is linked to the right member (was member_id=1 locally)
--   - "Frederica Afful" is linked to the right member (was member_id=2 locally)
-- "E7 TECHNOLOGY" is a company (member_id NULL) so no linkage to verify.
-- If a link is wrong, simply edit the tither in the UI to pick the correct member.
-- ---------------------------------------------------------
INSERT INTO tithers (id, member_id, tither_type, age_group, full_name, phone, email, company_tin, tithe_book_id, status, start_date, notes, created_at, updated_at) VALUES
(7, 1,    'individual', 'adult', 'Lord William',     '0240279748', NULL, NULL, 8,  'active', '2026-04-24', NULL, '2026-04-24 02:24:04', '2026-04-24 02:24:04'),
(8, NULL, 'company',    NULL,    'E7 TECHNOLOGY',    NULL,         NULL, NULL, 9,  'active', '2026-04-24', NULL, '2026-04-24 02:35:17', '2026-04-24 02:35:17'),
(9, 2,    'individual', 'adult', 'Frederica Afful',  NULL,         NULL, NULL, 10, 'active', '2026-04-24', NULL, '2026-04-24 02:35:51', '2026-04-24 02:42:02')
ON DUPLICATE KEY UPDATE
  full_name     = VALUES(full_name),
  phone         = VALUES(phone),
  tither_type   = VALUES(tither_type),
  age_group     = VALUES(age_group),
  tithe_book_id = VALUES(tithe_book_id),
  status        = VALUES(status);


COMMIT;

-- =========================================================
-- Verify (run these after the import to spot-check):
--   SELECT COUNT(*) FROM system_settings;     -- expect 24
--   SELECT COUNT(*) FROM tithe_books;         -- expect 3
--   SELECT COUNT(*) FROM tithers;             -- expect 3
--   SELECT t.full_name, mr.id, p.full_name AS linked_member_name
--     FROM tithers t LEFT JOIN member_roles mr ON mr.id = t.member_id
--                    LEFT JOIN people p ON p.id = mr.person_id;
-- =========================================================
