<?php

declare(strict_types=1);

namespace FilamentManager\Core;

use RuntimeException;

final class HttpException extends RuntimeException
{
    public function __construct(string $message, private readonly int $httpStatus)
    {
        parent::__construct($message);
    }

    public function status(): int { return $this->httpStatus; }
}
