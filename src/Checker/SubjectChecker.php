<?php

declare(strict_types=1);

namespace Tobi1craft\Sso\Checker;

use Jose\Component\Checker\ClaimChecker;
use Jose\Component\Checker\InvalidClaimException;


final class SubjectChecker implements ClaimChecker
{
    /**
     * {@inheritdoc}
     */
    public function checkClaim($value): void
    {
        if (!is_string($value)) {
            throw new InvalidClaimException('The claim "sub" must be a string.', 'sub', $value);
        }
        if ($value !== 'sso') { // Check if the value is allowed
            throw new InvalidClaimException('The claim "sub" must be "sso".', 'sub', $value);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supportedClaim(): string
    {
        return 'sub'; //The claim to check.
    }
}