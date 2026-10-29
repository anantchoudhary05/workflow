<?php

namespace App\Enums;

enum CommunicationType: string
{
    use EnumHelpers;

    case Email = 'email';
    case Phone = 'phone';
    case Meeting = 'meeting';
    case Messaging = 'messaging';
    case Preview = 'preview';
    case Approval = 'approval';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Phone => 'Phone call',
            self::Messaging => 'Messaging app',
            self::Preview => 'Website preview',
            self::Approval => 'Formal approval',
            default => ucfirst($this->value),
        };
    }
}
