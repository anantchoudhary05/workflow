<?php

namespace App\Models;

use App\Enums\ClientStatus;
use App\Models\Concerns\RecordsDeletion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, RecordsDeletion, SoftDeletes;

    protected $fillable = ['name', 'company_name', 'primary_contact', 'email', 'phone', 'website', 'billing_address', 'source', 'status', 'owner_id', 'notes', 'last_contacted_at'];

    protected function casts(): array
    {
        return ['status' => ClientStatus::class, 'last_contacted_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (Client $client) {
            $client->created_by ??= auth()->id();
            $client->code ??= sprintf('CL-%04d', (static::withTrashed()->max('id') ?? 0) + 1);
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * Who created the record: the created_by column (set for records created from 30 Oct 2026), otherwise the
     * user on the oldest activity-log entry for the record, otherwise null (the page then hides the line).
     */
    public function creatorName(): ?string
    {
        if ($this->created_by && $this->creator) {
            return $this->creator->name;
        }

        return ActivityLog::query()->where('subject_type', $this->getMorphClass())->where('subject_id', $this->getKey())
            ->whereNotNull('user_id')->oldest('id')->first()?->user?->name;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id')->withTrashed();
    }

    /** Documents uploaded for this client (briefs, contracts, details...). */
    public function documents(): HasMany
    {
        return $this->hasMany(ClientDocument::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function communications(): HasMany
    {
        return $this->hasMany(Communication::class);
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    public function clientActions(): HasMany
    {
        return $this->hasMany(ClientAction::class);
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ($user->hasAbility('view-clients')) {
            return $q;
        }

        return $q->whereHas('projects', fn ($p) => $p->whereIn('projects.id', $user->accessibleProjectIds() ?: [0]));
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        return $term ? $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")
            ->orWhere('company_name', 'like', "%{$term}%")
            ->orWhere('email', 'like', "%{$term}%")
            ->orWhere('code', 'like', "%{$term}%")) : $q;
    }

    /** Possible duplicates by email or company/name (warning only). */
    public static function possibleDuplicates(array $data, ?int $ignoreId = null): Collection
    {
        return static::query()
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->where(function ($q) use ($data) {
                if (! empty($data['email'])) {
                    $q->orWhere('email', $data['email']);
                }
                if (! empty($data['company_name'])) {
                    $q->orWhere('company_name', $data['company_name']);
                }
                $q->orWhere('name', $data['name'] ?? '');
            })->limit(5)->get(['id', 'code', 'name', 'email']);
    }
}
