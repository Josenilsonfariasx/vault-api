<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * Get the tokenable model that the access token belongs to.
     * 
     * Override to disable TenantScope when loading the user.
     * This is necessary because when Sanctum validates a token,
     * the TenantContext may not be set yet, causing the TenantScope
     * to filter out the user even though the token is valid.
     */
    public function tokenable()
    {
        return $this->morphTo('tokenable')->withoutGlobalScopes();
    }
}
