<?php

declare(strict_types=1);

return [
    "CREATE TABLE IF NOT EXISTS user_notification_settings (
        user_id CHAR(36) PRIMARY KEY,
        workspace_id CHAR(36) NOT NULL,
        enabled TINYINT(1) NOT NULL DEFAULT 0,
        notify_spool_empty TINYINT(1) NOT NULL DEFAULT 1,
        notify_low_spool_weight TINYINT(1) NOT NULL DEFAULT 1,
        low_spool_weight_g DECIMAL(10,2) NOT NULL DEFAULT 100.00,
        notify_material_out TINYINT(1) NOT NULL DEFAULT 1,
        notify_low_material_count TINYINT(1) NOT NULL DEFAULT 0,
        low_material_count INT UNSIGNED NOT NULL DEFAULT 1,
        notify_location_full TINYINT(1) NOT NULL DEFAULT 0,
        updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
        INDEX notification_settings_workspace_idx (workspace_id),
        CONSTRAINT notification_settings_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT notification_settings_workspace_fk FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS notification_states (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        workspace_id CHAR(36) NOT NULL,
        user_id CHAR(36) NOT NULL,
        event_key VARCHAR(190) NOT NULL,
        event_type VARCHAR(60) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        context_data JSON NULL,
        first_triggered_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        last_triggered_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        last_queued_at DATETIME(6) NULL,
        resolved_at DATETIME(6) NULL,
        UNIQUE KEY notification_state_user_event_unique (user_id,event_key),
        INDEX notification_state_workspace_active_idx (workspace_id,is_active),
        CONSTRAINT notification_state_workspace_fk FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
        CONSTRAINT notification_state_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS mail_queue (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        workspace_id CHAR(36) NOT NULL,
        user_id CHAR(36) NULL,
        recipient VARCHAR(190) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        body TEXT NOT NULL,
        status ENUM('queued','sending','sent','failed') NOT NULL DEFAULT 'queued',
        attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        next_attempt_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        locked_at DATETIME(6) NULL,
        last_error VARCHAR(500) NULL,
        created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        sent_at DATETIME(6) NULL,
        INDEX mail_queue_due_idx (status,next_attempt_at),
        INDEX mail_queue_workspace_idx (workspace_id,created_at),
        CONSTRAINT mail_queue_workspace_fk FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
        CONSTRAINT mail_queue_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS print_jobs (
        id CHAR(36) PRIMARY KEY,
        workspace_id CHAR(36) NOT NULL,
        printer_id CHAR(36) NOT NULL,
        source ENUM('upload','prusaslicer') NOT NULL DEFAULT 'upload',
        source_file_name VARCHAR(255) NOT NULL,
        source_sha256 CHAR(64) NOT NULL,
        status ENUM('ready','printing','completed','failed','cancelled') NOT NULL DEFAULT 'ready',
        total_estimated_weight_g DECIMAL(12,2) NOT NULL,
        metadata_json JSON NULL,
        imported_by_user_id CHAR(36) NULL,
        integration_token_id CHAR(36) NULL,
        started_at DATETIME(6) NULL,
        completed_at DATETIME(6) NULL,
        created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
        INDEX print_jobs_workspace_created_idx (workspace_id,created_at),
        INDEX print_jobs_status_idx (workspace_id,status),
        CONSTRAINT print_jobs_workspace_fk FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
        CONSTRAINT print_jobs_printer_fk FOREIGN KEY (printer_id) REFERENCES printers(id),
        CONSTRAINT print_jobs_user_fk FOREIGN KEY (imported_by_user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS print_job_consumptions (
        id CHAR(36) PRIMARY KEY,
        job_id CHAR(36) NOT NULL,
        extruder_index SMALLINT UNSIGNED NOT NULL,
        material_type VARCHAR(80) NULL,
        color_hex CHAR(7) NULL,
        spool_id CHAR(36) NULL,
        estimated_weight_g DECIMAL(12,2) NOT NULL,
        actual_weight_g DECIMAL(12,2) NULL,
        weight_before_g DECIMAL(12,2) NULL,
        weight_after_g DECIMAL(12,2) NULL,
        created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        UNIQUE KEY print_job_extruder_unique (job_id,extruder_index),
        CONSTRAINT print_consumption_job_fk FOREIGN KEY (job_id) REFERENCES print_jobs(id) ON DELETE CASCADE,
        CONSTRAINT print_consumption_spool_fk FOREIGN KEY (spool_id) REFERENCES spools(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS integration_tokens (
        id CHAR(36) PRIMARY KEY,
        workspace_id CHAR(36) NOT NULL,
        created_by_user_id CHAR(36) NULL,
        name VARCHAR(120) NOT NULL,
        token_hash CHAR(64) NOT NULL,
        last_used_at DATETIME(6) NULL,
        revoked_at DATETIME(6) NULL,
        created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        UNIQUE KEY integration_token_hash_unique (token_hash),
        INDEX integration_tokens_workspace_idx (workspace_id,revoked_at),
        CONSTRAINT integration_tokens_workspace_fk FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
        CONSTRAINT integration_tokens_user_fk FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "ALTER TABLE print_jobs ADD CONSTRAINT print_jobs_integration_token_fk FOREIGN KEY (integration_token_id) REFERENCES integration_tokens(id) ON DELETE SET NULL",
];
