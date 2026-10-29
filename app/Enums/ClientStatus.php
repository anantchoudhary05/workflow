<?php

namespace App\Enums;

enum ClientStatus: string
{
    use EnumHelpers;

    case Lead = 'lead';
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';

    public function tone(): string
    {
        return match ($this) {
            self::Lead => 'info',
            self::Active => 'success',
            default => 'neutral',
        };
    }
}
