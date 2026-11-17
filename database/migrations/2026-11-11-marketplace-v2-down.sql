-- DESTRUCTIVE ROLLBACK for 2026-11-11-marketplace-v2.sql
--
-- This removes every table and column added by the v2 migration, including
-- verification requests, conversations, messages, transactions, payments and
-- audit history. Make a full database backup first and only run it when you
-- intentionally want to undo the whole feature set.
--
--   mysqldump -u root campusfind > campusfind-before-rollback.sql
--   mysql -u root campusfind < database/migrations/2026-11-11-marketplace-v2-down.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS
  payment_events,
  payments,
  transaction_reviews,
  user_reports,
  admin_audit_logs,
  blocked_users,
  message_attachments,
  messages,
  conversations,
  student_verifications,
  verification_otps,
  transactions;

SET FOREIGN_KEY_CHECKS = 1;

-- The old status enum has no 'suspended' value, so suspended accounts are
-- mapped to 'deactivated' before the column is narrowed.
UPDATE users SET status = 'deactivated' WHERE status = 'suspended';

ALTER TABLE users
  DROP INDEX uq_users_uni_email,
  DROP COLUMN suspension_reason,
  DROP COLUMN suspended_at,
  DROP COLUMN trusted_seller_at,
  DROP COLUMN student_verified_at,
  DROP COLUMN university_email_verified_at,
  DROP COLUMN university_email,
  DROP COLUMN phone_verified_mode,
  DROP COLUMN phone_verified_at,
  MODIFY status ENUM('active','deactivated') NOT NULL DEFAULT 'active';
