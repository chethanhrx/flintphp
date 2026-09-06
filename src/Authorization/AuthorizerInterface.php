<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Authorization;

use FlintPHP\Framework\Authentication\IdentityInterface;

/**
 * The authorization decision contract.
 *
 * Answers a single question: "May this identity perform this ability?"
 *
 * Design contract:
 * - Authorization is strictly separated from authentication. Authentication
 *   ("who is this request associated with?") is handled by the Authentication
 *   component and surfaces the identity as a Request attribute. Authorization
 *   ("is this identity allowed to perform this operation?") consumes that
 *   identity and returns a boolean decision. The two components share nothing
 *   else.
 * - The framework never interprets the ability string. It is an opaque,
 *   developer-defined vocabulary ('' conventionally means "overall route
 *   access"). Implementations MUST NOT trust ability or resource values that
 *   originate from request input (query parameters, route parameters, headers,
 *   bodies). Authorization decisions must be driven by developer-authored
 *   configuration and server-side state only.
 * - Implementations should be stateless and are recommended to be registered
 *   as container singletons.
 * - Returning false means "denied". Throwing an exception means the
 *   authorization subsystem itself failed (infrastructure or developer error);
 *   such exceptions propagate to the framework's exception boundary and the
 *   request never proceeds. Exceptions are never converted into denials, and
 *   failures never result in allowances.
 *
 * There is deliberately no framework-provided default implementation: a
 * silent default (even deny-all) would mask developer misconfiguration.
 * Applications bind their own implementation; if none is bound, resolving
 * this interface fails loudly with a container NotFoundException.
 */
interface AuthorizerInterface
{
    /**
     * Decide whether the identity may perform the ability.
     *
     * @param IdentityInterface|null $identity The authenticated identity, or
     *                                         null when the request carries no
     *                                         identity (unauthenticated, or
     *                                         authentication middleware not
     *                                         applied). The implementation
     *                                         decides how to treat null.
     * @param string                 $ability  Opaque developer-defined ability
     *                                         name ('' = overall route access).
     * @param mixed                  $resource Optional subject object for
     *                                         fine-grained (controller-side)
     *                                         checks.
     */
    public function authorize(
        ?IdentityInterface $identity,
        string $ability = '',
        mixed $resource = null,
    ): bool;
}
