<?php

namespace App\Enums;

trait EnumHelpers
{
    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    /** Badge tone: neutral | brand | info | success | warning | danger */
    public function tone(): string
    {
        return 'neutral';
    }

    /** @return array<string,string> value => label */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
