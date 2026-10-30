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

    /** Marketing and Managers prepare quotations. */
    public function create(User $user): bool
    {
        return $user->hasAbility('manage-quotations');
    }

    public function update(User $user, Quotation $quotation): bool
    {
        return $user->hasAbility('manage-quotations') && $user->canAccessProject($quotation->project_id);
    }

    /** Marking a quotation as sent is the internal approval step: Managers only. */
    public function send(User $user, Quotation $quotation): bool
    {
        return $user->hasAbility('approve-quotations') && $user->canAccessProject($quotation->project_id);
    }

    /** Recording the client's accept/reject decision. */
    public function decide(User $user, Quotation $quotation): bool
    {
        return $user->hasAbility('record-client-approvals') && $user->canAccessProject($quotation->project_id);
    }
}
