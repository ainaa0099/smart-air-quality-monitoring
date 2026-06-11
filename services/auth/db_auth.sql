CREATE DATABASE IF NOT EXISTS db_auth;
USE db_auth;

-- Users table
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  password VARCHAR(255),
  avatar_url VARCHAR(255),
  oauth_provider VARCHAR(50) DEFAULT 'local' COMMENT 'local, github, google, facebook',
  oauth_id VARCHAR(255) UNIQUE,
  role VARCHAR(50) DEFAULT 'user' COMMENT 'user, admin, moderator',
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL
);

-- Refresh Tokens table
CREATE TABLE IF NOT EXISTS refresh_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token LONGTEXT NOT NULL,
  is_revoked BOOLEAN DEFAULT FALSE,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
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
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
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
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
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
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_client_id (client_id),
  INDEX idx_expires_at (expires_at)
);

-- Default Admin User (optional)
INSERT IGNORE INTO users (name, email, password, role, oauth_provider) VALUES
('Admin', 'admin@smartcity.local', '$2a$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36gZvQOm', 'admin', 'local');

-- Default OAuth Test Client (optional)
INSERT IGNORE INTO oauth_clients (client_id, client_secret, client_name, grant_types) VALUES
('test-client', 'test-secret', 'Test Client', '["client_credentials", "password", "refresh_token"]');
