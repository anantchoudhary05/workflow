<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientContact extends Model
{
    protected $fillable = ['name', 'email', 'phone', 'role_title'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
