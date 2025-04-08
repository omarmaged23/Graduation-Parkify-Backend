<?php

use App\Http\Controllers\Api\UserAuthController;
use App\Http\Controllers\UserDataController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/register', [UserAuthController::class, 'register']);
Route::post('/login', [UserAuthController::class, 'login']);

Route::post('/initiatePayment',[\App\Http\Controllers\PaymentController::class,'initiatePayment']);
Route::post('/paymobCallback',[\App\Http\Controllers\PaymentController::class,'paymobCallback'])->name('paymob.callback');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/setupUser',[UserDataController::class,'setupUser']);
    Route::post('/logout', [UserAuthController::class, 'logout']);
});

Route::post('/parkCar',[\App\Http\Controllers\SpotLogController::class,'parkCar']);
Route::post('/exitParking',[\App\Http\Controllers\SpotLogController::class,'exitParking']);
