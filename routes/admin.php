<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Admin\PermissionController;
use App\Http\Controllers\Web\Admin\ProfessionalController;
use App\Http\Controllers\Web\Admin\RoleController;
use App\Http\Controllers\Web\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission:admin.users.view'])->group(function (): void {
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
});

Route::middleware('auth')->group(function (): void {
    Route::post('users/{user}/suspend', [UserController::class, 'suspend'])->middleware('permission:admin.users.suspend')->name('users.suspend');
    Route::post('users/{user}/activate', [UserController::class, 'activate'])->middleware('permission:admin.users.activate')->name('users.activate');
});

Route::middleware(['auth', 'permission:rbac.view'])->group(function (): void {
    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('roles/{role}', [RoleController::class, 'show'])->name('roles.show');
    Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
    Route::get('permissions/{permission}', [PermissionController::class, 'show'])->name('permissions.show');
});

Route::middleware(['auth', 'permission:rbac.manage'])->group(function (): void {
    Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
    Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
});

Route::middleware(['auth', 'permission:admin.professionals.view'])->group(function (): void {
    Route::get('professionals', [ProfessionalController::class, 'index'])->name('professionals.index');
    Route::get('professionals/{professional}', [ProfessionalController::class, 'show'])->name('professionals.show');
});

Route::middleware('auth')->group(function (): void {
    Route::post('professionals/{professional}/suspend', [ProfessionalController::class, 'suspend'])
        ->middleware('permission:admin.professionals.suspend')
        ->name('professionals.suspend');
    Route::post('professionals/{professional}/activate', [ProfessionalController::class, 'activate'])
        ->middleware('permission:admin.professionals.activate')
        ->name('professionals.activate');
    Route::post('professionals/{professional}/verification/start', [ProfessionalController::class, 'startReview'])
        ->middleware('permission:admin.professionals.review')
        ->name('professionals.verification.start');
    Route::post('professionals/{professional}/verification/verify', [ProfessionalController::class, 'verify'])
        ->middleware('permission:admin.professionals.verify')
        ->name('professionals.verification.verify');
    Route::post('professionals/{professional}/verification/reject', [ProfessionalController::class, 'reject'])
        ->middleware('permission:admin.professionals.reject')
        ->name('professionals.verification.reject');
});
