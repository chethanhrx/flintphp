<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Authentication\Exception;

final class MissingCredentialsException extends AuthenticationException
{
    public function __construct(string $message = 'Authentication credentials are required.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
