<?php

namespace App\Models;

use App\Enums\QuotationStatus;
use App\Models\Concerns\BelongsToProject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    use BelongsToProject;

    /** Also hidden while its client is deleted. */
    public const SCOPED_TO_CLIENT = true;

    protected $fillable = ['title', 'currency', 'valid_until', 'payment_terms', 'scope_summary', 'exclusions', 'assumptions', 'delivery_period', 'notes', 'discount_amount', 'tax_rate', 'scope_version_id'];

    protected function casts(): array
    {
        return [
            'status' => QuotationStatus::class,
            'valid_until' => 'date',
            'sent_at' => 'datetime',
            'decided_at' => 'datetime',
            'subtotal' => 'decimal:2', 'discount_amount' => 'decimal:2', 'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2', 'total' => 'decimal:2', 'optional_total' => 'decimal:2',
            'internal_cost_total' => 'decimal:2', 'estimated_hours' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopeVersion(): BelongsTo
    {
        return $this->belongsTo(ScopeVersion::class);
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'supersedes_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function isLocked(): bool
    {
        return ! $this->status->isEditable();
    }

    public function reference(): string
    {
        return "{$this->number} v{$this->version}";
    }

    public function margin(): ?float
    {
        $net = (float) $this->subtotal - (float) $this->discount_amount;

        return $net > 0 ? round(($net - (float) $this->internal_cost_total) / $net * 100, 1) : null;
    }
}
