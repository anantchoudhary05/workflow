<?php

namespace App\Enums;

enum ClientDocumentCategory: string
{
    use EnumHelpers;

    case RequirementBrief = 'requirement_brief';
    case Contract = 'contract';
    case ClientDetails = 'client_details';
    case DesignReference = 'design_reference';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::RequirementBrief => 'Requirement brief',
            self::Contract => 'Contract',
            self::ClientDetails => 'Client details',
            self::DesignReference => 'Design reference',
            self::Other => 'Other',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::RequirementBrief => 'brand',
            self::Contract => 'warning',
            self::ClientDetails => 'info',
            self::DesignReference => 'success',
            self::Other => 'neutral',
        };
    }
}
