<?php

use App\Http\Controllers\App\ActivityController;
use App\Http\Controllers\App\EmailLogController;
use App\Http\Controllers\App\UiPreferenceController;
use App\Http\Controllers\App\Auth\AuthenticatedSessionController;
use App\Http\Controllers\App\AssetManagementController;
use App\Http\Controllers\App\BudgetAccountController;
use App\Http\Controllers\App\BudgetTransactionHistoryController;
use App\Http\Controllers\App\BudgetTransferController;
use App\Http\Controllers\App\ChartController;
use App\Http\Controllers\App\ComingSoonController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\DepartmentController;
use App\Http\Controllers\App\ItemController;
use App\Http\Controllers\App\LocationController;
use App\Http\Controllers\App\PettyCashReimbursmentController;
use App\Http\Controllers\App\ProfileController;
use App\Http\Controllers\App\ProjectController;
use App\Http\Controllers\App\PurchaseOrderController;
use App\Http\Controllers\App\PurchaseRequestController;
use App\Http\Controllers\App\ReportController;
use App\Http\Controllers\App\RoleController;
use App\Http\Controllers\App\UserController;
use App\Http\Controllers\App\VendorController;
use Illuminate\Support\Facades\Route;

Route::prefix('app')->name('app.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    });

    Route::middleware('auth')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('profile', [ProfileController::class, 'edit'])->name('profile');
        Route::post('profile/signature', [ProfileController::class, 'updateSignature'])->name('profile.signature');
        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

        Route::get('items', [ItemController::class, 'index'])->name('items.index');
        Route::get('items/create', [ItemController::class, 'create'])->name('items.create');
        Route::post('items', [ItemController::class, 'store'])->name('items.store');
        Route::get('items/{item}/edit', [ItemController::class, 'edit'])->name('items.edit');
        Route::put('items/{item}', [ItemController::class, 'update'])->name('items.update');
        Route::delete('items/{item}', [ItemController::class, 'destroy'])->name('items.destroy');
        Route::delete('items-bulk', [ItemController::class, 'destroyMany'])->name('items.destroy-many');

        foreach ([
            'locations' => LocationController::class,
            'projects' => ProjectController::class,
            'departments' => DepartmentController::class,
            'vendors' => VendorController::class,
        ] as $resource => $controller) {
            Route::get($resource, [$controller, 'index'])->name("{$resource}.index");
            Route::get("{$resource}/create", [$controller, 'create'])->name("{$resource}.create");
            Route::post($resource, [$controller, 'store'])->name("{$resource}.store");
            Route::get("{$resource}/{id}/edit", [$controller, 'edit'])->name("{$resource}.edit");
            Route::put("{$resource}/{id}", [$controller, 'update'])->name("{$resource}.update");
            Route::delete("{$resource}/{id}", [$controller, 'destroy'])->name("{$resource}.destroy");
            Route::delete("{$resource}-bulk", [$controller, 'destroyMany'])->name("{$resource}.destroy-many");
        }

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::post('users/{user}/hod', [UserController::class, 'associateHod'])->name('users.hod.associate');
        Route::delete('users/{user}/hod/{department}', [UserController::class, 'dissociateHod'])->name('users.hod.dissociate');
        Route::delete('users/{user}/hod-bulk', [UserController::class, 'dissociateHodMany'])->name('users.hod.dissociate-many');

        Route::get('budget-accounts', [BudgetAccountController::class, 'index'])->name('budget-accounts.index');
        Route::get('budget-accounts/create', [BudgetAccountController::class, 'create'])->name('budget-accounts.create');
        Route::post('budget-accounts', [BudgetAccountController::class, 'store'])->name('budget-accounts.store');
        Route::get('budget-accounts/{budgetAccount}/edit', [BudgetAccountController::class, 'edit'])->name('budget-accounts.edit');
        Route::put('budget-accounts/{budgetAccount}', [BudgetAccountController::class, 'update'])->name('budget-accounts.update');
        Route::delete('budget-accounts/{budgetAccount}', [BudgetAccountController::class, 'destroy'])->name('budget-accounts.destroy');
        Route::delete('budget-accounts-bulk', [BudgetAccountController::class, 'destroyMany'])->name('budget-accounts.destroy-many');
        Route::post('budget-accounts/{budgetAccount}/top-up', [BudgetAccountController::class, 'topUp'])->name('budget-accounts.top-up');
        Route::post('budget-accounts/{budgetAccount}/sub-budgets', [BudgetAccountController::class, 'storeSubBudget'])->name('budget-accounts.sub-budgets.store');
        Route::get('budget-accounts/{budgetAccount}/sub-budgets/{subBudget}/on-hold-purchase-requests', [BudgetAccountController::class, 'onHoldPurchaseRequests'])->name('budget-accounts.sub-budgets.on-hold-purchase-requests');
        Route::put('budget-accounts/{budgetAccount}/sub-budgets/{subBudget}', [BudgetAccountController::class, 'updateSubBudget'])->name('budget-accounts.sub-budgets.update');
        Route::delete('budget-accounts/{budgetAccount}/sub-budgets/{subBudget}', [BudgetAccountController::class, 'destroySubBudget'])->name('budget-accounts.sub-budgets.destroy');
        Route::delete('budget-accounts/{budgetAccount}/sub-budgets-bulk', [BudgetAccountController::class, 'destroySubBudgetsMany'])->name('budget-accounts.sub-budgets.destroy-many');

        Route::get('budget-transfers', [BudgetTransferController::class, 'index'])->name('budget-transfers.index');
        Route::get('budget-transfers/create', [BudgetTransferController::class, 'create'])->name('budget-transfers.create');
        Route::post('budget-transfers', [BudgetTransferController::class, 'store'])->name('budget-transfers.store');
        Route::delete('budget-transfers-bulk', [BudgetTransferController::class, 'destroyMany'])->name('budget-transfers.destroy-many');

        Route::get('budget-transaction-histories', [BudgetTransactionHistoryController::class, 'index'])->name('budget-transaction-histories.index');
        Route::delete('budget-transaction-histories-bulk', [BudgetTransactionHistoryController::class, 'destroyMany'])->name('budget-transaction-histories.destroy-many');

        Route::get('purchase-requests', [PurchaseRequestController::class, 'index'])->name('purchase-requests.index');
        Route::get('purchase-requests/create', [PurchaseRequestController::class, 'create'])->name('purchase-requests.create');
        Route::post('purchase-requests', [PurchaseRequestController::class, 'store'])->name('purchase-requests.store');
        Route::get('purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'show'])->name('purchase-requests.show');
        Route::get('purchase-requests/{purchaseRequest}/audit', [PurchaseRequestController::class, 'audit'])->name('purchase-requests.audit');
        Route::get('purchase-requests/{purchaseRequest}/edit', [PurchaseRequestController::class, 'edit'])->name('purchase-requests.edit');
        Route::match(['put', 'post'], 'purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'update'])->name('purchase-requests.update');
        Route::delete('purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'destroy'])->name('purchase-requests.destroy');
        Route::post('purchase-requests/{purchaseRequest}/submit', [PurchaseRequestController::class, 'submit'])->name('purchase-requests.submit');
        Route::post('purchase-requests/{purchaseRequest}/hod-approve', [PurchaseRequestController::class, 'hodApprove'])->name('purchase-requests.hod-approve');
        Route::post('purchase-requests/{purchaseRequest}/hod-reject', [PurchaseRequestController::class, 'hodReject'])->name('purchase-requests.hod-reject');
        Route::post('purchase-requests/{purchaseRequest}/finance-approve', [PurchaseRequestController::class, 'financeApprove'])->name('purchase-requests.finance-approve');
        Route::post('purchase-requests/{purchaseRequest}/finance-reject', [PurchaseRequestController::class, 'financeReject'])->name('purchase-requests.finance-reject');
        Route::post('purchase-requests/{purchaseRequest}/cancel', [PurchaseRequestController::class, 'cancel'])->name('purchase-requests.cancel');
        Route::post('purchase-requests/{purchaseRequest}/send-back', [PurchaseRequestController::class, 'sendBack'])->name('purchase-requests.send-back');
        Route::post('purchase-requests/{purchaseRequest}/md-dmd-approve', [PurchaseRequestController::class, 'mdDmdApprove'])->name('purchase-requests.md-dmd-approve');
        Route::post('purchase-requests/{purchaseRequest}/md-dmd-reject', [PurchaseRequestController::class, 'mdDmdReject'])->name('purchase-requests.md-dmd-reject');
        Route::post('purchase-requests/{purchaseRequest}/close', [PurchaseRequestController::class, 'close'])->name('purchase-requests.close');
        Route::post('purchase-requests/{purchaseRequest}/details', [PurchaseRequestController::class, 'storeDetail'])->name('purchase-requests.details.store');
        Route::put('purchase-requests/{purchaseRequest}/details/{detail}', [PurchaseRequestController::class, 'updateDetail'])->name('purchase-requests.details.update');
        Route::delete('purchase-requests/{purchaseRequest}/details/{detail}', [PurchaseRequestController::class, 'destroyDetail'])->name('purchase-requests.details.destroy');

        Route::get('purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
        Route::get('purchase-orders/create', [PurchaseOrderController::class, 'create'])->name('purchase-orders.create');
        Route::post('purchase-orders', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store');
        Route::get('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show');
        Route::get('purchase-orders/{purchaseOrder}/audit', [PurchaseOrderController::class, 'audit'])->name('purchase-orders.audit');
        Route::get('purchase-orders/{purchaseOrder}/edit', [PurchaseOrderController::class, 'edit'])->name('purchase-orders.edit');
        Route::put('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('purchase-orders.update');
        Route::delete('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])->name('purchase-orders.destroy');
        Route::post('purchase-orders/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit'])->name('purchase-orders.submit');
        Route::post('purchase-orders/{purchaseOrder}/close', [PurchaseOrderController::class, 'close'])->name('purchase-orders.close');
        Route::post('purchase-orders/{purchaseOrder}/upload-receipt', [PurchaseOrderController::class, 'uploadReceipt'])->name('purchase-orders.upload-receipt');
        Route::post('purchase-orders/{purchaseOrder}/advance-form/generate', [PurchaseOrderController::class, 'generateAdvanceForm'])->name('purchase-orders.advance-form.generate');
        Route::post('purchase-orders/{purchaseOrder}/advance-form/regenerate', [PurchaseOrderController::class, 'regenerateAdvanceForm'])->name('purchase-orders.advance-form.regenerate');
        Route::post('purchase-orders/{purchaseOrder}/advance-form/submit', [PurchaseOrderController::class, 'submitAdvanceForm'])->name('purchase-orders.advance-form.submit');
        Route::post('purchase-orders/{purchaseOrder}/advance-form/hod-approve', [PurchaseOrderController::class, 'hodApproveAdvanceForm'])->name('purchase-orders.advance-form.hod-approve');
        Route::post('purchase-orders/{purchaseOrder}/advance-form/hod-reject', [PurchaseOrderController::class, 'hodRejectAdvanceForm'])->name('purchase-orders.advance-form.hod-reject');
        Route::post('purchase-orders/{purchaseOrder}/advance-form/md-dmd-approve', [PurchaseOrderController::class, 'mdDmdApproveAdvanceForm'])->name('purchase-orders.advance-form.md-dmd-approve');
        Route::post('purchase-orders/{purchaseOrder}/advance-form/md-dmd-reject', [PurchaseOrderController::class, 'mdDmdRejectAdvanceForm'])->name('purchase-orders.advance-form.md-dmd-reject');
        Route::post('purchase-orders/{purchaseOrder}/details', [PurchaseOrderController::class, 'storeDetail'])->name('purchase-orders.details.store');
        Route::put('purchase-orders/{purchaseOrder}/details/{detail}', [PurchaseOrderController::class, 'updateDetail'])->name('purchase-orders.details.update');
        Route::delete('purchase-orders/{purchaseOrder}/details/{detail}', [PurchaseOrderController::class, 'destroyDetail'])->name('purchase-orders.details.destroy');

        Route::get('petty-cash', [PettyCashReimbursmentController::class, 'index'])->name('petty-cash.index');
        Route::get('petty-cash/create', [PettyCashReimbursmentController::class, 'create'])->name('petty-cash.create');
        Route::post('petty-cash', [PettyCashReimbursmentController::class, 'store'])->name('petty-cash.store');
        Route::get('petty-cash/{pettyCashReimbursment}', [PettyCashReimbursmentController::class, 'show'])->name('petty-cash.show');
        Route::get('petty-cash/{pettyCashReimbursment}/edit', [PettyCashReimbursmentController::class, 'edit'])->name('petty-cash.edit');
        Route::post('petty-cash/{pettyCashReimbursment}', [PettyCashReimbursmentController::class, 'update'])->name('petty-cash.update');
        Route::delete('petty-cash/{pettyCashReimbursment}', [PettyCashReimbursmentController::class, 'destroy'])->name('petty-cash.destroy');
        Route::delete('petty-cash-bulk', [PettyCashReimbursmentController::class, 'destroyMany'])->name('petty-cash.destroy-many');

        Route::post('petty-cash/{pettyCashReimbursment}/submit', [PettyCashReimbursmentController::class, 'submit'])->name('petty-cash.submit');
        Route::post('petty-cash/{pettyCashReimbursment}/approve-department', [PettyCashReimbursmentController::class, 'approveDepartment'])->name('petty-cash.approve-department');
        Route::post('petty-cash/{pettyCashReimbursment}/reject-department', [PettyCashReimbursmentController::class, 'rejectDepartment'])->name('petty-cash.reject-department');
        Route::post('petty-cash/{pettyCashReimbursment}/add-pv', [PettyCashReimbursmentController::class, 'addPv'])->name('petty-cash.add-pv');
        Route::post('petty-cash/{pettyCashReimbursment}/approve-finance', [PettyCashReimbursmentController::class, 'approveFinance'])->name('petty-cash.approve-finance');
        Route::post('petty-cash/{pettyCashReimbursment}/reject-finance', [PettyCashReimbursmentController::class, 'rejectFinance'])->name('petty-cash.reject-finance');

        Route::post('petty-cash/{pettyCashReimbursment}/details', [PettyCashReimbursmentController::class, 'storeDetail'])->name('petty-cash.details.store');
        Route::put('petty-cash/{pettyCashReimbursment}/details/{detail}', [PettyCashReimbursmentController::class, 'updateDetail'])->name('petty-cash.details.update');
        Route::delete('petty-cash/{pettyCashReimbursment}/details/{detail}', [PettyCashReimbursmentController::class, 'destroyDetail'])->name('petty-cash.details.destroy');

        Route::get('asset-management', [AssetManagementController::class, 'index'])->name('asset-management.index');
        Route::get('asset-management/{purchaseOrder}', [AssetManagementController::class, 'show'])->name('asset-management.show');
        Route::post('asset-management/{purchaseOrder}/receipts/{receipt}/receive', [AssetManagementController::class, 'receive'])->name('asset-management.receive');
        Route::post('asset-management/{purchaseOrder}/bulk-receive', [AssetManagementController::class, 'bulkReceive'])->name('asset-management.bulk-receive');
        Route::post('asset-management/check-serial', [AssetManagementController::class, 'checkSerial'])->name('asset-management.check-serial');

        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/create', [ReportController::class, 'create'])->name('reports.create');
        Route::post('reports', [ReportController::class, 'store'])->name('reports.store');
        Route::get('reports/{report}/edit', [ReportController::class, 'edit'])->name('reports.edit');
        Route::put('reports/{report}', [ReportController::class, 'update'])->name('reports.update');
        Route::delete('reports/{report}', [ReportController::class, 'destroy'])->name('reports.destroy');
        Route::delete('reports-bulk', [ReportController::class, 'destroyMany'])->name('reports.destroy-many');
        Route::get('reports/{report}/download', [ReportController::class, 'download'])->name('reports.download');

        Route::get('charts', [ChartController::class, 'index'])->name('charts.index');
        Route::get('charts/create', [ChartController::class, 'create'])->name('charts.create');
        Route::post('charts/preview', [ChartController::class, 'preview'])->name('charts.preview');
        Route::post('charts', [ChartController::class, 'store'])->name('charts.store');
        Route::get('charts/{chart}/edit', [ChartController::class, 'edit'])->name('charts.edit');
        Route::put('charts/{chart}', [ChartController::class, 'update'])->name('charts.update');
        Route::delete('charts/{chart}', [ChartController::class, 'destroy'])->name('charts.destroy');
        Route::delete('charts-bulk', [ChartController::class, 'destroyMany'])->name('charts.destroy-many');

        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
        Route::delete('roles-bulk', [RoleController::class, 'destroyMany'])->name('roles.destroy-many');

        Route::get('activity', [ActivityController::class, 'index'])->name('activity.index');
        Route::delete('activity/{activity}', [ActivityController::class, 'destroy'])->name('activity.destroy');
        Route::delete('activity-bulk', [ActivityController::class, 'destroyMany'])->name('activity.destroy-many');

        Route::get('emails', [EmailLogController::class, 'index'])->name('emails.index');

        Route::put('preferences/pinned-tab', [UiPreferenceController::class, 'updatePinnedTab'])->name('preferences.pinned-tab');

        Route::get('modules/{module}', ComingSoonController::class)->name('coming-soon');
    });
});
