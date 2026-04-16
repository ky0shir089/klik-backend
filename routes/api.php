<?php

use App\Http\Controllers\AuctionController;
use App\Http\Controllers\AuctionCustomerController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\BankController;
// use App\Http\Controllers\ByadAttachmentController;
// use App\Http\Controllers\ByadController;
// use App\Http\Controllers\ByadPaymentController;
use App\Http\Controllers\ChartOfAccountController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\GeneralLedgerController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MemoInvoiceController;
use App\Http\Controllers\MemoPaymentController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentVoucherController;
use App\Http\Controllers\PphController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RvClassificationController;
use App\Http\Controllers\RvController;
use App\Http\Controllers\RvUploadController;
use App\Http\Controllers\SelectController;
use App\Http\Controllers\SettlementController;
use App\Http\Controllers\SppController;
use App\Http\Controllers\SppV2Controller;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TrxDetailController;
use App\Http\Controllers\TypeTrxController;
use App\Http\Controllers\UploadRvController;
use App\Http\Controllers\UploadSppController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkflowHeaderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post("auth/sign-up", [AuthController::class, "signUp"]);
Route::post("auth/sign-in", [AuthController::class, "signIn"]);

Route::middleware('auth:sanctum')
    ->group(function () {
        Route::prefix('auth')
            ->group(function () {
                Route::prefix('v1')
                    ->group(function () {
                        Route::controller(AuthController::class)
                            ->group(function () {
                                Route::put('change-password', 'changePassword');
                                Route::get('navigation', 'navigation');
                            });
                    });
            });

        Route::prefix('setup-aplikasi')
            ->group(function () {
                Route::prefix('v1')
                    ->group(function () {
                        Route::resource('module', ModuleController::class);
                        Route::resource('menu', MenuController::class);
                        Route::resource('role', RoleController::class);
                        Route::resource('user', UserController::class);
                    });
            });

        Route::prefix('accounting')
            ->group(function () {
                Route::prefix('v1')
                    ->group(function () {
                        Route::resource('coa', ChartOfAccountController::class);
                        Route::resource('type-trx', TypeTrxController::class);
                        Route::resource('trx-dtl', TrxDetailController::class);
                        Route::resource('bank', BankController::class);
                        Route::resource('bank-account', BankAccountController::class);
                        Route::resource('pph', PphController::class);
                        Route::resource('gl', GeneralLedgerController::class);
                        Route::get('journal-input/{gl_no}', [GeneralLedgerController::class, 'show']);
                    });
            });

        Route::prefix('finance')
            ->group(function () {
                Route::prefix('v1')
                    ->group(function () {
                        Route::resource('rv', RvController::class);
                        Route::resource('pv', PaymentVoucherController::class);
                        Route::post('upload-rv', UploadRvController::class);
                        Route::resource('invoice', InvoiceController::class);
                        Route::resource('supplier', SupplierController::class);
                        Route::resource('rv-classification', RvClassificationController::class);
                        Route::get('invoice-inbox', [InvoiceController::class, 'inbox']);
                        Route::get('memo-invoice/{invoice}', MemoInvoiceController::class);
                        Route::resource('settlement', SettlementController::class);
                    });

                Route::prefix('v2')
                    ->group(function () {
                        Route::post('upload-rv', RvUploadController::class);
                    });
            });

        Route::prefix('klik')
            ->group(function () {
                Route::prefix('v1')
                    ->group(function () {
                        Route::resource('auction', AuctionController::class);
                        Route::resource('customer', CustomerController::class);
                        Route::resource('auction-customer', AuctionCustomerController::class);
                        Route::resource('payment', PaymentController::class);
                        Route::post('upload-spp', UploadSppController::class);
                        Route::get('memo-payment/{payment}', MemoPaymentController::class);
                        Route::resource('spp', SppController::class);
                        // Route::resource('byad', ByadController::class);
                        // Route::resource('byad-payment', ByadPaymentController::class);
                        // Route::get('byad-attachment/{byad}', ByadAttachmentController::class);
                    });

                Route::prefix('v2')
                    ->group(function () {
                        Route::get('spp-v2', [SppV2Controller::class, 'index']);
                        Route::get('spp-v2/{sppV2}', [SppV2Controller::class, 'show']);
                        Route::post('spp-v2/sync-status', [SppV2Controller::class, 'update']);
                    });
            });

        Route::prefix('select')
            ->group(function () {
                Route::prefix('v1')
                    ->group(function () {
                        Route::get('module', [SelectController::class, 'module']);
                        Route::get('menu-permission', [SelectController::class, 'menuPermission']);
                        Route::get('module', [SelectController::class, 'module']);
                        Route::get('role', [SelectController::class, 'role']);
                        Route::get('coa', [SelectController::class, 'coa']);
                        Route::get('bank', [SelectController::class, 'bank']);
                        Route::get('type-trx', [SelectController::class, 'typeTrx']);
                        Route::get('bank-account', [SelectController::class, 'bankAccount']);
                        Route::get('titipan-pelunasan', [SelectController::class, 'titipanPelunasan']);
                        Route::get('unpaid-bidder', [SelectController::class, 'unpaidBidder']);
                        Route::get('unpaid-payment', [SelectController::class, 'unpaidPayment']);
                        Route::get('supplier', [SelectController::class, 'supplier']);
                        Route::get('pph', [SelectController::class, 'pph']);
                        Route::get('rv', [SelectController::class, 'rv']);
                        Route::get('byad-unit', [SelectController::class, 'byadUnit']);
                        Route::get('branch', [SelectController::class, 'branch']);
                        Route::get('byad', [SelectController::class, 'byad']);
                        Route::get('user', [SelectController::class, 'user']);
                        Route::get('prepayment', [SelectController::class, 'prepayment']);
                    });
            });

        Route::prefix('report')
            ->group(function () {
                Route::prefix('v1')
                    ->group(function () {
                        Route::get('report-rv', [ReportController::class, 'reportRv']);
                        Route::post('report-auction', [ReportController::class, 'reportAuction']);
                        Route::post('report-bank', [ReportController::class, 'reportBank']);
                        Route::post('report-gl', [ReportController::class, 'reportGl']);
                        Route::post('report-kas', [ReportController::class, 'reportKas']);
                    });
            });

        Route::prefix('workflow')
            ->group(function () {
                Route::prefix('v1')
                    ->group(function () {
                        Route::resource('workflow', WorkflowHeaderController::class);
                    });
            });
    });
