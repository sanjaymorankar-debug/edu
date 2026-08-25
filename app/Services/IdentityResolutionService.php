<?php

namespace App\Services;

use App\Models\AnonymousIdentity;
use App\Models\IdentityAccessLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * The only code path allowed to reverse an anonymous_ref back to a real
 * user. Callers must hold the access-protected-identity permission (see
 * PermissionSeeder) and every call is written to identity_access_logs —
 * officer, time, record, reason, action (spec section I).
 */
class IdentityResolutionService
{
    public function resolve(string $anonymousRef, string $action, ?string $reason = null): ?User
    {
        $officer = Auth::user();

        abort_unless($officer && $officer->can('access-protected-identity'), 403);

        $identity = AnonymousIdentity::where('anonymous_ref', $anonymousRef)->first();

        IdentityAccessLog::create([
            'officer_user_id' => $officer->id,
            'anonymous_ref' => $anonymousRef,
            'action' => $action,
            'reason' => $reason,
        ]);

        return $identity?->user;
    }
}
