CREATE DATABASE IF NOT EXISTS db_smartcity;
USE db_smartcity;

-- Users table
CREATE TABLE IF NOT EXISTS citizen_citizens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nik VARCHAR(16) UNIQUE,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  password VARCHAR(255),
  phone VARCHAR(20),
  zone_id INT,
  role VARCHAR(20) DEFAULT 'user',
  is_active BOOLEAN DEFAULT TRUE,
  oauth_provider VARCHAR(20) DEFAULT 'local',
  avatar_url VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Refresh Tokens table
CREATE TABLE IF NOT EXISTS refresh_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token LONGTEXT NOT NULL,
  is_revoked BOOLEAN DEFAULT FALSE,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES citizen_citizens(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  INDEX idx_expires_at (expires_at)
);

-- Social Accounts table (for handling multiple OAuth providers per user)
CREATE TABLE IF NOT EXISTS social_accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  provider VARCHAR(50) NOT NULL COMMENT 'google, github, facebook',
  provider_user_id VARCHAR(255) NOT NULL,
  provider_email VARCHAR(255),
  avatar_url VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES citizen_citizens(id) ON DELETE CASCADE,
  UNIQUE KEY uk_provider_user (provider, provider_user_id),
  INDEX idx_user_id (user_id)
);

-- OAuth Clients table (for client_credentials grant)
CREATE TABLE IF NOT EXISTS oauth_clients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id VARCHAR(255) UNIQUE NOT NULL,
  client_secret VARCHAR(255) NOT NULL,
  client_name VARCHAR(255) NOT NULL,
  redirect_uris JSON,
  grant_types JSON COMMENT 'Array of: password, client_credentials, refresh_token, authorization_code',
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_client_id (client_id)
);

-- OAuth Tokens table (for token tracking)
CREATE TABLE IF NOT EXISTS oauth_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  token_id VARCHAR(255) UNIQUE NOT NULL,
  client_id VARCHAR(255) NOT NULL,
  user_id INT,
  access_token LONGTEXT NOT NULL,
  token_type VARCHAR(50) DEFAULT 'Bearer',
  expires_at DATETIME NOT NULL,
  scope VARCHAR(255),
  is_revoked BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES citizen_citizens(id) ON DELETE SET NULL,
  INDEX idx_client_id (client_id),
  INDEX idx_user_id (user_id),
  INDEX idx_expires_at (expires_at)
);

-- Authorization Codes table (for authorization_code grant)
CREATE TABLE IF NOT EXISTS oauth_authorization_codes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code_id VARCHAR(255) UNIQUE NOT NULL,
  client_id VARCHAR(255) NOT NULL,
  user_id INT NOT NULL,
  redirect_uri VARCHAR(255) NOT NULL,
  scope VARCHAR(255),
  is_used BOOLEAN DEFAULT FALSE,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES citizen_citizens(id) ON DELETE CASCADE,
  INDEX idx_client_id (client_id),
  INDEX idx_expires_at (expires_at)
);

CREATE TABLE IF NOT EXISTS login_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  login_method VARCHAR(50) NOT NULL COMMENT 'password, oauth',
  provider VARCHAR(50) DEFAULT NULL COMMENT 'google, github, facebook',
  ip_address VARCHAR(45) NOT NULL,
  city VARCHAR(100),
  region VARCHAR(100),
  country VARCHAR(100),
  loc VARCHAR(100),
  timezone VARCHAR(100),
  org VARCHAR(255),
  user_agent TEXT,
  login_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES citizen_citizens(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  INDEX idx_login_at (login_at)
);

-- Default Admin User (optional)
INSERT IGNORE INTO citizen_citizens (name, email, password, role, oauth_provider, nik) VALUES
('Admin', 'admin@smartcity.local', '$2a$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36gZvQOm', 'admin', 'local', '0000000000000000');

-- Default OAuth Test Client (optional)
INSERT IGNORE INTO oauth_clients (client_id, client_secret, client_name, grant_types) VALUES
('test-client', 'test-secret', 'Test Client', '["client_credentials", "password", "refresh_token"]');
