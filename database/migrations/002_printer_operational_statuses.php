<?php

declare(strict_types=1);

return [
    "ALTER TABLE printers MODIFY status ENUM('active','maintenance','downtime','fault','inactive') NOT NULL DEFAULT 'active'",
];
