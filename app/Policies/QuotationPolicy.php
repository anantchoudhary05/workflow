<?php

namespace App\Policies;

use App\Models\Quotation;
use App\Models\User;

class QuotationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAbility('view-financials');
    }

    public function view(User $user, Quotation $quotation): bool
    {
        return $user->hasAbility('view-financials') && $user->canAccessProject($quotation->project_id);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Quotation $quotation): bool
    {
        return false;
    }
}
