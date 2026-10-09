<?php

namespace App\Modules\Identity\Support;

class ReservedUsernames
{
    public static function contains(string $username): bool
    {
        $normalized = strtolower($username);
        $reserved = array_map('strtolower', config('identity.reserved_usernames', []));

        return in_array($normalized, $reserved, true);
    }
}
