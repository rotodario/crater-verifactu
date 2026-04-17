<?php

namespace Crater\Policies;

use Crater\Models\User;
use Crater\Models\VerifactuRecord;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

class VerifactuPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        if (BouncerFacade::can('view-verifactu', VerifactuRecord::class)) {
            return true;
        }

        return false;
    }

    public function manage(User $user)
    {
        if (BouncerFacade::can('manage-verifactu', VerifactuRecord::class)) {
            return true;
        }

        return false;
    }

    /**
     * Platform-level actions (global SIF config, declarations lifecycle).
     * Restricted to the company owner / super admin only.
     */
    public function managePlatform(User $user)
    {
        return $user->isOwner();
    }
}
