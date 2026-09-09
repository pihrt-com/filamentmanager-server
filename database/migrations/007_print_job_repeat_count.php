<?php

declare(strict_types=1);

return [
    "ALTER TABLE print_jobs ADD COLUMN deduction_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER completed_at",
    "UPDATE print_jobs SET deduction_count=1 WHERE status='completed' AND deduction_count=0",
];
