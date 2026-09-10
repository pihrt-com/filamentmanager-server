<?php

declare(strict_types=1);

return [
    "CREATE TABLE IF NOT EXISTS manufacturer_aliases (
        workspace_id CHAR(36) NOT NULL,
        alias_id CHAR(36) NOT NULL,
        manufacturer_id CHAR(36) NOT NULL,
        PRIMARY KEY (workspace_id, alias_id),
        CONSTRAINT manufacturer_alias_workspace_fk FOREIGN KEY (workspace_id) REFERENCES workspaces(id),
        CONSTRAINT manufacturer_alias_target_fk FOREIGN KEY (manufacturer_id) REFERENCES manufacturers(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
