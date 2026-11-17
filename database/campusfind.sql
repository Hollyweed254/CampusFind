-- CampusFind schema (MySQL 5.7+/MariaDB 10.3+)
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS payment_events, payments, transaction_reviews, user_reports, admin_audit_logs,
  blocked_users, message_attachments, messages, conversations, student_verifications, verification_otps,
  transactions, email_verifications, user_oauth, moderation_logs, listing_reports, listing_images, listings, categories, users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  phone VARCHAR(20) NULL,
  role ENUM('student','admin') NOT NULL DEFAULT 'student',
  status ENUM('active','deactivated','suspended') NOT NULL DEFAULT 'active',
  email_verified_at DATETIME NULL,
  phone_verified_at DATETIME NULL,
  phone_verified_mode ENUM('sms','dev') NULL,
  university_email VARCHAR(150) NULL,
  university_email_verified_at DATETIME NULL,
  student_verified_at DATETIME NULL,
  trusted_seller_at DATETIME NULL,
  suspended_at DATETIME NULL,
  suspension_reason VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_uni_email (university_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_verifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  sends TINYINT UNSIGNED NOT NULL DEFAULT 1,
  first_sent_at DATETIME NOT NULL,
  last_sent_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  UNIQUE KEY uq_ev_user (user_id),
  CONSTRAINT fk_ev_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_oauth (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  provider VARCHAR(20) NOT NULL,
  provider_user_id VARCHAR(191) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_oauth (provider, provider_user_id),
  CONSTRAINT fk_oauth_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  listing_type ENUM('product','service') NOT NULL,
  description VARCHAR(255) NULL,
  UNIQUE KEY uq_cat (name, listing_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE listings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  seller_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  title VARCHAR(120) NOT NULL,
  description TEXT NOT NULL,
  price DECIMAL(10,2) NULL,
  item_condition ENUM('new','like_new','good','fair') NULL,
  contact_method ENUM('whatsapp','phone','email') NOT NULL,
  contact_value VARCHAR(150) NOT NULL,
  status ENUM('available','sold','closed') NOT NULL DEFAULT 'available',
  moderation_status ENUM('pending','approved','rejected','removed') NOT NULL DEFAULT 'pending',
  inspected_at DATETIME NULL,
  inspected_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_l_seller FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_l_cat FOREIGN KEY (category_id) REFERENCES categories(id),
  CONSTRAINT fk_l_inspector FOREIGN KEY (inspected_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_l_search (moderation_status, status, category_id),
  INDEX idx_l_price (price),
  INDEX idx_l_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE listing_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  listing_id INT UNSIGNED NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  CONSTRAINT fk_i_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE listing_reports (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  listing_id INT UNSIGNED NOT NULL,
  reporter_id INT UNSIGNED NOT NULL,
  reason ENUM('scam','prohibited','misleading','duplicate','other') NOT NULL,
  details VARCHAR(500) NULL,
  status ENUM('open','resolved','dismissed') NOT NULL DEFAULT 'open',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_r_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  CONSTRAINT fk_r_user FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_report (listing_id, reporter_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE moderation_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id INT UNSIGNED NOT NULL,
  listing_id INT UNSIGNED NOT NULL,
  action ENUM('approve','reject','remove','restore','inspect') NOT NULL,
  notes VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_m_admin FOREIGN KEY (admin_id) REFERENCES users(id),
  CONSTRAINT fk_m_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== Verification, trust, chat, transactions and M-Pesa (marketplace v2) =====

CREATE TABLE verification_otps (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  purpose ENUM('phone','university_email') NOT NULL,
  destination VARCHAR(150) NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  sends TINYINT UNSIGNED NOT NULL DEFAULT 1,
  first_sent_at DATETIME NOT NULL,
  last_sent_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  UNIQUE KEY uq_vo_user_purpose (user_id, purpose),
  CONSTRAINT fk_vo_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_verifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  registration_number VARCHAR(60) NOT NULL,
  university VARCHAR(120) NOT NULL,
  evidence_path VARCHAR(255) NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  admin_note VARCHAR(500) NULL,
  reviewed_by INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sv_user (user_id),
  INDEX idx_sv_status (status, created_at),
  CONSTRAINT fk_sv_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_sv_admin FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE conversations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  listing_id INT UNSIGNED NOT NULL,
  buyer_id INT UNSIGNED NOT NULL,
  seller_id INT UNSIGNED NOT NULL,
  last_message_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_conv (listing_id, buyer_id),
  INDEX idx_conv_buyer (buyer_id, last_message_at),
  INDEX idx_conv_seller (seller_id, last_message_at),
  CONSTRAINT fk_conv_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  CONSTRAINT fk_conv_buyer FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_conv_seller FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT UNSIGNED NOT NULL,
  sender_id INT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  read_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_msg_conv (conversation_id, id),
  CONSTRAINT fk_msg_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE message_attachments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  message_id INT UNSIGNED NOT NULL,
  path VARCHAR(255) NOT NULL,
  mime VARCHAR(80) NOT NULL,
  bytes INT UNSIGNED NOT NULL,
  original_name VARCHAR(150) NOT NULL,
  UNIQUE KEY uq_ma_message (message_id),
  CONSTRAINT fk_ma_msg FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE blocked_users (
  blocker_id INT UNSIGNED NOT NULL,
  blocked_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (blocker_id, blocked_id),
  CONSTRAINT fk_bu_blocker FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_bu_blocked FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transactions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  listing_id INT UNSIGNED NOT NULL,
  buyer_id INT UNSIGNED NOT NULL,
  seller_id INT UNSIGNED NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  status ENUM('initiated','awaiting_inspection','awaiting_payment','payment_pending','paid','completed','cancelled','disputed')
         NOT NULL DEFAULT 'initiated',
  payment_method ENUM('direct','mpesa') NOT NULL DEFAULT 'direct',
  buyer_reported_paid_at DATETIME NULL,
  seller_reported_paid_at DATETIME NULL,
  payment_verified_at DATETIME NULL,
  buyer_confirmed_at DATETIME NULL,
  seller_confirmed_at DATETIME NULL,
  completed_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  dispute_reason VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_tx_buyer (buyer_id, status),
  INDEX idx_tx_seller (seller_id, status),
  INDEX idx_tx_listing (listing_id),
  CONSTRAINT fk_tx_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  CONSTRAINT fk_tx_buyer FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_tx_seller FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transaction_id INT UNSIGNED NOT NULL,
  provider VARCHAR(20) NOT NULL DEFAULT 'mpesa',
  environment ENUM('sandbox','production') NOT NULL DEFAULT 'sandbox',
  phone VARCHAR(20) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  merchant_request_id VARCHAR(80) NULL,
  checkout_request_id VARCHAR(80) NULL,
  mpesa_receipt VARCHAR(40) NULL,
  status ENUM('initiated','pending','paid','failed','timeout','cancelled') NOT NULL DEFAULT 'initiated',
  result_code VARCHAR(10) NULL,
  result_desc VARCHAR(255) NULL,
  raw_callback MEDIUMTEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pay_checkout (checkout_request_id),
  UNIQUE KEY uq_pay_receipt (mpesa_receipt),
  INDEX idx_pay_tx (transaction_id),
  CONSTRAINT fk_pay_tx FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payment_id INT UNSIGNED NOT NULL,
  event_key VARCHAR(120) NOT NULL,
  event_type VARCHAR(40) NOT NULL,
  payload MEDIUMTEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pe_key (event_key),
  CONSTRAINT fk_pe_pay FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transaction_reviews (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transaction_id INT UNSIGNED NOT NULL,
  reviewer_id INT UNSIGNED NOT NULL,
  reviewee_id INT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  comment VARCHAR(1000) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_rev_transaction (transaction_id),
  INDEX idx_rev_reviewee (reviewee_id, created_at),
  CONSTRAINT fk_rev_tx FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
  CONSTRAINT fk_rev_reviewer FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_rev_reviewee FOREIGN KEY (reviewee_id) REFERENCES users(id) ON DELETE CASCADE,
  CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_reports (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reporter_id INT UNSIGNED NOT NULL,
  reported_user_id INT UNSIGNED NOT NULL,
  listing_id INT UNSIGNED NULL,
  conversation_id INT UNSIGNED NULL,
  context ENUM('profile','chat','transaction') NOT NULL,
  reason ENUM('scam','harassment','abuse','fake_account','other') NOT NULL,
  details VARCHAR(500) NULL,
  status ENUM('open','resolved','dismissed') NOT NULL DEFAULT 'open',
  admin_note VARCHAR(500) NULL,
  reviewed_by INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ur_status (status, created_at),
  INDEX idx_ur_reported (reported_user_id),
  CONSTRAINT fk_ur_reporter FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ur_reported FOREIGN KEY (reported_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ur_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE SET NULL,
  CONSTRAINT fk_ur_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE SET NULL,
  CONSTRAINT fk_ur_admin FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_audit_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id INT UNSIGNED NOT NULL,
  action VARCHAR(60) NOT NULL,
  entity_type VARCHAR(40) NOT NULL,
  entity_id INT UNSIGNED NULL,
  notes VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_created (created_at),
  CONSTRAINT fk_audit_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO categories (name, listing_type, description) VALUES
('Electronics','product','Phones, laptops, calculators, accessories'),
('Books & Notes','product','Textbooks, past papers, notes'),
('Clothing','product','Clothes, shoes, bags'),
('Furniture & Hostel','product','Beds, mattresses, shelves, kitchenware'),
('Other Items','product','Anything else physical'),
('Tutoring','service','Academic help and revision sessions'),
('Graphic & Web Design','service','Posters, logos, websites'),
('Computer Repair','service','Laptop and phone repair, software install'),
('Printing & Typing','service','Printing, typing, binding'),
('Other Services','service','Any other student service');

-- Default admin: admin@campusfind.local / admin123  (CHANGE BEFORE DEPLOYING)
INSERT INTO users (full_name, email, password_hash, role, email_verified_at) VALUES
('Site Admin','admin@campusfind.local','$2y$10$AzTWhU3HMvc1AfjSAkESU.hgul3j7fWGCVYLx8zVGeKie4UoWpqsi','admin', NOW());
