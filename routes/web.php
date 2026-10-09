<?php

use App\Http\Controllers\Pharmacy\AuditLogController;
use App\Http\Controllers\Pharmacy\BranchController;
use App\Http\Controllers\Pharmacy\CustomerController;
use App\Http\Controllers\Pharmacy\DashboardController;
use App\Http\Controllers\Pharmacy\EInvoiceController;
use App\Http\Controllers\Pharmacy\GoodsReceiptController;
use App\Http\Controllers\Pharmacy\PosController;
use App\Http\Controllers\Pharmacy\PrescriptionController;
use App\Http\Controllers\Pharmacy\ProductController;
use App\Http\Controllers\Pharmacy\ProductImportController;
use App\Http\Controllers\Pharmacy\PurchaseOrderController;
use App\Http\Controllers\Pharmacy\RegisterController;
use App\Http\Controllers\Pharmacy\ReportController;
use App\Http\Controllers\Pharmacy\SaleController;
use App\Http\Controllers\Pharmacy\ShiftController;
use App\Http\Controllers\Pharmacy\StockController;
use App\Http\Controllers\Pharmacy\SupplierController;
use App\Http\Controllers\Pharmacy\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Counter: every role.
    Route::get('pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('pos', [PosController::class, 'store'])->name('pos.store');
    Route::get('shifts', [ShiftController::class, 'index'])->name('shifts.index');
    Route::post('shifts', [ShiftController::class, 'store'])->name('shifts.store');
    Route::post('shifts/close', [ShiftController::class, 'close'])->name('shifts.close');
    Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
    Route::get('sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::post('customers/{customer}/payments', [CustomerController::class, 'payment'])->name('customers.payment');
    Route::get('stock', [StockController::class, 'index'])->name('stock.index');

    Route::middleware('role:owner|pharmacist|assistant')->group(function () {
        Route::get('products/import', [ProductImportController::class, 'create'])->name('products.import');
        Route::get('products/import/template', [ProductImportController::class, 'template'])->name('products.import.template');
        Route::post('products/import', [ProductImportController::class, 'store'])->name('products.import.store');
        Route::resource('products', ProductController::class)->except(['show', 'destroy']);
        Route::resource('suppliers', SupplierController::class)->only(['index', 'store']);
        Route::resource('receipts', GoodsReceiptController::class)->only(['index', 'create', 'store']);
        Route::resource('purchase-orders', PurchaseOrderController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('purchase-orders/{purchase_order}/status', [PurchaseOrderController::class, 'status'])->name('purchase-orders.status');
        Route::get('prescriptions', [PrescriptionController::class, 'index'])->name('prescriptions.index');
        Route::get('stock/movements', [StockController::class, 'movements'])->name('stock.movements');
    });

    Route::middleware('role:owner|pharmacist')->group(function () {
        Route::post('sales/{sale}/refund', [SaleController::class, 'refund'])->name('sales.refund');
        Route::get('register', [RegisterController::class, 'index'])->name('register.index');
        Route::post('stock/{batch}/adjust', [StockController::class, 'adjust'])->name('stock.adjust');
        Route::get('stock/transfer', [StockController::class, 'transfer'])->name('stock.transfer');
        Route::post('stock/transfer', [StockController::class, 'storeTransfer'])->name('stock.transfer.store');
        Route::get('reports', ReportController::class)->name('reports.index');
        Route::post('einvoice/sales/{sale}', [EInvoiceController::class, 'submitSale'])->name('einvoice.sale');
        Route::post('einvoice/refunds/{refund}', [EInvoiceController::class, 'submitRefund'])->name('einvoice.refund');
        Route::post('einvoice/{einvoice}/poll', [EInvoiceController::class, 'poll'])->name('einvoice.poll');
    });

    Route::middleware('role:owner')->group(function () {
        Route::resource('users', UserController::class)->only(['index', 'store', 'update']);
        Route::resource('branches', BranchController::class)->only(['index', 'store', 'update']);
        Route::post('branches/{branch}/switch', [BranchController::class, 'switch'])->name('branches.switch');
        Route::get('audit', AuditLogController::class)->name('audit.index');
        Route::get('einvoice', [EInvoiceController::class, 'index'])->name('einvoice.index');
        Route::post('einvoice/consolidate', [EInvoiceController::class, 'consolidate'])->name('einvoice.consolidate');
        Route::post('einvoice/settings', [EInvoiceController::class, 'saveSettings'])->name('einvoice.settings');
        Route::post('einvoice/{einvoice}/cancel', [EInvoiceController::class, 'cancel'])->name('einvoice.cancel');
    });
});

require __DIR__.'/settings.php';
