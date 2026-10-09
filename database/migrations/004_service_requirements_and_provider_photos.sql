ALTER TABLE services
    ADD COLUMN requires_location TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN requires_business_name TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN requires_photos TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN requires_owner_name TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN requires_owner_phone TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN supports_delivery TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN online_only TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE providers
    ADD COLUMN owner_name VARCHAR(150) NOT NULL DEFAULT '' AFTER owner_account_id;

CREATE TABLE provider_photos (
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
