<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\User;

class ClientDocumentPolicy
{
    /** Anyone who can open the client - or any project of the client - can open its documents. */
    public function view(User $user, ClientDocument $document): bool
    {
        return $document->client !== null && $user->can('view', $document->client);
    }

    /** Marketing, Team Leads, Managers (and administrators) upload client documents. */
    public function create(User $user, Client $client): bool
    {
        return $user->hasAbility('manage-client-documents') && $user->can('view', $client);
    }

    /** The uploader, or anyone who manages client documents. */
    public function delete(User $user, ClientDocument $document): bool
    {
        return $user->hasAbility('manage-client-documents')
            && ($document->uploaded_by === $user->id || $user->hasAbility('manage-clients'));
    }
}
