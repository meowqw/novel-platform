<?php

namespace App\Modules\Identity\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clears cookie/session rows for a user when the sessions table is present.
 * Distinct from Sanctum personal access token revocation.
 */
class RevokeUserSessions
{
    public function handle(User $user): void
    {
        if (! Schema::hasTable('sessions')) {
            return;
        }

        DB::table('sessions')->where('user_id', $user->id)->delete();
    }
}
