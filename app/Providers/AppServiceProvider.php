<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\Project;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Inactive users can do nothing; administrators can do everything that passes workflow rules.
        // Personal to-dos are the exception: they stay private even from administrators (TodoPolicy decides).
        Gate::before(function (User $user, string $ability, array $arguments = []) {
            if (! $user->is_active) {
                return false;
            }
            if (($arguments[0] ?? null) instanceof Todo || ($arguments[0] ?? null) === Todo::class) {
                return null;
            }

            return $user->isAdmin() ? true : null;
        });

        // One gate per role ability (see App\Enums\Role::abilities()).
        $abilities = collect(Role::cases())->flatMap->abilities()->reject(fn ($a) => $a === '*')->unique();
        foreach ($abilities as $ability) {
            Gate::define($ability, fn (User $user) => $user->hasAbility($ability));
        }

        // Administrator-only: user and role management and workflow overrides. Day-to-day actions use the granular abilities above.
        Gate::define('manage', fn (User $user) => false);

        // Soft delete with a reason, the Trash page and restore: administrators only (checked explicitly as well as via Gate::before).
        Gate::define('delete-records', fn (User $user) => $user->isAdmin());

        // Ability + record-level project access, e.g. Gate::authorize('project-ability', [$project, 'manage-qa']).
        Gate::define('project-ability', fn (User $user, Project $project, string $ability) => $user->hasAbility($ability) && $user->canAccessProject($project->id));

        // 5 sign-in attempts per minute per email + IP. When exceeded, return to the form with a readable message instead of a bare 429 page.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(strtolower((string) $request->input('email')).'|'.$request->ip())
            ->response(fn (Request $request, array $headers) => back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Too many sign-in attempts. Please wait a minute and try again.'])));

        View::composer('layouts.app', function (ViewContract $view): void {
            $user = auth()->user();
            $view->with('unreadCount', $user ? $user->unreadNotifications()->count() : 0);
        });

        // The project header shows the client name on every workspace tab.
        View::composer('projects.partials.tabs', function (ViewContract $view): void {
            $view->getData()['project']->loadMissing('client:id,name');
        });
    }
}
