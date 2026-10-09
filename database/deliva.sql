CREATE DATABASE IF NOT EXISTS deliva
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE deliva;

CREATE TABLE IF NOT EXISTS services (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(60) NOT NULL,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    requires_location TINYINT(1) NOT NULL DEFAULT 1,
    requires_business_name TINYINT(1) NOT NULL DEFAULT 1,
    requires_photos TINYINT(1) NOT NULL DEFAULT 0,
    requires_owner_name TINYINT(1) NOT NULL DEFAULT 0,
    requires_owner_phone TINYINT(1) NOT NULL DEFAULT 0,
    supports_delivery TINYINT(1) NOT NULL DEFAULT 1,
    online_only TINYINT(1) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_services_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS provider_accounts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_provider_accounts_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_accounts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_accounts_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS provider_password_resets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_provider_password_resets_token (token_hash),
    KEY ix_provider_password_resets_account (account_id, expires_at),
    CONSTRAINT fk_provider_password_resets_account
        FOREIGN KEY (account_id) REFERENCES provider_accounts (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_password_resets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_password_resets_token (token_hash),
    KEY ix_admin_password_resets_account (account_id, expires_at),
    CONSTRAINT fk_admin_password_resets_account
        FOREIGN KEY (account_id) REFERENCES admin_accounts (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS providers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    service_id INT UNSIGNED NOT NULL,
    photography_type VARCHAR(10) NOT NULL DEFAULT '',
    owner_account_id INT UNSIGNED NULL,
    owner_name VARCHAR(150) NOT NULL DEFAULT '',
    is_demo TINYINT(1) NOT NULL DEFAULT 0,
    name VARCHAR(150) NOT NULL,
    location VARCHAR(150) NOT NULL,
    address VARCHAR(255) NOT NULL DEFAULT '',
    phone VARCHAR(40) NOT NULL DEFAULT '',
    description TEXT NOT NULL,
    opening_hours VARCHAR(255) NOT NULL DEFAULT '',
    opening_time TIME NULL,
    closing_time TIME NULL,
    closed_days VARCHAR(100) NOT NULL DEFAULT '',
    delivery_available TINYINT(1) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    approval_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'approved',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_providers_service_location (service_id, location, active),
    KEY ix_providers_owner_status (owner_account_id, approval_status),
    CONSTRAINT fk_providers_service
        FOREIGN KEY (service_id) REFERENCES services (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_providers_owner
        FOREIGN KEY (owner_account_id) REFERENCES provider_accounts (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS provider_photos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_id INT UNSIGNED NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_provider_photos_provider (provider_id),
    CONSTRAINT fk_provider_photos_provider
        FOREIGN KEY (provider_id) REFERENCES providers (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO services
    (slug, name, description, sort_order, requires_location, requires_business_name,
     requires_photos, requires_owner_name, requires_owner_phone, supports_delivery, online_only)
VALUES
    ('laundry', 'laundry', 'Find laundry and garment-care providers near you.', 1, 1, 1, 0, 0, 0, 1, 0),
    ('gas-delivery', 'gas delivery', 'Find gas refill shops and delivery providers near you.', 2, 1, 1, 0, 0, 0, 1, 0),
    ('water', 'water', 'Find water suppliers and delivery providers near you.', 3, 1, 1, 0, 0, 0, 1, 0),
    ('food', 'food', 'Find local food vendors, restaurants, and delivery providers near you.', 4, 1, 1, 0, 0, 0, 1, 0),
    ('photography', 'photography', 'Find indoor and outdoor photographers near you.', 5, 1, 1, 0, 0, 1, 0, 0),
    ('mama-fua', 'mama fua', 'Find laundry and garment-care providers offering mama fua services near you.', 6, 1, 1, 0, 0, 0, 1, 0),
    ('house-party', 'house party', 'Find providers for house parties and private events near you.', 7, 1, 1, 0, 0, 0, 1, 0)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    sort_order = VALUES(sort_order),
    requires_location = VALUES(requires_location),
    requires_business_name = VALUES(requires_business_name),
    requires_photos = VALUES(requires_photos),
    requires_owner_name = VALUES(requires_owner_name),
    requires_owner_phone = VALUES(requires_owner_phone),
    supports_delivery = VALUES(supports_delivery),
    online_only = VALUES(online_only);
