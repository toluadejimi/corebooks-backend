<?php

namespace Laragear\WebAuthn\Attestation\Validator\Pipes;

use Closure;
use Laragear\WebAuthn\Attestation\Validator\AttestationValidation;
use Laragear\WebAuthn\Events\CredentialAttested;

/**
 * @internal
 */
class FireCredentialAttestedEvent
{
    /**
     * Handle the incoming Attestation Validation.
     */
    public function handle(AttestationValidation $validation, Closure $next): mixed
    {
        CredentialAttested::dispatch($validation->user, $validation->credential);

        return $next($validation);
    }
}
