<?php

namespace Laragear\WebAuthn\SharedPipes;

use Closure;
use Laragear\WebAuthn\Attestation\Creator\AttestationCreation;
use Laragear\WebAuthn\Attestation\Validator\AttestationValidation;
use UnexpectedValueException;

/**
 * @internal
 */
class RequireWebAuthnUser
{
    /**
     * Handle the Attestation creation.
     */
    public function handle(AttestationCreation|AttestationValidation $attestable, Closure $next): mixed
    {
        if ($attestable->user) {
            return $next($attestable);
        }

        throw new UnexpectedValueException('There is no user set for the ceremony.');
    }
}
