<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Admin\AnalyticsController;
use App\Http\Controllers\Web\Admin\AuditController;
use App\Http\Controllers\Web\Admin\CategoryController;
use App\Http\Controllers\Web\Admin\OrderController;
use App\Http\Controllers\Web\Admin\PaymentController;
use App\Http\Controllers\Web\Admin\PermissionController;
use App\Http\Controllers\Web\Admin\ProfessionalController;
use App\Http\Controllers\Web\Admin\ProfessionalDocumentController;
use App\Http\Controllers\Web\Admin\ReportController;
use App\Http\Controllers\Web\Admin\RoleController;
use App\Http\Controllers\Web\Admin\ServiceController;
use App\Http\Controllers\Web\Admin\ServiceRequestController;
use App\Http\Controllers\Web\Admin\SupportController;
use App\Http\Controllers\Web\Admin\UserController;
use App\Http\Controllers\Web\Admin\VerificationController;
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
Route::middleware(['auth', 'permission:rbac.view'])->group(function (): void {
    Route::get('roles/{role}', [RoleController::class, 'show'])->name('roles.show');
});

Route::middleware(['auth', 'permission:admin.professionals.view'])->group(function (): void {
    Route::get('professionals', [ProfessionalController::class, 'index'])->name('professionals.index');
    Route::get('professionals/{professional}', [ProfessionalController::class, 'show'])->name('professionals.show');
    Route::get('professional-documents/{document}/download', [ProfessionalDocumentController::class, 'download'])
        ->middleware('active.account')
        ->name('professional-documents.download');
});
Route::middleware('auth')->group(function (): void {
    Route::post('professionals/{professional}/suspend', [ProfessionalController::class, 'suspend'])->middleware('permission:admin.professionals.suspend')->name('professionals.suspend');
    Route::post('professionals/{professional}/activate', [ProfessionalController::class, 'activate'])->middleware('permission:admin.professionals.activate')->name('professionals.activate');
    Route::post('professionals/{professional}/verification/start', [ProfessionalController::class, 'startReview'])->middleware('permission:admin.professionals.review')->name('professionals.verification.start');
    Route::post('professionals/{professional}/verification/verify', [ProfessionalController::class, 'verify'])->middleware('permission:admin.professionals.verify')->name('professionals.verification.verify');
    Route::post('professionals/{professional}/verification/reject', [ProfessionalController::class, 'reject'])->middleware('permission:admin.professionals.reject')->name('professionals.verification.reject');
});

Route::middleware(['auth', 'permission:categories.view'])->group(function (): void {
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('categories/{category}', [CategoryController::class, 'show'])->name('categories.show');
});
Route::middleware(['auth', 'permission:categories.manage'])->group(function (): void {
    Route::get('categories/create', [CategoryController::class, 'create'])->name('categories.create');
    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::get('categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
    Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::post('categories/{category}/archive', [CategoryController::class, 'archive'])->name('categories.archive');
});

Route::middleware(['auth', 'permission:services.view'])->group(function (): void {
    Route::get('services', [ServiceController::class, 'index'])->name('services.index');
    Route::get('services/{service}', [ServiceController::class, 'show'])->name('services.show');
});
Route::middleware('auth')->group(function (): void {
    Route::post('services/{service}/publish', [ServiceController::class, 'publish'])->middleware('permission:services.manage')->name('services.publish');
    Route::post('services/{service}/unpublish', [ServiceController::class, 'unpublish'])->middleware('permission:services.manage')->name('services.unpublish');
    Route::post('services/{service}/archive', [ServiceController::class, 'archive'])->middleware('permission:services.manage')->name('services.archive');
});

Route::middleware(['auth', 'permission:requests.view'])->group(function (): void {
    Route::get('service-requests', [ServiceRequestController::class, 'index'])->name('service-requests.index');
    Route::get('service-requests/{serviceRequest}', [ServiceRequestController::class, 'show'])->name('service-requests.show');
});
Route::middleware('auth')->group(function (): void {
    Route::post('service-requests/{serviceRequest}/cancel', [ServiceRequestController::class, 'cancel'])->middleware('permission:requests.manage')->name('service-requests.cancel');
    Route::post('service-requests/{serviceRequest}/reject', [ServiceRequestController::class, 'reject'])->middleware('permission:requests.manage')->name('service-requests.reject');
});

Route::middleware(['auth', 'permission:orders.view'])->group(function (): void {
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
});
Route::middleware(['auth', 'permission:payments.view'])->group(function (): void {
    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
});

Route::middleware(['auth', 'permission:support.manage'])->group(function (): void {
    Route::get('support', [SupportController::class, 'index'])->name('support.index');
    Route::get('support/{ticket}', [SupportController::class, 'show'])->name('support.show');
    Route::post('support/{ticket}/assign', [SupportController::class, 'assign'])->name('support.assign');
    Route::patch('support/{ticket}', [SupportController::class, 'update'])->name('support.update');
    Route::post('support/{ticket}/message', [SupportController::class, 'message'])->name('support.message');
    Route::post('support/{ticket}/resolve', [SupportController::class, 'resolve'])->name('support.resolve');
    Route::post('support/{ticket}/close', [SupportController::class, 'close'])->name('support.close');
});

Route::middleware(['auth', 'permission:audit.view'])->group(function (): void {
    Route::get('audit', [AuditController::class, 'index'])->name('audit.index');
    Route::get('audit/{auditLog}', [AuditController::class, 'show'])->name('audit.show');
});

Route::middleware(['auth', 'permission:admin.dashboard.view'])->group(function (): void {
    Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics');
});

Route::middleware(['auth', 'permission:admin.professionals.view'])->group(function (): void {
    Route::get('verification', [VerificationController::class, 'index'])->name('verification.index');
    Route::get('verification/{professional}', [VerificationController::class, 'show'])->name('verification.show');
});

Route::middleware(['auth', 'permission:reports.manage'])->group(function (): void {
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');
    Route::post('reports/{report}/assign', [ReportController::class, 'assign'])->name('reports.assign');
    Route::post('reports/{report}/start-review', [ReportController::class, 'startReview'])->name('reports.start-review');
    Route::post('reports/{report}/moderate', [ReportController::class, 'moderate'])->name('reports.moderate');
    Route::post('reports/{report}/resolve', [ReportController::class, 'resolve'])->name('reports.resolve');
    Route::post('reports/{report}/dismiss', [ReportController::class, 'dismiss'])->name('reports.dismiss');
});
