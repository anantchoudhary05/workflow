<?php

use App\Http\Controllers;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

// Authentication. There is deliberately no public registration route:
// the first administrator is created with `php artisan doc:make-admin`.
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
});
Route::post('logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'active'])->group(function () {
    Route::redirect('/', '/dashboard');
    Route::get('dashboard', Controllers\DashboardController::class)->name('dashboard');
    Route::get('my-work', Controllers\MyWorkController::class)->name('my-work');
    Route::get('search', Controllers\SearchController::class)->name('search');

    Route::get('notifications', [Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [Controllers\NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('notifications/{id}', [Controllers\NotificationController::class, 'open'])->name('notifications.open');

    // Clients
    Route::resource('clients', Controllers\ClientController::class)->except('destroy');
    Route::post('clients/{client}/archive', [Controllers\ClientController::class, 'archive'])->name('clients.archive');
    Route::post('clients/{client}/contacts', [Controllers\ClientController::class, 'storeContact'])->name('clients.contacts.store');
    Route::delete('client-contacts/{contact}', [Controllers\ClientController::class, 'destroyContact'])->name('clients.contacts.destroy');

    // Projects and workspace
    Route::resource('projects', Controllers\ProjectController::class)->except('destroy');
    Route::prefix('projects/{project}')->name('projects.')->group(function () {
        Route::post('status', [Controllers\ProjectController::class, 'status'])->name('status');
        Route::post('due-date', [Controllers\ProjectController::class, 'dueDate'])->name('due-date');
        Route::post('members', [Controllers\ProjectController::class, 'addMember'])->name('members.store');
        Route::delete('members/{user}', [Controllers\ProjectController::class, 'removeMember'])->name('members.destroy');
        Route::get('activity', [Controllers\ProjectController::class, 'activity'])->name('activity');

        Route::get('requirements', [Controllers\RequirementController::class, 'index'])->name('requirements');
        Route::post('requirements', [Controllers\RequirementController::class, 'store'])->name('requirements.store');

        Route::get('scope', [Controllers\ScopeController::class, 'index'])->name('scope');
        Route::post('scope', [Controllers\ScopeController::class, 'store'])->name('scope.store');

        Route::get('quotations', [Controllers\QuotationController::class, 'forProject'])->name('quotations');
        Route::get('quotations/create', [Controllers\QuotationController::class, 'create'])->name('quotations.create');
        Route::post('quotations', [Controllers\QuotationController::class, 'store'])->name('quotations.store');

        Route::get('milestones', [Controllers\MilestoneController::class, 'index'])->name('milestones');
        Route::post('milestones', [Controllers\MilestoneController::class, 'store'])->name('milestones.store');

        Route::get('tasks', [Controllers\TaskController::class, 'forProject'])->name('tasks');
        Route::get('changes', [Controllers\ChangeRequestController::class, 'forProject'])->name('changes');
        Route::post('changes', [Controllers\ChangeRequestController::class, 'store'])->name('changes.store');
        Route::get('previews', [Controllers\PreviewController::class, 'index'])->name('previews');
        Route::post('previews', [Controllers\PreviewController::class, 'store'])->name('previews.store');

        Route::get('qa', [Controllers\QaController::class, 'index'])->name('qa');
        Route::post('test-cases', [Controllers\QaController::class, 'storeTestCase'])->name('test-cases.store');
        Route::post('defects', [Controllers\QaController::class, 'storeDefect'])->name('defects.store');

        Route::get('files', [Controllers\FileController::class, 'index'])->name('files');
        Route::post('files', [Controllers\FileController::class, 'store'])->name('files.store');

        Route::get('checklist/{type}', [Controllers\ChecklistController::class, 'index'])->whereIn('type', ['release', 'handover'])->name('checklist');
        Route::post('checklist/{type}/generate', [Controllers\ChecklistController::class, 'generate'])->whereIn('type', ['release', 'handover'])->name('checklist.generate');
        Route::post('checklist/{type}', [Controllers\ChecklistController::class, 'store'])->whereIn('type', ['release', 'handover'])->name('checklist.store');

        Route::post('handover-status', [Controllers\ClosureController::class, 'handover'])->name('handover-status');
        Route::post('acceptance', [Controllers\ClosureController::class, 'acceptance'])->name('acceptance');
        Route::post('close', [Controllers\ClosureController::class, 'close'])->name('close');
        Route::post('reopen', [Controllers\ClosureController::class, 'reopen'])->name('reopen');
    });

    Route::patch('requirements/{requirement}', [Controllers\RequirementController::class, 'update'])->name('requirements.update');
    Route::post('requirements/{requirement}/decision', [Controllers\RequirementController::class, 'decide'])->name('requirements.decide');
    Route::get('scope-versions/{scopeVersion}', [Controllers\ScopeController::class, 'show'])->name('scope.show');

    // Quotations (financial: view-financials + admin for changes)
    Route::get('quotations', [Controllers\QuotationController::class, 'index'])->name('quotations.index');
    Route::get('quotations/{quotation}', [Controllers\QuotationController::class, 'show'])->name('quotations.show');
    Route::get('quotations/{quotation}/edit', [Controllers\QuotationController::class, 'edit'])->name('quotations.edit');
    Route::put('quotations/{quotation}', [Controllers\QuotationController::class, 'update'])->name('quotations.update');
    Route::get('quotations/{quotation}/print', [Controllers\QuotationController::class, 'print'])->name('quotations.print');
    Route::post('quotations/{quotation}/submit', [Controllers\QuotationController::class, 'submit'])->name('quotations.submit');
    Route::post('quotations/{quotation}/send', [Controllers\QuotationController::class, 'send'])->name('quotations.send');
    Route::post('quotations/{quotation}/decide', [Controllers\QuotationController::class, 'decide'])->name('quotations.decide');
    Route::post('quotations/{quotation}/new-version', [Controllers\QuotationController::class, 'newVersion'])->name('quotations.new-version');

    Route::put('milestones/{milestone}', [Controllers\MilestoneController::class, 'update'])->name('milestones.update');
    Route::post('milestones/{milestone}/status', [Controllers\MilestoneController::class, 'status'])->name('milestones.status');

    // Tasks
    Route::get('tasks', [Controllers\TaskController::class, 'index'])->name('tasks.index');
    Route::get('tasks/board', [Controllers\TaskController::class, 'board'])->name('tasks.board');
    Route::get('tasks/create', [Controllers\TaskController::class, 'create'])->name('tasks.create');
    Route::post('tasks', [Controllers\TaskController::class, 'store'])->name('tasks.store');
    Route::get('tasks/{task}', [Controllers\TaskController::class, 'show'])->name('tasks.show');
    Route::get('tasks/{task}/edit', [Controllers\TaskController::class, 'edit'])->name('tasks.edit');
    Route::put('tasks/{task}', [Controllers\TaskController::class, 'update'])->name('tasks.update');
    Route::post('tasks/{task}/status', [Controllers\TaskController::class, 'status'])->name('tasks.status');
    Route::post('tasks/{task}/comments', [Controllers\TaskController::class, 'comment'])->name('tasks.comments.store');
    Route::post('tasks/{task}/time', [Controllers\TaskController::class, 'logTime'])->name('tasks.time.store');

    // Client operations
    Route::resource('meetings', Controllers\MeetingController::class)->except('destroy');
    Route::post('meetings/{meeting}/decisions', [Controllers\MeetingController::class, 'storeDecision'])->name('meetings.decisions.store');
    Route::post('meeting-decisions/{decision}/task', [Controllers\MeetingController::class, 'decisionToTask'])->name('meetings.decisions.task');

    Route::get('communications', [Controllers\CommunicationController::class, 'index'])->name('communications.index');
    Route::post('communications', [Controllers\CommunicationController::class, 'store'])->name('communications.store');

    Route::get('client-actions', [Controllers\ClientActionController::class, 'index'])->name('client-actions.index');
    Route::post('client-actions', [Controllers\ClientActionController::class, 'store'])->name('client-actions.store');
    Route::post('client-actions/{clientAction}/follow-up', [Controllers\ClientActionController::class, 'followUp'])->name('client-actions.follow-up');
    Route::post('client-actions/{clientAction}/resolve', [Controllers\ClientActionController::class, 'resolve'])->name('client-actions.resolve');

    Route::put('previews/{preview}', [Controllers\PreviewController::class, 'update'])->name('previews.update');
    Route::post('previews/{preview}/feedback', [Controllers\PreviewController::class, 'feedback'])->name('previews.feedback');

    Route::get('approvals', [Controllers\ApprovalController::class, 'index'])->name('approvals.index');
    Route::post('milestones/{milestone}/approval', [Controllers\ApprovalController::class, 'milestone'])->name('approvals.milestone');

    Route::get('change-requests', [Controllers\ChangeRequestController::class, 'index'])->name('change-requests.index');
    Route::get('change-requests/{changeRequest}', [Controllers\ChangeRequestController::class, 'show'])->name('change-requests.show');
    Route::put('change-requests/{changeRequest}', [Controllers\ChangeRequestController::class, 'update'])->name('change-requests.update');
    Route::post('change-requests/{changeRequest}/status', [Controllers\ChangeRequestController::class, 'status'])->name('change-requests.status');
    Route::post('change-requests/{changeRequest}/internal-review', [Controllers\ChangeRequestController::class, 'internalReview'])->name('change-requests.internal-review');
    Route::post('change-requests/{changeRequest}/client-decision', [Controllers\ChangeRequestController::class, 'clientDecision'])->name('change-requests.client-decision');

    // QA & delivery
    Route::get('qa', [Controllers\QaController::class, 'overview'])->name('qa.index');
    Route::post('test-cases/{testCase}/result', [Controllers\QaController::class, 'recordResult'])->name('test-cases.result');
    Route::get('defects/{defect}', [Controllers\QaController::class, 'showDefect'])->name('defects.show');
    Route::post('defects/{defect}/status', [Controllers\QaController::class, 'defectStatus'])->name('defects.status');
    Route::post('checklist-items/{item}', [Controllers\ChecklistController::class, 'update'])->name('checklist.update');

    Route::get('files/{file}/download', [Controllers\FileController::class, 'download'])->name('files.download');
    Route::delete('files/{file}', [Controllers\FileController::class, 'destroy'])->name('files.destroy');

    // Team, reports, audit, administration
    Route::get('team', Controllers\TeamController::class)->name('team.index');
    Route::get('reports', [Controllers\ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/{report}.csv', [Controllers\ReportController::class, 'export'])->name('reports.export');
    Route::get('activity', [Controllers\ActivityController::class, 'index'])->name('activity.index');
    Route::resource('users', Controllers\UserController::class)->except(['show', 'destroy']);
});
