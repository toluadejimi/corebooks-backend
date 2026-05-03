<?php

namespace Laragear\WebAuthn\Contracts;

use Laragear\WebAuthn\Assertion\Creator\AssertionCreation;
use Laragear\WebAuthn\Assertion\Validator\AssertionValidation;
use Laragear\WebAuthn\Attestation\Creator\AttestationCreation;
use Laragear\WebAuthn\Attestation\Validator\AttestationValidation;
use Laragear\WebAuthn\Challenge\Challenge;

interface WebAuthnChallengeRepository
{
    /**
     * Puts a ceremony challenge into the repository.
     */
    public function store(AttestationCreation|AssertionCreation $ceremony, Challenge $challenge): void;

    /**
     * Pulls a ceremony challenge out from the repository, if it exists.
     */
    public function pull(AttestationValidation|AssertionValidation $ceremony): ?Challenge;
}
