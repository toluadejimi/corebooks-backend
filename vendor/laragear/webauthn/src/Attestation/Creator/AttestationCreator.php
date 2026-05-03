<?php

namespace Laragear\WebAuthn\Attestation\Creator;

use Illuminate\Pipeline\Pipeline;
use Laragear\WebAuthn\Assertion\Creator\AssertionCreation;
use Laragear\WebAuthn\SharedPipes\RequireWebAuthnUser;

/**
 * @see https://www.w3.org/TR/webauthn-2/#sctn-registering-a-new-credential
 *
 * @method AssertionCreation thenReturn()
 */
class AttestationCreator extends Pipeline
{
    /**
     * The array of class pipes.
     *
     * @var array
     */
    protected $pipes = [
        RequireWebAuthnUser::class,
        Pipes\AddRelyingParty::class,
        Pipes\SetResidentKeyConfiguration::class,
        Pipes\MayRequireUserVerification::class,
        Pipes\AddUserDescriptor::class,
        Pipes\AddAcceptedAlgorithms::class,
        Pipes\MayPreventDuplicateCredentials::class,
        Pipes\CreateAttestationChallenge::class,
    ];
}
