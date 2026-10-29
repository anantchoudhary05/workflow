<?php

namespace App\Enums;

enum RequirementCategory: string
{
    use EnumHelpers;

    case Functional = 'functional';
    case UiUx = 'ui_ux';
    case Integration = 'integration';
    case Content = 'content';
    case Performance = 'performance';
    case Seo = 'seo';
    case Security = 'security';
    case Hosting = 'hosting';
    case Maintenance = 'maintenance';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::UiUx => 'UI/UX',
            self::Seo => 'SEO',
            self::Hosting => 'Hosting & Deployment',
            default => ucfirst($this->value),
        };
    }
}
