<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\Activity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Administrator soft delete and restore for projects, clients, tasks and users, with the reason kept on the
 * record (deleted_by, deletion_reason) and in the audit log. Nothing is ever removed permanently.
 */
class Trash
{
    /** Trash page tabs => model class. */
    public const TYPES = ['projects' => Project::class, 'clients' => Client::class, 'tasks' => Task::class, 'users' => User::class];

    public function delete(Model $record, User $by, string $reason): void
    {
        $this->guardDelete($record, $by);

        DB::transaction(function () use ($record, $by, $reason) {
            $record->forceFill(['deleted_by' => $by->id, 'deletion_reason' => $reason])->save();
            $record->delete();
            Activity::log(self::event($record).'.deleted', 'Deleted '.self::describe($record).". Reason: {$reason}", $record,
                ['reason' => $reason], audit: true);
        });

        if ($record instanceof User) {
            User::forgetDeletedIds();
            // End the deleted user's database sessions straight away (other drivers: rejected on the next request).
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $record->id)->delete();
            }
        }
    }

    public function restore(Model $record, User $by, ?string $note): void
    {
        $this->guardRestore($record);

        DB::transaction(function () use ($record, $note) {
            $reason = $record->deletion_reason;
            $record->restore();
            $record->forceFill(['deleted_by' => null, 'deletion_reason' => null])->save();
            Activity::log(self::event($record).'.restored', 'Restored '.self::describe($record).($note ? ". Note: {$note}" : ''), $record,
                array_filter(['note' => $note, 'deletion_reason' => $reason]), audit: true);
        });

        if ($record instanceof User) {
            User::forgetDeletedIds();
        }
    }

    private function guardDelete(Model $record, User $by): void
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(['workflow' => $message]);

        if ($record instanceof Client && ($count = $record->projects()->count()) > 0) {
            $fail("{$record->name} still has {$count} ".str('project')->plural($count).'. Delete or move those projects first, then delete the client.');
        }
        if ($record instanceof User) {
            if ($record->is($by)) {
                $fail('You cannot delete your own account.');
            }
            $otherAdmins = User::query()->where('role', Role::Admin)->where('is_active', true)->whereKeyNot($record->id)->count();
            if ($record->role === Role::Admin && $otherAdmins === 0) {
                $fail('This is the last active administrator. Make someone else an administrator first.');
            }
        }
    }

    private function guardRestore(Model $record): void
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(['workflow' => $message]);

        if ($record instanceof Project && $record->client()->onlyTrashed()->exists()) {
            $fail("Restore the client {$record->client->name} first.");
        }
        if ($record instanceof Task && Project::onlyTrashed()->whereKey($record->project_id)->exists()) {
            $fail('This task belongs to a deleted project. Restore the project first.');
        }
    }

    public static function event(Model $record): string
    {
        return match (true) {
            $record instanceof Project => 'project',
            $record instanceof Client => 'client',
            $record instanceof Task => 'task',
            default => 'user',
        };
    }

    /** Human label used in messages and the log. */
    public static function describe(Model $record): string
    {
        return match (true) {
            $record instanceof Project => "project {$record->code} {$record->name}",
            $record instanceof Client => "client {$record->name}",
            $record instanceof Task => "task \"{$record->title}\"",
            $record instanceof User => "user {$record->realName()} ({$record->email})",
        };
    }
}
