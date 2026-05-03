<?php

namespace Laragear\WebAuthn\Assertion\Validator\Pipes;

use Closure;
use Laragear\WebAuthn\Assertion\Validator\AssertionValidation;
use Laragear\WebAuthn\Events\CredentialAsserted;

/**
 * @internal
 */
class FireCredentialAssertedEvent
{
    /**
     * Handle the incoming Assertion Validation.
     */
    public function handle(AssertionValidation $validation, Closure $next): mixed
    {
        CredentialAsserted::dispatch($validation->user, $validation->credential);

        return $next($validation);
    }
}
