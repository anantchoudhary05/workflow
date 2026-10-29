<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    protected $fillable = ['category', 'description', 'quantity', 'unit', 'hours', 'cost_rate', 'internal_cost', 'unit_price', 'line_total', 'is_optional', 'notes', 'sort_order'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2', 'hours' => 'decimal:2', 'cost_rate' => 'decimal:2',
            'internal_cost' => 'decimal:2', 'unit_price' => 'decimal:2', 'line_total' => 'decimal:2',
            'is_optional' => 'boolean',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }
}
