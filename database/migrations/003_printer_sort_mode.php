<?php

declare(strict_types=1);

return [
    "ALTER TABLE workspaces ADD COLUMN printer_sort_mode ENUM('az','za','custom') NOT NULL DEFAULT 'az' AFTER timezone",
];
