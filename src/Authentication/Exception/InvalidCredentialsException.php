<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Authentication\Exception;

final class InvalidCredentialsException extends AuthenticationException
{
    public function __construct(string $message = 'Invalid credentials provided.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
