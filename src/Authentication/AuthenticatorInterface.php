<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Authentication;

use FlintPHP\Framework\Authentication\Exception\AuthenticationException;
use FlintPHP\Framework\Http\Request;

interface AuthenticatorInterface
{
    /**
     * Authenticate the given request.
     *
     * @param Request $request
     * @return IdentityInterface
     * @throws AuthenticationException
     */
    public function authenticate(Request $request): IdentityInterface;
}
