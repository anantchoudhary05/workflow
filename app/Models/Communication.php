<?php

namespace App\Models;

use App\Enums\CommunicationType;
use App\Models\Concerns\BelongsToProject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Communication extends Model
{
    use BelongsToProject;

    /** Also hidden while its client is deleted. */
    public const SCOPED_TO_CLIENT = true;

    protected $fillable = ['client_id', 'project_id', 'type', 'direction', 'subject', 'summary', 'occurred_at', 'outcome', 'next_action'];

    protected function casts(): array
    {
        return ['type' => CommunicationType::class, 'occurred_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id')->withTrashed();
    }
}
