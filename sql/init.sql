-- Telegram Mini App System SQL Schema

CREATE DATABASE IF NOT EXISTS telegram_bot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE telegram_bot;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  telegram_id BIGINT UNSIGNED NOT NULL UNIQUE,
  username VARCHAR(255) NOT NULL,
  membership_type ENUM('free','vip') NOT NULL DEFAULT 'free',
  membership_expire DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS services (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  description TEXT NOT NULL,
  api_url VARCHAR(255) NOT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  is_vip TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  telegram_id BIGINT UNSIGNED NOT NULL,
  service_id INT UNSIGNED NOT NULL,
  request_payload JSON NOT NULL,
  response_payload JSON NOT NULL,
  type ENUM('query','admin','auth') NOT NULL DEFAULT 'query',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX ix_user_id (user_id),
  INDEX ix_service_id (service_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_tokens (
  token VARCHAR(64) PRIMARY KEY,
  telegram_id BIGINT UNSIGNED NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX ix_telegram_id (telegram_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO services (name, description, api_url, price, is_vip, status)
VALUES
  ('TC Kimlik Sorgu', 'TC kimlik numarası ile demo sorgu yapar.', 'https://httpbin.org/get?query={input}', 5.00, 0, 'active'),
  ('VIP Numara Sorgu', 'VIP kullanıcılar için özel numara sorgulama servisi.', 'https://httpbin.org/get?vip={input}', 25.00, 1, 'active');

-- Admin kullanıcısı için sample. ADMIN_TELEGRAM_ID environment değişkeni burada kullanılacak.
-- Bu örnekte 123456789 olarak bırakılmıştır.
INSERT IGNORE INTO users (telegram_id, username, membership_type, membership_expire, created_at)
VALUES (123456789, 'admin', 'vip', DATE_ADD(NOW(), INTERVAL 365 DAY), NOW());
