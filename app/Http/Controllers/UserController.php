<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Admin-only team account management. There is no public registration. */
class UserController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:manage')];
    }

    public function index(Request $request): View
    {
        return view('users.index', [
            'users' => User::query()
                ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%{$t}%")->orWhere('email', 'like', "%{$t}%")))
                ->when($request->query('role'), fn ($q, $r) => $q->where('role', $r))
                ->orderByDesc('is_active')->orderBy('name')->paginate(30)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('users.form', ['user' => new User(['weekly_capacity_hours' => 40])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $user = new User(collect($data)->only(['name', 'email', 'job_title', 'skills', 'weekly_capacity_hours'])->all());
        $user->forceFill(['password' => $data['password'], 'role' => $data['role'], 'is_active' => $request->boolean('is_active', true)])->save();
        Activity::log('user.created', "Created user {$user->name} ({$user->role->label()})", $user, audit: true);

        return redirect()->route('users.index')->with('status', 'User created.');
    }

    public function edit(User $user): View
    {
        $history = ActivityLog::query()->with('user')->where('subject_type', $user->getMorphClass())
            ->where('subject_id', $user->id)->latest('id')->limit(20)->get();

        return view('users.form', compact('user', 'history'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);
        $self = $request->user()->is($user);
        if ($self && ($data['role'] !== $user->role->value || ! $request->boolean('is_active'))) {
            return back()->withErrors(['role' => 'You cannot change your own role or deactivate your own account.']);
        }

        $user->fill(collect($data)->only(['name', 'email', 'job_title', 'skills', 'weekly_capacity_hours'])->all());
        $user->forceFill(['role' => $data['role'], 'is_active' => $request->boolean('is_active')]);
        if (! empty($data['password'])) {
            $user->forceFill(['password' => $data['password']]);
        }
        $changes = Activity::diff($user, ['name', 'email', 'role', 'is_active', 'job_title', 'weekly_capacity_hours']);
        $passwordChanged = $user->isDirty('password');
        $user->save();
        Activity::log('user.updated', "Updated user {$user->name}".($passwordChanged ? ' (password reset)' : ''), $user, $changes, audit: true);

        return redirect()->route('users.index')->with('status', 'User updated.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['bail', 'required', 'email', 'max:255', function (string $attribute, mixed $value, \Closure $fail) use ($user) {
                // Deleted accounts keep their email (the column is unique), so point the admin to Trash instead.
                if (User::onlyTrashed()->where('email', $value)->whereKeyNot($user?->id)->exists()) {
                    $fail('This email belongs to a deleted user. Restore that account from Trash instead of creating a new one.');
                }
            }, Rule::unique('users')->ignore($user)],
            'role' => ['required', Rule::enum(Role::class)],
            'job_title' => ['nullable', 'string', 'max:255'],
            'skills' => ['nullable', 'string', 'max:500'],
            'weekly_capacity_hours' => ['nullable', 'numeric', 'min:0', 'max:168'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::defaults()],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
