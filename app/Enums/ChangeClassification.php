<?php

namespace App\Enums;

enum ChangeClassification: string
{
    use EnumHelpers;

    case Bug = 'bug';
    case InScope = 'in_scope';
    case NewScope = 'new_scope';

    public function label(): string
    {
        return match ($this) {
            self::Bug => 'Bug',
            self::InScope => 'In-scope refinement',
            self::NewScope => 'New scope',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Bug => 'danger',
            self::InScope => 'info',
            self::NewScope => 'warning',
        };
    }

    public function guidance(): string
    {
        return match ($this) {
            self::Bug => 'Previously agreed functionality does not meet its acceptance criteria.',
            self::InScope => 'A reasonable adjustment that fits the approved scope and acceptance criteria.',
            self::NewScope => 'New feature, page, integration, extra revision or other material change outside the approved scope. Needs internal review and client approval before scheduling.',
        };
    }
}
