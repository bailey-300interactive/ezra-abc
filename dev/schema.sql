-- Ezra ABC database tables. Applied by `php dev/install.php` (safe to run again).

CREATE TABLE IF NOT EXISTS admin_users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(64)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(64) NOT NULL PRIMARY KEY,
  v TEXT        NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS letters (
  letter     CHAR(1)      NOT NULL PRIMARY KEY,
  word       VARCHAR(40)  NOT NULL DEFAULT '',
  emoji      VARCHAR(16)  NOT NULL DEFAULT '',
  image      VARCHAR(255) NULL,
  voice      VARCHAR(255) NULL,
  enabled    TINYINT(1)   NOT NULL DEFAULT 1,
  updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS drawings (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  letter      VARCHAR(3)   NOT NULL,                          -- 'A'…'Z', or '0'…'100' for numbers
  letter_case ENUM('upper','lower','number') NOT NULL DEFAULT 'upper',
  mode        ENUM('trace','draw')  NOT NULL DEFAULT 'trace',
  device_id   VARCHAR(64)  NOT NULL DEFAULT '',
  file        VARCHAR(255) NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
