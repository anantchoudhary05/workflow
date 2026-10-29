<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;

class Activity
{
    /** Fields never written to the audit log. */
    private const SECRET_KEYS = ['password', 'remember_token', 'token', 'secret', 'api_key'];

    public static function log(string $event, string $description, ?Model $subject = null, array $changes = [], bool $audit = false): ActivityLog
    {
        $projectId = match (true) {
            $subject instanceof Project => $subject->id,
            $subject !== null && isset($subject->project_id) => $subject->project_id,
            default => null,
        };
        $clientId = match (true) {
            $subject instanceof Client => $subject->id,
            $subject !== null && isset($subject->client_id) => $subject->client_id,
            $subject instanceof Project => $subject->client_id,
            default => null,
        };

        return ActivityLog::create([
            'user_id' => auth()->id(),
            'project_id' => $projectId,
            'client_id' => $clientId,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'event' => $event,
            'description' => mb_substr($description, 0, 500),
            'changes' => $changes ? self::scrub($changes) : null,
            'is_audit' => $audit,
        ]);
    }

    /** Before/after for selected fields of a model that is about to be saved. */
    public static function diff(Model $model, array $fields): array
    {
        $out = [];
        foreach ($fields as $f) {
            if ($model->isDirty($f)) {
                $out[$f] = ['from' => self::plain($model->getOriginal($f)), 'to' => self::plain($model->getAttribute($f))];
            }
        }

        return $out;
    }

    private static function plain(mixed $v): mixed
    {
        return match (true) {
            $v instanceof \BackedEnum => $v->value,
            $v instanceof \DateTimeInterface => $v->format('Y-m-d'),
            default => $v,
        };
    }

    private static function scrub(array $changes): array
    {
        return array_diff_key($changes, array_flip(self::SECRET_KEYS));
    }
}
