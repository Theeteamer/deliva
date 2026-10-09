USE deliva;

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
