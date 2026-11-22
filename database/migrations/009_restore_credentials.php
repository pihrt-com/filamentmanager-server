<?php

declare(strict_types=1);

return [
    "CREATE TABLE IF NOT EXISTS restore_credentials (
        id CHAR(36) PRIMARY KEY,
        user_id CHAR(36) NOT NULL,
        credential_id TEXT NOT NULL,
        credential_id_hash CHAR(64) NOT NULL,
        public_key_pem TEXT NOT NULL,
        origin VARCHAR(255) NOT NULL,
        sign_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
        UNIQUE KEY restore_credential_hash_unique (credential_id_hash),
        INDEX restore_credentials_user_idx (user_id),
        CONSTRAINT restore_credentials_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS webauthn_challenges (
        id CHAR(36) PRIMARY KEY,
        user_id CHAR(36) NOT NULL,
        purpose ENUM('restore_register','restore_authenticate') NOT NULL,
        challenge_hash CHAR(64) NOT NULL,
        expires_at DATETIME(6) NOT NULL,
        used_at DATETIME(6) NULL,
        created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        UNIQUE KEY webauthn_challenge_hash_unique (challenge_hash),
        INDEX webauthn_challenge_expiry_idx (expires_at),
        CONSTRAINT webauthn_challenges_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
