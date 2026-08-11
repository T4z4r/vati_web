<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\ComplianceController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\GroupController;
use App\Http\Controllers\Web\LoanApplicationController;
use App\Http\Controllers\Web\LoanController;
use App\Http\Controllers\Web\LoanProductController;
use App\Http\Controllers\Web\MemberController;
use App\Http\Controllers\Web\OrganizationController;
use App\Http\Controllers\Web\PaymentController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\SecurityController;
use App\Http\Controllers\Web\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('admin.dashboard') : view('auth.login'))->name('home');
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'create'])->name('login');
    Route::post('login', [AuthController::class, 'store'])->name('login.store')->middleware('throttle:5,1');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'branch.access'])->group(function () {
    Route::post('logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard')->middleware('permission:view-dashboard');

    Route::middleware('role:super_admin|head_office_admin')->group(function () {
        Route::get('organization', [OrganizationController::class, 'index'])->name('organization.index');
        Route::post('regions', [OrganizationController::class, 'storeRegion'])->name('regions.store');
        Route::post('areas', [OrganizationController::class, 'storeArea'])->name('areas.store');
        Route::post('branches', [OrganizationController::class, 'storeBranch'])->name('branches.store');
        Route::resource('users', UserController::class)->except(['edit', 'update', 'destroy']);
    });

    Route::resource('groups', GroupController::class)->only(['index', 'show'])->middleware('permission:view-groups');
    Route::resource('groups', GroupController::class)->only(['create', 'store'])->middleware('permission:create-groups');
    Route::resource('members', MemberController::class)->only(['index', 'show'])->middleware('permission:view-members');
    Route::resource('members', MemberController::class)->only(['create', 'store'])->middleware('permission:create-members');
    Route::put('members/{member}/kyc', [MemberController::class, 'updateKyc'])->name('members.kyc.update')->middleware('permission:edit-members');
    Route::resource('loan-products', LoanProductController::class)->only(['index', 'show'])->middleware('permission:view-loan-products');
    Route::resource('loan-products', LoanProductController::class)->only(['create', 'store', 'edit', 'update'])->middleware('permission:manage-loan-products');

    Route::resource('loan-applications', LoanApplicationController::class)->only(['index', 'show'])->middleware('permission:view-loan-applications');
    Route::resource('loan-applications', LoanApplicationController::class)->only(['create', 'store'])->middleware('permission:create-loan-applications');
    Route::post('loan-applications/{loanApplication}/submit', [LoanApplicationController::class, 'submit'])->name('loan-applications.submit')->middleware('permission:create-loan-applications');
    Route::post('loan-applications/{loanApplication}/witnesses', [LoanApplicationController::class, 'witness'])->name('loan-applications.witnesses.store')->middleware('permission:manage-group-witnesses');
    Route::post('loan-applications/{loanApplication}/approve', [LoanApplicationController::class, 'approve'])->name('loan-applications.approve')->middleware('permission:approve-loan-applications');
    Route::post('loan-applications/{loanApplication}/reject', [LoanApplicationController::class, 'reject'])->name('loan-applications.reject')->middleware('permission:reject-loan-applications');
    Route::put('loan-applications/{loanApplication}/compliance/applicant', [ComplianceController::class, 'applicant'])->name('loan-applications.compliance.applicant')->middleware('permission:manage-loan-compliance');
    Route::post('loan-applications/{loanApplication}/compliance/guarantors', [ComplianceController::class, 'guarantor'])->name('loan-applications.compliance.guarantors')->middleware('permission:manage-loan-compliance');
    Route::put('loan-applications/{loanApplication}/compliance/nominees', [ComplianceController::class, 'nominees'])->name('loan-applications.compliance.nominees')->middleware('permission:manage-loan-compliance');
    Route::post('loan-applications/{loanApplication}/compliance/documents', [ComplianceController::class, 'document'])->name('loan-applications.compliance.documents')->middleware('permission:manage-loan-compliance');
    Route::post('loan-applications/{loanApplication}/compliance/documents/{loanDocument}/verify', [ComplianceController::class, 'verifyDocument'])->name('loan-applications.compliance.documents.verify')->middleware('permission:verify-loan-documents');
    Route::post('loan-applications/{loanApplication}/cancel', [ComplianceController::class, 'cancel'])->name('loan-applications.cancel')->middleware('permission:create-loan-applications');

    Route::get('loans', [LoanController::class, 'index'])->name('loans.index')->middleware('permission:view-loans');
    Route::get('loans/{loan}', [LoanController::class, 'show'])->name('loans.show')->middleware('permission:view-loans');
    Route::post('loans/{loan}/disburse', [LoanController::class, 'disburse'])->name('loans.disburse')->middleware('permission:disburse-loans');
    Route::post('loans/{loan}/settle', [LoanController::class, 'settle'])->name('loans.settle')->middleware('permission:settle-loans');
    Route::post('loans/{loan}/payments', [PaymentController::class, 'store'])->name('payments.store')->middleware('permission:collect-payments');
    Route::post('payments/{payment}/reverse', [PaymentController::class, 'reverse'])->name('payments.reverse')->middleware('permission:reverse-payments');
    Route::post('members/{member}/security', [SecurityController::class, 'store'])->name('security.store')->middleware('permission:manage-security');
    Route::post('members/{member}/passbook-replacements', [ComplianceController::class, 'passbook'])->name('members.passbook-replacements.store')->middleware('permission:replace-passbooks');
    Route::post('loans/{loan}/default-notices', [ComplianceController::class, 'defaultNotice'])->name('loans.default-notices.store')->middleware('permission:issue-default-notices');
    Route::post('loans/{loan}/clearance', [ComplianceController::class, 'clearance'])->name('loans.clearance.store')->middleware('permission:authorize-loan-clearances');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index')->middleware('permission:view-reports');
});
