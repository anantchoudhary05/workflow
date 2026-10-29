<?php

namespace App\Enums;

enum ProjectType: string
{
    use EnumHelpers;

    case BusinessWebsite = 'business_website';
    case Ecommerce = 'ecommerce';
    case WebApplication = 'web_application';
    case InternalSoftware = 'internal_software';
    case ApiIntegration = 'api_integration';
    case Maintenance = 'maintenance';
    case BugFix = 'bug_fix';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Ecommerce => 'E-commerce Website',
            self::ApiIntegration => 'API Integration',
            self::BugFix => 'Bug-fix Engagement',
            default => ucwords(str_replace('_', ' ', $this->value)),
        };
    }
}
