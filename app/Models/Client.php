<?php

namespace App\Models;

use App\Enums\ClientStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'company_name', 'primary_contact', 'email', 'phone', 'website', 'billing_address', 'source', 'status', 'owner_id', 'notes', 'last_contacted_at'];

    protected function casts(): array
    {
        return ['status' => ClientStatus::class, 'last_contacted_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (Client $client) {
            $client->code ??= sprintf('CL-%04d', (static::withTrashed()->max('id') ?? 0) + 1);
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
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
