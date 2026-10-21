<?php

use Illuminate\Support\Facades\Route;
use Modules\ProjectManagement\Http\Controllers\HomeController;
use Modules\ProjectManagement\Http\Controllers\ProjectController;
use Modules\ProjectManagement\Http\Controllers\RoleController;
use Modules\ProjectManagement\Http\Controllers\TaskController;
use Modules\ProjectManagement\Http\Controllers\DashboardController;
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
        Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:roles.view')->name('roles.index');
        Route::get('/roles/create', [RoleController::class, 'create'])->middleware('permission:roles.create')->name('roles.create');
        Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:roles.create')->name('roles.store');
        Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->middleware('permission:roles.edit')->name('roles.edit');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.edit')->name('roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete')->name('roles.destroy');

        Route::get('/users/roles', [UserRoleController::class, 'index'])->middleware('auth')->name('users.roles.index');
        Route::post('/users/roles', [UserRoleController::class, 'assign'])->middleware('auth')->name('users.roles.assign');
        Route::delete('/users/{user}', [UserRoleController::class, 'destroy'])->middleware('auth')->name('users.destroy');

        Route::get('/{project}/tasks/create', [TaskController::class, 'create'])->middleware('permission:tasks.create')->name('tasks.create');
        Route::post('/{project}/tasks', [TaskController::class, 'store'])->middleware('permission:tasks.create')->name('tasks.store');
        Route::get('/{project}/tasks/{task}', [TaskController::class, 'show'])->middleware('permission:tasks.view')->name('tasks.show');
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

    });