CREATE TABLE admin_users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(80) NOT NULL,
  two_factor_enabled TINYINT(1) NOT NULL DEFAULT 1,
  two_factor_code VARCHAR(20) DEFAULT NULL,
  must_change_password TINYINT(1) NOT NULL DEFAULT 1,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  password_changed_at DATETIME DEFAULT NULL,
  last_login_at DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_password_history (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_user_id INT UNSIGNED NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_password_history_admin FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE membership_applications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(80) NOT NULL,
  membership_type VARCHAR(120) NOT NULL,
  message TEXT,
  status VARCHAR(40) NOT NULL DEFAULT 'New',
  internal_notes TEXT,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_membership_status (status),
  INDEX idx_membership_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE licensing_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NOT NULL,
  affiliation VARCHAR(190) NOT NULL,
  license_type VARCHAR(120) NOT NULL,
  experience TEXT NOT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'Received',
  internal_notes TEXT,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_licensing_status (status),
  INDEX idx_licensing_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contact_messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NOT NULL,
  subject VARCHAR(190) NOT NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  INDEX idx_contact_read (is_read),
  INDEX idx_contact_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE training_modules (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sort_order INT NOT NULL,
  step_number INT NOT NULL,
  title VARCHAR(160) NOT NULL,
  description TEXT NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_training_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(120) NOT NULL UNIQUE,
  setting_value TEXT,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_email VARCHAR(190) NOT NULL,
  action VARCHAR(120) NOT NULL,
  details TEXT,
  ip_address VARCHAR(80) NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_audit_created (created_at),
  INDEX idx_audit_admin (admin_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
  attempt_key VARCHAR(255) PRIMARY KEY,
  attempts INT NOT NULL DEFAULT 0,
  locked_until DATETIME DEFAULT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
  rate_key VARCHAR(255) PRIMARY KEY,
  attempts INT NOT NULL DEFAULT 0,
  locked_until DATETIME DEFAULT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
