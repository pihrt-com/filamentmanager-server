<?php

declare(strict_types=1);

return [
    "ALTER TABLE sync_mutations DROP FOREIGN KEY mutations_device_fk",
    "ALTER TABLE sync_mutations ADD CONSTRAINT mutations_device_fk FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE",
];
