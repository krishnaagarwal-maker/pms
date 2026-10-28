<?php

use Illuminate\Support\Facades\Route;
use Modules\ProjectManagement\Http\Controllers\AttachmentController;
use Modules\ProjectManagement\Http\Controllers\DashboardController;
use Modules\ProjectManagement\Http\Controllers\HomeController;
use Modules\ProjectManagement\Http\Controllers\ProjectController;
use Modules\ProjectManagement\Http\Controllers\RoleController;
use Modules\ProjectManagement\Http\Controllers\TaskCommentController;
use Modules\ProjectManagement\Http\Controllers\TaskController;
use Modules\ProjectManagement\Http\Controllers\TaskTimerController;
use Modules\ProjectManagement\Http\Controllers\UserRoleController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your module. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::prefix('projectmanagement')->name('projectmanagement.')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
});
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['web', 'auth'])
    ->prefix('projects')
    ->name('projects.')
    ->group(function () {

        // Project routes
        Route::get('/', [ProjectController::class, 'index'])->middleware('permission:projects.view')->name('index');
        Route::get('/create', [ProjectController::class, 'create'])->middleware('permission:projects.create')->name('create');
        Route::post('/', [ProjectController::class, 'store'])->middleware('permission:projects.create')->name('store');

        // Keep static task paths above /{project} so they are not captured as project IDs
        Route::get('/tasks', [TaskController::class, 'index'])->middleware('permission:tasks.view')->name('tasks.index');
        Route::get('/my-tasks', [TaskController::class, 'myTasks'])
            ->middleware('permission:tasks.view')
            ->name('my-tasks');

        // Role Management routes

        // Role Management routes
        Route::get('/roles', [RoleController::class, 'index'])
            ->middleware('permission:roles.view')
            ->name('roles.index');

        Route::get('/roles/create', [RoleController::class, 'create'])
            ->middleware('permission:roles.create')
            ->name('roles.create');

        Route::post('/roles', [RoleController::class, 'store'])
            ->middleware('permission:roles.create')
            ->name('roles.store');

        // Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])
        //     ->middleware('permission:roles.edit')
        //     ->name('roles.edit');

        Route::put('/roles/{role}', [RoleController::class, 'update'])
            ->middleware('permission:roles.edit')
            ->name('roles.update');

        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])
            ->middleware('permission:roles.delete')
            ->name('roles.destroy');

        // User Role Management routes
        Route::get('/users/roles', [UserRoleController::class, 'index'])
            ->middleware('permission:roles.view')
            ->name('users.roles.index');

        Route::post('/users/roles', [UserRoleController::class, 'assign'])
            ->middleware('permission:roles.assign-permissions')
            ->name('users.roles.assign');

        Route::post('/users/onboard', [UserRoleController::class, 'onboard'])
            ->middleware('permission:users.create')
            ->name('users.onboard');

        Route::delete('/users/{user}', [UserRoleController::class, 'destroy'])
            ->middleware('permission:users.delete')
            ->name('users.destroy');

        // Attachment routes (download, preview, delete)
        Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');
        Route::get('/attachments/{attachment}/preview', [AttachmentController::class, 'preview'])->name('attachments.preview');
        Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

        Route::get('/{project}/tasks/create', [TaskController::class, 'create'])->middleware('permission:tasks.create')->name('tasks.create');
        Route::post('/{project}/tasks', [TaskController::class, 'store'])->middleware('permission:tasks.create')->name('tasks.store');
        Route::post('/{project}/attachments', [AttachmentController::class, 'storeProject'])->name('attachments.store');
        Route::post('/{project}/tasks/{task}/attachments', [AttachmentController::class, 'storeTask'])->name('tasks.attachments.store');
        Route::get('/{project}/tasks/{task}', [TaskController::class, 'show'])->middleware('permission:tasks.view')->name('tasks.show');
        Route::post('/{project}/tasks/{task}/comments', [TaskCommentController::class, 'store'])
            ->middleware('permission:tasks.view')
            ->name('tasks.comments.store');
        Route::put('/{project}/tasks/{task}/comments/{comment}', [TaskCommentController::class, 'update'])
            ->middleware('permission:tasks.view')
            ->name('tasks.comments.update');
        Route::delete('/{project}/tasks/{task}/comments/{comment}', [TaskCommentController::class, 'destroy'])
            ->middleware('permission:tasks.view')
            ->name('tasks.comments.destroy');
        Route::get('/{project}/tasks/{task}/edit', [TaskController::class, 'edit'])->middleware('permission:tasks.edit')->name('tasks.edit');
        Route::put('/{project}/tasks/{task}', [TaskController::class, 'update'])
            ->middleware('permission:tasks.edit|tasks.update-status')
            ->name('tasks.update');
        Route::delete('/{project}/tasks/{task}', [TaskController::class, 'destroy'])->middleware('permission:tasks.delete')->name('tasks.destroy');

        // Remaining project routes
        Route::get('/{project}', [ProjectController::class, 'show'])->middleware('permission:projects.view')->name('show');
        Route::get('/{project}/edit', [ProjectController::class, 'edit'])->middleware('permission:projects.edit')->name('edit');
        Route::put('/{project}', [ProjectController::class, 'update'])->middleware('permission:projects.edit')->name('update');
        Route::delete('/{project}', [ProjectController::class, 'destroy'])->middleware('permission:projects.delete')->name('destroy');
        

        Route::post('/{project}/tasks/{task}/timer/start', [TaskTimerController::class, 'start'])
            ->name('tasks.timer.start')
            ->middleware(['auth', 'verified']);

        Route::post('/{project}/tasks/{task}/timer/stop', [TaskTimerController::class, 'stop'])
            ->name('tasks.timer.stop')
            ->middleware(['auth', 'verified']);
            

        Route::delete('/{project}/tasks/{task}/timer/{timer}', [TaskTimerController::class, 'destroy'])
            ->name('tasks.timer.destroy')
            ->middleware(['auth', 'verified']);

    });
