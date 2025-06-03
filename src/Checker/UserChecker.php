<?php

declare(strict_types=1);

namespace Tobi1craft\Sso\Checker;

use App\Models\User;
use Jose\Component\Checker\ClaimChecker;
use Jose\Component\Checker\InvalidClaimException;


final class UserChecker implements ClaimChecker
{
    /**
     * {@inheritdoc}
     */
    public function checkClaim($value): void
    {
        if (!is_int($value)) {
            throw new InvalidClaimException('The claim "user" must be an user id (int).', 'user', $value);
        }
        $user = User::find($value);

        if($user === null) {
            throw new InvalidClaimException('User with given id not found.', 'user', $value);
        }

        if ($user->use_totp) {
            throw new InvalidClaimException('User has 2FA enabled.', 'user', $value);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supportedClaim(): string
    {
        return 'user'; //The claim to check.
    }
}