<?php

use App\Http\Controllers\Api\V1\ApplicationComplianceController;
use App\Http\Controllers\Api\V1\AreaController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\GroupController;
use App\Http\Controllers\Api\V1\GroupPortfolioController;
use App\Http\Controllers\Api\V1\LoanAdministrationController;
use App\Http\Controllers\Api\V1\LoanApplicationController;
use App\Http\Controllers\Api\V1\LoanApplicationWorkflowController;
use App\Http\Controllers\Api\V1\LoanCalculatorController;
use App\Http\Controllers\Api\V1\LoanController;
use App\Http\Controllers\Api\V1\LoanDisbursementController;
use App\Http\Controllers\Api\V1\LoanGroupWitnessController;
use App\Http\Controllers\Api\V1\LoanProductController;
use App\Http\Controllers\Api\V1\LoanSettlementController;
use App\Http\Controllers\Api\V1\MemberController;
use App\Http\Controllers\Api\V1\MemberKycController;
use App\Http\Controllers\Api\V1\MemberPassbookController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\RegionController;
use App\Http\Controllers\Api\V1\SecurityAccountController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::middleware(['auth:sanctum', 'branch.access'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('profile', [AuthController::class, 'profile']);
        Route::get('dashboard', [DashboardController::class, 'index'])->middleware('permission:view-dashboard');

        Route::post('onboarding/groups', [OnboardingController::class, 'group'])->middleware('permission:create-groups');
        Route::post('onboarding/members', [OnboardingController::class, 'member'])->middleware('permission:create-members');
        Route::post('onboarding/loan-applications', [OnboardingController::class, 'loanApplication'])->middleware('permission:create-loan-applications');

        Route::apiResource('regions', RegionController::class)->middleware('permission:manage-organization');
        Route::apiResource('areas', AreaController::class)->middleware('permission:manage-organization');
        Route::apiResource('branches', BranchController::class)->middleware('permission:manage-organization');
        Route::get('roles', [UserController::class, 'roles'])->middleware('permission:view-users');
        Route::apiResource('users', UserController::class)->middleware('permission:manage-users');
        Route::apiResource('groups', GroupController::class)->only(['index', 'show'])->middleware('permission:view-groups');
        Route::apiResource('groups', GroupController::class)->only('store')->middleware('permission:create-groups');
        Route::apiResource('groups', GroupController::class)->only(['update', 'destroy'])->middleware('permission:edit-groups');
        Route::get('groups/{group}/members', [GroupController::class, 'members'])->middleware('permission:view-groups');
        Route::get('groups/{group}/dashboard', [GroupPortfolioController::class, 'dashboard'])->middleware('permission:view-group-portfolio');
        Route::get('groups/{group}/loans', [GroupPortfolioController::class, 'loans'])->middleware('permission:view-group-portfolio');
        Route::get('groups/{group}/applications', [GroupPortfolioController::class, 'applications'])->middleware('permission:view-group-portfolio');
        Route::get('groups/{group}/collections', [GroupPortfolioController::class, 'collections'])->middleware('permission:view-group-portfolio');
        Route::get('groups/{group}/meetings', [GroupPortfolioController::class, 'meetings'])->middleware('permission:view-group-portfolio');
        Route::apiResource('members', MemberController::class)->only(['index', 'show'])->middleware('permission:view-members');
        Route::apiResource('members', MemberController::class)->only('store')->middleware('permission:create-members');
        Route::apiResource('members', MemberController::class)->only('update')->middleware('permission:edit-members');
        Route::apiResource('members', MemberController::class)->only('destroy')->middleware('permission:delete-members');
        Route::put('members/{member}/kyc', [MemberKycController::class, 'update'])->middleware('permission:edit-members');
        Route::get('members/{member}/passbook', [MemberPassbookController::class, 'show'])->middleware('permission:view-members');
        Route::get('members/{member}/security', [SecurityAccountController::class, 'show'])->middleware('permission:view-security');
        Route::post('members/{member}/security-transactions', [SecurityAccountController::class, 'store'])->middleware('permission:manage-security');

        Route::apiResource('loan-products', LoanProductController::class)->only(['index', 'show'])->middleware('permission:view-loan-products');
        Route::apiResource('loan-products', LoanProductController::class)->only(['store', 'update', 'destroy'])->middleware('permission:manage-loan-products');
        Route::post('loan-calculator', [LoanCalculatorController::class, 'calculate'])->middleware('permission:view-loan-products');
        Route::apiResource('loan-applications', LoanApplicationController::class)->only(['index', 'show'])->middleware('permission:view-loan-applications');
        Route::apiResource('loan-applications', LoanApplicationController::class)->only(['store', 'update', 'destroy'])->middleware('permission:create-loan-applications');
        Route::post('loan-applications/{loanApplication}/submit', [LoanApplicationWorkflowController::class, 'submit'])->middleware('permission:create-loan-applications');
        Route::post('loan-applications/{loanApplication}/approve', [LoanApplicationWorkflowController::class, 'approve'])->middleware('permission:approve-loan-applications');
        Route::post('loan-applications/{loanApplication}/reject', [LoanApplicationWorkflowController::class, 'reject'])->middleware('permission:reject-loan-applications');
        Route::put('loan-applications/{loanApplication}/compliance/applicant', [ApplicationComplianceController::class, 'applicant'])->middleware('permission:manage-loan-compliance');
        Route::post('loan-applications/{loanApplication}/compliance/guarantors', [ApplicationComplianceController::class, 'guarantor'])->middleware('permission:manage-loan-compliance');
        Route::put('loan-applications/{loanApplication}/compliance/nominees', [ApplicationComplianceController::class, 'nominees'])->middleware('permission:manage-loan-compliance');
        Route::post('loan-applications/{loanApplication}/compliance/documents', [ApplicationComplianceController::class, 'document'])->middleware('permission:manage-loan-compliance');
        Route::post('loan-applications/{loanApplication}/compliance/documents/{loanDocument}/verify', [ApplicationComplianceController::class, 'verifyDocument'])->middleware('permission:verify-loan-documents');
        Route::post('loan-applications/{loanApplication}/cancel', [ApplicationComplianceController::class, 'cancel'])->middleware('permission:create-loan-applications');
        Route::get('loan-applications/{loanApplication}/group-witnesses', [LoanGroupWitnessController::class, 'index'])->middleware('permission:view-group-witnesses');
        Route::post('loan-applications/{loanApplication}/group-witnesses', [LoanGroupWitnessController::class, 'store'])->middleware('permission:manage-group-witnesses');

        Route::get('loans', [LoanController::class, 'index'])->middleware('permission:view-loans');
        Route::get('loans/{loan}', [LoanController::class, 'show'])->middleware('permission:view-loans');
        Route::get('loans/{loan}/schedule', [LoanController::class, 'schedule'])->middleware('permission:view-loans');
        Route::post('loans/{loan}/disburse', [LoanDisbursementController::class, 'store'])->middleware('permission:disburse-loans');
        Route::post('loans/{loan}/payments', [PaymentController::class, 'store'])->middleware('permission:collect-payments');
        Route::post('payments/{payment}/reverse', [PaymentController::class, 'reverse'])->middleware('permission:reverse-payments');
        Route::post('loans/{loan}/settle', [LoanSettlementController::class, 'store'])->middleware('permission:settle-loans');
        Route::post('members/{member}/passbook-replacements', [LoanAdministrationController::class, 'replacePassbook'])->middleware('permission:replace-passbooks');
        Route::post('loans/{loan}/default-notices', [LoanAdministrationController::class, 'defaultNotice'])->middleware('permission:issue-default-notices');
        Route::post('loans/{loan}/clearance', [LoanAdministrationController::class, 'clearance'])->middleware('permission:authorize-loan-clearances');
    });
});
