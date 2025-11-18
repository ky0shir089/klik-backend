<?php

use App\Http\Controllers\AuctionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\bankAccountController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\ChartOfAccountController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentVoucherController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RvController;
use App\Http\Controllers\SelectController;
use App\Http\Controllers\TrxDetailController;
use App\Http\Controllers\TypeTrxController;
use App\Http\Controllers\UploadRvController;
use App\Http\Controllers\UserController;
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
                        Route::resource('bank-account', bankAccountController::class);
                    });
            });

        Route::prefix('finance')
            ->group(function () {
                Route::prefix('v1')
                    ->group(function () {
                        Route::resource('rv', RvController::class);
                        Route::resource('pv', PaymentVoucherController::class);
                        Route::post('upload-rv', UploadRvController::class);
                    });
            });

        Route::prefix('klik')
            ->group(function () {
                Route::prefix('v1')
                    ->group(function () {
                        Route::resource('auction', AuctionController::class);
                        Route::resource('customer', CustomerController::class);
                        Route::resource('payment', PaymentController::class);
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
                    });
            });
    });
