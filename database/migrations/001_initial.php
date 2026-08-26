<?php

declare(strict_types=1);

return [
    "CREATE TABLE IF NOT EXISTS schema_migrations (
        version VARCHAR(64) PRIMARY KEY,
        applied_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS workspaces (
        id CHAR(36) PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        locale VARCHAR(10) NOT NULL DEFAULT 'cs',
        timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/Prague',
        created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS users (
        id CHAR(36) PRIMARY KEY,
        workspace_id CHAR(36) NOT NULL,
        username VARCHAR(80) NOT NULL,
        email VARCHAR(190) NULL,
        display_name VARCHAR(120) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('admin','manager','operator','viewer') NOT NULL DEFAULT 'viewer',
        locale VARCHAR(10) NOT NULL DEFAULT 'cs',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        failed_login_count INT UNSIGNED NOT NULL DEFAULT 0,
        locked_until DATETIME(6) NULL,
        last_login_at DATETIME(6) NULL,
        created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
        deleted_at DATETIME(6) NULL,
        UNIQUE KEY users_workspace_username_unique (workspace_id, username),
        UNIQUE KEY users_workspace_email_unique (workspace_id, email),
        CONSTRAINT users_workspace_fk FOREIGN KEY (workspace_id) REFERENCES workspaces(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS devices (
        id CHAR(36) PRIMARY KEY,
        workspace_id CHAR(36) NOT NULL,
        user_id CHAR(36) NOT NULL,
        name VARCHAR(120) NOT NULL,
        platform VARCHAR(40) NOT NULL DEFAULT 'android',
        app_version VARCHAR(40) NULL,
        last_seen_at DATETIME(6) NULL,
        revoked_at DATETIME(6) NULL,
        created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        CONSTRAINT devices_workspace_fk FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
        CONSTRAINT devices_user_fk FOREIGN KEY (user_id) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS api_refresh_tokens (
        id CHAR(36) PRIMARY KEY,
        device_id CHAR(36) NOT NULL,
        user_id CHAR(36) NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME(6) NOT NULL,
        rotated_from_id CHAR(36) NULL,
        revoked_at DATETIME(6) NULL,
        created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        UNIQUE KEY refresh_token_hash_unique (token_hash),
        CONSTRAINT refresh_device_fk FOREIGN KEY (device_id) REFERENCES devices(id),
        CONSTRAINT refresh_user_fk FOREIGN KEY (user_id) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS api_access_tokens (
        id CHAR(36) PRIMARY KEY,
        device_id CHAR(36) NOT NULL,
        user_id CHAR(36) NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME(6) NOT NULL,
        revoked_at DATETIME(6) NULL,
        created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        UNIQUE KEY access_token_hash_unique (token_hash),
        INDEX access_token_expiry_idx (expires_at),
        CONSTRAINT access_device_fk FOREIGN KEY (device_id) REFERENCES devices(id),
        CONSTRAINT access_user_fk FOREIGN KEY (user_id) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS manufacturers (
        id CHAR(36) PRIMARY KEY,
        workspace_id CHAR(36) NOT NULL,
        name VARCHAR(160) NOT NULL,
        website VARCHAR(255) NULL,
        notes TEXT NULL,
        version INT UNSIGNED NOT NULL DEFAULT 1,
        created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
        deleted_at DATETIME(6) NULL,
        UNIQUE KEY manufacturers_workspace_name_unique (workspace_id, name),
        CONSTRAINT manufacturers_workspace_fk FOREIGN KEY (workspace_id) REFERENCES workspaces(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS materials (
        id CHAR(36) PRIMARY KEY,
        workspace_id CHAR(36) NOT NULL,
        manufacturer_id CHAR(36) NULL,
        material_type VARCHAR(80) NOT NULL,
        commercial_name VARCHAR(160) NULL,
        color_name VARCHAR(120) NOT NULL,
        color_hex CHAR(7) NULL,
        diameter_mm DECIMAL(4,2) NOT NULL DEFAULT 1.75,
        density_g_cm3 DECIMAL(6,3) NULL,
        nozzle_temp_min SMALLINT UNSIGNED NULL,
        nozzle_temp_max SMALLINT UNSIGNED NULL,
        bed_temp_min SMALLINT UNSIGNED NULL,
        bed_temp_max SMALLINT UNSIGNED NULL,
        notes TEXT NULL,
        version INT UNSIGNED NOT NULL DEFAULT 1,
        created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
        deleted_at DATETIME(6) NULL,
        INDEX materials_lookup_idx (workspace_id, material_type, color_name),
        CONSTRAINT materials_workspace_fk FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
        CONSTRAINT materials_manufacturer_fk FOREIGN KEY (manufacturer_id) REFERENCES manufacturers(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS locations (
        id CHAR(36) PRIMARY KEY,
        workspace_id CHAR(36) NOT NULL,
        parent_id CHAR(36) NULL,
        name VARCHAR(120) NOT NULL,
        code VARCHAR(60) NULL,
        description VARCHAR(255) NULL,
        version INT UNSIGNED NOT NULL DEFAULT 1,
        created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
        deleted_at DATETIME(6) NULL,
        CONSTRAINT locations_workspace_fk FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
        CONSTRAINT locations_parent_fk FOREIGN KEY (parent_id) REFERENCES locations(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS spools (
        id CHAR(36) PRIMARY KEY,
        workspace_id CHAR(36) NOT NULL,
        material_id CHAR(36) NOT NULL,
        location_id CHAR(36) NULL,
        tag_uid VARCHAR(128) NULL,
        openprinttag_id VARCHAR(128) NULL,
        original_net_weight_g DECIMAL(10,2) NOT NULL,
        current_net_weight_g DECIMAL(10,2) NOT NULL,
        tare_weight_g DECIMAL(10,2) NULL,
        purchase_date DATE NULL,
        purchase_price DECIMAL(12,2) NULL,
        currency CHAR(3) NULL,
        batch_number VARCHAR(120) NULL,
        manufactured_at DATE NULL,
        expires_at DATE NULL,
        opened_at DATE NULL,
        last_dried_at DATETIME(6) NULL,
        status ENUM('in_stock','loaded','empty','archived') NOT NULL DEFAULT 'in_stock',
        notes TEXT NULL,
        openprinttag_data JSON NULL,
        version INT UNSIGNED NOT NULL DEFAULT 1,
        created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
        deleted_at DATETIME(6) NULL,
        UNIQUE KEY spools_workspace_tag_unique (workspace_id, tag_uid),
        INDEX spools_status_idx (workspace_id, status),
        CONSTRAINT spools_workspace_fk FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
        CONSTRAINT spools_material_fk FOREIGN KEY (material_id) REFERENCES materials(id),
        CONSTRAINT spools_location_fk FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS printers (
        id CHAR(36) PRIMARY KEY,
        workspace_id CHAR(36) NOT NULL,
        name VARCHAR(120) NOT NULL,
        manufacturer VARCHAR(120) NULL,
        model VARCHAR(120) NULL,
        description VARCHAR(255) NULL,
        status ENUM('active','maintenance','inactive') NOT NULL DEFAULT 'active',
        sort_order INT NOT NULL DEFAULT 0,
        version INT UNSIGNED NOT NULL DEFAULT 1,
        created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
        deleted_at DATETIME(6) NULL,
        UNIQUE KEY printers_workspace_name_unique (workspace_id, name),
        CONSTRAINT printers_workspace_fk FOREIGN KEY (workspace_id) REFERENCES workspaces(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS printer_slots (
        id CHAR(36) PRIMARY KEY,
        workspace_id CHAR(36) NOT NULL,
        printer_id CHAR(36) NOT NULL,
        slot_number SMALLINT UNSIGNED NOT NULL,
        label VARCHAR(80) NULL,
        loaded_spool_id CHAR(36) NULL,
        version INT UNSIGNED NOT NULL DEFAULT 1,
        created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
        deleted_at DATETIME(6) NULL,
        UNIQUE KEY printer_slot_number_unique (printer_id, slot_number),
        UNIQUE KEY printer_loaded_spool_unique (loaded_spool_id),
        CONSTRAINT slots_workspace_fk FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
        CONSTRAINT slots_printer_fk FOREIGN KEY (printer_id) REFERENCES printers(id),
        CONSTRAINT slots_spool_fk FOREIGN KEY (loaded_spool_id) REFERENCES spools(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS spool_movements (
        id CHAR(36) PRIMARY KEY,
        workspace_id CHAR(36) NOT NULL,
        spool_id CHAR(36) NOT NULL,
        movement_type ENUM('created','stocked','loaded','unloaded','weight_adjusted','consumed','transferred','archived','restored') NOT NULL,
        from_location_id CHAR(36) NULL,
        to_location_id CHAR(36) NULL,
        printer_id CHAR(36) NULL,
        printer_slot_id CHAR(36) NULL,
        weight_before_g DECIMAL(10,2) NULL,
        weight_after_g DECIMAL(10,2) NULL,
        weight_delta_g DECIMAL(10,2) NULL,
        source ENUM('web','mobile','nfc','api','import','system') NOT NULL DEFAULT 'web',
        user_id CHAR(36) NULL,
        device_id CHAR(36) NULL,
        client_mutation_id CHAR(36) NULL,
        notes VARCHAR(255) NULL,
        created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        UNIQUE KEY spool_movement_mutation_unique (workspace_id, client_mutation_id),
        INDEX spool_movement_history_idx (spool_id, created_at),
        CONSTRAINT movement_workspace_fk FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
        CONSTRAINT movement_spool_fk FOREIGN KEY (spool_id) REFERENCES spools(id),
        CONSTRAINT movement_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT movement_device_fk FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS sync_changes (
        sequence BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        workspace_id CHAR(36) NOT NULL,
        entity_type VARCHAR(40) NOT NULL,
        entity_id CHAR(36) NOT NULL,
        operation ENUM('upsert','delete') NOT NULL,
        entity_version INT UNSIGNED NOT NULL,
        changed_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        user_id CHAR(36) NULL,
        device_id CHAR(36) NULL,
        INDEX sync_changes_cursor_idx (workspace_id, sequence),
        CONSTRAINT sync_workspace_fk FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
        CONSTRAINT sync_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT sync_device_fk FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS sync_mutations (
        workspace_id CHAR(36) NOT NULL,
        device_id CHAR(36) NOT NULL,
        client_mutation_id CHAR(36) NOT NULL,
        result_data JSON NOT NULL,
        created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        PRIMARY KEY (workspace_id, device_id, client_mutation_id),
        INDEX sync_mutations_created_idx (created_at),
        CONSTRAINT mutations_workspace_fk FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
        CONSTRAINT mutations_device_fk FOREIGN KEY (device_id) REFERENCES devices(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS audit_log (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        workspace_id CHAR(36) NULL,
        user_id CHAR(36) NULL,
        device_id CHAR(36) NULL,
        action VARCHAR(80) NOT NULL,
        entity_type VARCHAR(40) NULL,
        entity_id CHAR(36) NULL,
        before_data JSON NULL,
        after_data JSON NULL,
        ip_hash CHAR(64) NULL,
        request_id VARCHAR(32) NULL,
        created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        INDEX audit_workspace_created_idx (workspace_id, created_at),
        CONSTRAINT audit_workspace_fk FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE SET NULL,
        CONSTRAINT audit_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT audit_device_fk FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS settings (
        workspace_id CHAR(36) NOT NULL,
        setting_key VARCHAR(120) NOT NULL,
        setting_value TEXT NULL,
        updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
        PRIMARY KEY (workspace_id, setting_key),
        CONSTRAINT settings_workspace_fk FOREIGN KEY (workspace_id) REFERENCES workspaces(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
