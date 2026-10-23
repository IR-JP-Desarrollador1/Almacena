<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\CostCenterController;
use App\Http\Controllers\Admin\CurrencyController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\RegionalSettingsController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\StorageLocationController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\SupplierEvaluationController;
use App\Http\Controllers\Admin\UnitController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WarehouseController;
use App\Http\Controllers\Inventory\InventoryMovementController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Purchasing\PurchaseOrderController;
use App\Http\Controllers\Purchasing\QuoteComparisonController;
use App\Http\Controllers\Purchasing\RequisitionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'active'])->name('dashboard');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.view')->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->middleware('permission:users.manage')->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.manage')->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->middleware('permission:users.manage')->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:users.manage')->name('users.update');
        Route::post('/users/{user}/invitation', [UserController::class, 'sendInvitation'])->middleware('permission:users.manage')->name('users.invitation');

        Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:roles.manage')->name('roles.index');
        Route::get('/roles/create', [RoleController::class, 'create'])->middleware('permission:roles.manage')->name('roles.create');
        Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:roles.manage')->name('roles.store');
        Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->middleware('permission:roles.manage')->name('roles.edit');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.manage')->name('roles.update');

        Route::get('/company', [CompanyController::class, 'edit'])->middleware('permission:companies.manage')->name('company.edit');
        Route::put('/company', [CompanyController::class, 'update'])->middleware('permission:companies.manage')->name('company.update');
        Route::get('/regional-settings', [RegionalSettingsController::class, 'edit'])->middleware('permission:companies.manage')->name('regional-settings.edit');
        Route::put('/regional-settings', [RegionalSettingsController::class, 'update'])->middleware('permission:companies.manage')->name('regional-settings.update');
        Route::get('/audit', [AuditLogController::class, 'index'])->middleware('permission:audit.view')->name('audit.index');

        Route::middleware('permission:organization.view|organization.manage')->group(function () {
            Route::get('/organization/branches', [BranchController::class, 'index'])->name('organization.branches.index');
            Route::get('/organization/branches/create', [BranchController::class, 'create'])->middleware('permission:organization.manage')->name('organization.branches.create');
            Route::post('/organization/branches', [BranchController::class, 'store'])->middleware('permission:organization.manage')->name('organization.branches.store');
            Route::get('/organization/branches/{branch}/edit', [BranchController::class, 'edit'])->middleware('permission:organization.manage')->name('organization.branches.edit');
            Route::put('/organization/branches/{branch}', [BranchController::class, 'update'])->middleware('permission:organization.manage')->name('organization.branches.update');

            Route::get('/organization/warehouses', [WarehouseController::class, 'index'])->name('organization.warehouses.index');
            Route::get('/organization/warehouses/create', [WarehouseController::class, 'create'])->middleware('permission:organization.manage')->name('organization.warehouses.create');
            Route::post('/organization/warehouses', [WarehouseController::class, 'store'])->middleware('permission:organization.manage')->name('organization.warehouses.store');
            Route::get('/organization/warehouses/{warehouse}/edit', [WarehouseController::class, 'edit'])->middleware('permission:organization.manage')->name('organization.warehouses.edit');
            Route::put('/organization/warehouses/{warehouse}', [WarehouseController::class, 'update'])->middleware('permission:organization.manage')->name('organization.warehouses.update');

            Route::get('/organization/locations', [StorageLocationController::class, 'index'])->name('organization.locations.index');
            Route::get('/organization/locations/create', [StorageLocationController::class, 'create'])->middleware('permission:organization.manage')->name('organization.locations.create');
            Route::post('/organization/locations', [StorageLocationController::class, 'store'])->middleware('permission:organization.manage')->name('organization.locations.store');
            Route::get('/organization/locations/{storageLocation}/edit', [StorageLocationController::class, 'edit'])->middleware('permission:organization.manage')->name('organization.locations.edit');
            Route::put('/organization/locations/{storageLocation}', [StorageLocationController::class, 'update'])->middleware('permission:organization.manage')->name('organization.locations.update');

            Route::get('/organization/cost-centers', [CostCenterController::class, 'index'])->name('organization.cost-centers.index');
            Route::get('/organization/cost-centers/create', [CostCenterController::class, 'create'])->middleware('permission:organization.manage')->name('organization.cost-centers.create');
            Route::post('/organization/cost-centers', [CostCenterController::class, 'store'])->middleware('permission:organization.manage')->name('organization.cost-centers.store');
            Route::get('/organization/cost-centers/{costCenter}/edit', [CostCenterController::class, 'edit'])->middleware('permission:organization.manage')->name('organization.cost-centers.edit');
            Route::put('/organization/cost-centers/{costCenter}', [CostCenterController::class, 'update'])->middleware('permission:organization.manage')->name('organization.cost-centers.update');
        });

        Route::middleware('permission:departments.view|departments.manage')->group(function () {
            Route::get('/organization/departments', [DepartmentController::class, 'index'])->name('organization.departments.index');
            Route::get('/organization/departments/create', [DepartmentController::class, 'create'])->middleware('permission:departments.manage')->name('organization.departments.create');
            Route::post('/organization/departments', [DepartmentController::class, 'store'])->middleware('permission:departments.manage')->name('organization.departments.store');
            Route::get('/organization/departments/{department}/edit', [DepartmentController::class, 'edit'])->middleware('permission:departments.manage')->name('organization.departments.edit');
            Route::put('/organization/departments/{department}', [DepartmentController::class, 'update'])->middleware('permission:departments.manage')->name('organization.departments.update');
        });

        Route::middleware('permission:catalogs.view|catalogs.manage')->group(function () {
            Route::get('/catalogs/products', [ProductController::class, 'index'])->name('catalogs.products.index');
            Route::get('/catalogs/products/import', [ProductController::class, 'importForm'])->middleware('permission:catalogs.manage')->name('catalogs.products.import.form');
            Route::post('/catalogs/products/import', [ProductController::class, 'importFile'])->middleware('permission:catalogs.manage')->name('catalogs.products.import');
            Route::get('/catalogs/products/import/template/{format}', [ProductController::class, 'downloadTemplate'])->middleware('permission:catalogs.manage')->name('catalogs.products.import.template');
            Route::get('/catalogs/products/create', [ProductController::class, 'create'])->middleware('permission:catalogs.manage')->name('catalogs.products.create');
            Route::post('/catalogs/products', [ProductController::class, 'store'])->middleware('permission:catalogs.manage')->name('catalogs.products.store');
            Route::get('/catalogs/products/{product}/edit', [ProductController::class, 'edit'])->middleware('permission:catalogs.manage')->name('catalogs.products.edit');
            Route::put('/catalogs/products/{product}', [ProductController::class, 'update'])->middleware('permission:catalogs.manage')->name('catalogs.products.update');

            Route::get('/catalogs/units', [UnitController::class, 'index'])->name('catalogs.units.index');
            Route::get('/catalogs/units/create', [UnitController::class, 'create'])->middleware('permission:catalogs.manage')->name('catalogs.units.create');
            Route::post('/catalogs/units', [UnitController::class, 'store'])->middleware('permission:catalogs.manage')->name('catalogs.units.store');
            Route::get('/catalogs/units/{unit}/edit', [UnitController::class, 'edit'])->middleware('permission:catalogs.manage')->name('catalogs.units.edit');
            Route::put('/catalogs/units/{unit}', [UnitController::class, 'update'])->middleware('permission:catalogs.manage')->name('catalogs.units.update');

            Route::get('/catalogs/suppliers', [SupplierController::class, 'index'])->name('catalogs.suppliers.index');
            Route::get('/catalogs/suppliers/create', [SupplierController::class, 'create'])->middleware('permission:catalogs.manage')->name('catalogs.suppliers.create');
            Route::post('/catalogs/suppliers', [SupplierController::class, 'store'])->middleware('permission:catalogs.manage')->name('catalogs.suppliers.store');
            Route::get('/catalogs/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->middleware('permission:catalogs.manage')->name('catalogs.suppliers.edit');
            Route::put('/catalogs/suppliers/{supplier}', [SupplierController::class, 'update'])->middleware('permission:catalogs.manage')->name('catalogs.suppliers.update');
            Route::get('/catalogs/supplier-evaluations', [SupplierEvaluationController::class, 'index'])
                ->middleware('permission:catalogs.manage')
                ->name('catalogs.supplier-evaluations.index');
            Route::post('/catalogs/supplier-evaluations', [SupplierEvaluationController::class, 'store'])
                ->middleware('permission:catalogs.manage')
                ->name('catalogs.supplier-evaluations.store');

            Route::get('/catalogs/currencies', [CurrencyController::class, 'index'])->name('catalogs.currencies.index');
            Route::get('/catalogs/currencies/create', [CurrencyController::class, 'create'])->middleware('permission:catalogs.manage')->name('catalogs.currencies.create');
            Route::post('/catalogs/currencies', [CurrencyController::class, 'store'])->middleware('permission:catalogs.manage')->name('catalogs.currencies.store');
            Route::get('/catalogs/currencies/{currency}/edit', [CurrencyController::class, 'edit'])->middleware('permission:catalogs.manage')->name('catalogs.currencies.edit');
            Route::put('/catalogs/currencies/{currency}', [CurrencyController::class, 'update'])->middleware('permission:catalogs.manage')->name('catalogs.currencies.update');
        });
    });

    Route::middleware('permission:purchases.requisitions.view|purchases.requisitions.approve|purchases.requisitions.approve_any|purchases.requisitions.reject|inventory.requisitions.manage|inventory.requisitions.submit|purchases.quotes.view|purchases.quotes.manage|purchases.quotes.award|purchases.quotes.generate_orders|purchases.orders.view|purchases.orders.submit|purchases.orders.create_direct|purchases.orders.approve|purchases.orders.approve_any|purchases.orders.reject')
        ->prefix('purchasing')
        ->name('purchasing.')
        ->group(function () {
            Route::get('/requisitions', [RequisitionController::class, 'index'])
                ->middleware('permission:purchases.requisitions.view|purchases.requisitions.approve|purchases.requisitions.approve_any|purchases.requisitions.reject')
                ->name('requisitions.index');
            Route::get('/requisitions/create', [RequisitionController::class, 'create'])
                ->middleware('permission:inventory.requisitions.manage')
                ->name('requisitions.create');
            Route::post('/requisitions', [RequisitionController::class, 'store'])
                ->middleware('permission:inventory.requisitions.manage')
                ->name('requisitions.store');
            Route::get('/requisitions/{requisition}', [RequisitionController::class, 'show'])
                ->middleware('permission:purchases.requisitions.view|purchases.requisitions.approve|purchases.requisitions.approve_any|purchases.requisitions.reject|inventory.requisitions.manage|inventory.requisitions.submit|purchases.quotes.view|purchases.quotes.manage|purchases.quotes.award|purchases.quotes.generate_orders|purchases.orders.create_direct')
                ->name('requisitions.show');
            Route::get('/requisitions/{requisition}/edit', [RequisitionController::class, 'edit'])
                ->middleware('permission:inventory.requisitions.manage')
                ->name('requisitions.edit');
            Route::put('/requisitions/{requisition}', [RequisitionController::class, 'update'])
                ->middleware('permission:inventory.requisitions.manage')
                ->name('requisitions.update');
            Route::post('/requisitions/{requisition}/submit', [RequisitionController::class, 'submit'])
                ->middleware('permission:inventory.requisitions.submit')
                ->name('requisitions.submit');
            Route::post('/requisitions/{requisition}/approve', [RequisitionController::class, 'approve'])
                ->middleware('permission:purchases.requisitions.approve|purchases.requisitions.approve_any')
                ->name('requisitions.approve');
            Route::post('/requisitions/{requisition}/reject', [RequisitionController::class, 'reject'])
                ->middleware('permission:purchases.requisitions.reject')
                ->name('requisitions.reject');

            Route::get('/quotes', [QuoteComparisonController::class, 'registrationQueue'])
                ->middleware('permission:purchases.quotes.view|purchases.quotes.manage|purchases.quotes.award|purchases.quotes.generate_orders')
                ->name('quotes.queue');
            Route::get('/orders', [PurchaseOrderController::class, 'index'])
                ->middleware('permission:purchases.orders.view|purchases.quotes.generate_orders|purchases.orders.submit|purchases.orders.create_direct|purchases.orders.approve|purchases.orders.approve_any|purchases.orders.reject')
                ->name('orders.index');
            Route::get('/orders/create', [PurchaseOrderController::class, 'create'])
                ->middleware('permission:purchases.quotes.generate_orders')
                ->name('orders.create');
            Route::get('/orders/direct', [PurchaseOrderController::class, 'createDirect'])
                ->middleware('permission:purchases.orders.create_direct')
                ->name('orders.direct.create');
            Route::post('/orders/direct', [PurchaseOrderController::class, 'storeDirect'])
                ->middleware('permission:purchases.orders.create_direct')
                ->name('orders.direct.store');
            Route::get('/requisitions/{requisition}/quotes', [QuoteComparisonController::class, 'index'])
                ->middleware('permission:purchases.quotes.view|purchases.quotes.award|purchases.quotes.generate_orders')
                ->name('quotes.index');
            Route::get('/requisitions/{requisition}/quotes/register', [QuoteComparisonController::class, 'register'])
                ->middleware('permission:purchases.quotes.manage')
                ->name('quotes.register');
            Route::post('/requisitions/{requisition}/quotes', [QuoteComparisonController::class, 'store'])
                ->middleware('permission:purchases.quotes.manage')
                ->name('quotes.store');
            Route::post('/requisitions/{requisition}/quotes/select', [QuoteComparisonController::class, 'select'])
                ->middleware('permission:purchases.quotes.award')
                ->name('quotes.select');
            Route::post('/requisitions/{requisition}/quotes/generate-orders', [QuoteComparisonController::class, 'generateOrders'])
                ->middleware('permission:purchases.quotes.generate_orders')
                ->name('quotes.generate-orders');
            Route::get('/orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])
                ->middleware('permission:purchases.orders.view|purchases.quotes.generate_orders|purchases.orders.submit|purchases.orders.create_direct|purchases.orders.approve|purchases.orders.reject')
                ->name('orders.show');
            Route::get('/orders/{purchaseOrder}/edit', [PurchaseOrderController::class, 'edit'])
                ->middleware('permission:purchases.orders.submit')
                ->name('orders.edit');
            Route::put('/orders/{purchaseOrder}', [PurchaseOrderController::class, 'update'])
                ->middleware('permission:purchases.orders.submit')
                ->name('orders.update');
            Route::post('/orders/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit'])
                ->middleware('permission:purchases.orders.submit')
                ->name('orders.submit');
            Route::post('/orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])
                ->middleware('permission:purchases.orders.approve|purchases.orders.approve_any')
                ->name('orders.approve');
            Route::post('/orders/{purchaseOrder}/reject', [PurchaseOrderController::class, 'reject'])
                ->middleware('permission:purchases.orders.reject')
                ->name('orders.reject');
        });

    Route::prefix('inventory')->name('inventory.')
        ->middleware('permission:inventory.receipts.view|inventory.receipts.create|inventory.receipts.direct.create|inventory.issues.view|inventory.issues.create|inventory.issues.direct.create|inventory.requisitions.dispatch|inventory.movements.view|inventory.requisitions.view|inventory.requisitions.manage|inventory.requisitions.submit|purchases.requisitions.view')
        ->group(function () {
            Route::get('/requisitions', [RequisitionController::class, 'inventoryIndex'])
                ->middleware('permission:inventory.requisitions.view|inventory.requisitions.manage|inventory.requisitions.submit|inventory.requisitions.dispatch|inventory.issues.create')
                ->name('requisitions.index');
            Route::get('/requisitions/create', [RequisitionController::class, 'create'])
                ->middleware('permission:inventory.requisitions.manage')
                ->name('requisitions.create');
            Route::post('/requisitions', [RequisitionController::class, 'store'])
                ->middleware('permission:inventory.requisitions.manage')
                ->name('requisitions.store');
            Route::get('/requisitions/{requisition}/edit', [RequisitionController::class, 'edit'])
                ->middleware('permission:inventory.requisitions.manage')
                ->name('requisitions.edit');
            Route::put('/requisitions/{requisition}', [RequisitionController::class, 'update'])
                ->middleware('permission:inventory.requisitions.manage')
                ->name('requisitions.update');
            Route::post('/requisitions/{requisition}/submit', [RequisitionController::class, 'submit'])
                ->middleware('permission:inventory.requisitions.submit')
                ->name('requisitions.submit');
            Route::post('/requisitions/{requisition}/activate', [RequisitionController::class, 'activate'])
                ->middleware('permission:inventory.requisitions.manage')
                ->name('requisitions.activate');
            Route::post('/requisitions/{requisition}/cancel', [RequisitionController::class, 'cancel'])
                ->middleware('permission:inventory.requisitions.manage')
                ->name('requisitions.cancel');
            Route::get('/requisitions/{requisition}', [RequisitionController::class, 'show'])
                ->middleware('permission:inventory.requisitions.view|inventory.requisitions.manage|inventory.requisitions.submit|inventory.requisitions.dispatch|purchases.requisitions.view|purchases.requisitions.approve|purchases.requisitions.reject')
                ->name('requisitions.show');

            Route::get('/kardex/export', [InventoryMovementController::class, 'exportKardex'])
                ->middleware('permission:inventory.movements.view')
                ->name('kardex.export');
            Route::get('/kardex', [InventoryMovementController::class, 'kardex'])
                ->middleware('permission:inventory.movements.view')
                ->name('kardex');
            Route::get('/receipts', [InventoryMovementController::class, 'receipts'])
                ->middleware('permission:inventory.receipts.view|inventory.receipts.create|inventory.receipts.direct.create')
                ->name('receipts.index');
            Route::get('/receipts/create', [InventoryMovementController::class, 'createReceipt'])
                ->middleware('permission:inventory.receipts.create|inventory.receipts.direct.create')
                ->name('receipts.create');
            Route::get('/receipts/{receipt}', [InventoryMovementController::class, 'showReceipt'])
                ->middleware('permission:inventory.receipts.view|inventory.receipts.create|inventory.receipts.direct.create')
                ->name('receipts.show');
            Route::post('/receipts', [InventoryMovementController::class, 'storeReceipt'])
                ->middleware('permission:inventory.receipts.create|inventory.receipts.direct.create')
                ->name('receipts.store');
            Route::get('/issues', [InventoryMovementController::class, 'issues'])
                ->middleware('permission:inventory.issues.view|inventory.issues.create|inventory.issues.direct.create')
                ->name('issues.index');
            Route::get('/issues/create', [InventoryMovementController::class, 'createIssue'])
                ->middleware('permission:inventory.issues.create|inventory.issues.direct.create|inventory.requisitions.dispatch')
                ->name('issues.create');
            Route::get('/issues/{issue}', [InventoryMovementController::class, 'showIssue'])
                ->middleware('permission:inventory.issues.view|inventory.issues.create|inventory.issues.direct.create')
                ->name('issues.show');
            Route::post('/issues', [InventoryMovementController::class, 'storeIssue'])
                ->middleware('permission:inventory.issues.create|inventory.issues.direct.create|inventory.requisitions.dispatch')
                ->name('issues.store');
        });
});

require __DIR__.'/auth.php';
