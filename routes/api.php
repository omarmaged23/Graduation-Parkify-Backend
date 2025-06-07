<?php

use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\Auth\UserAuthController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\UserDataController;
use App\Services\MqttService;
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

//Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//    return $request->user();
//});

Route::post('/user/register', [UserAuthController::class, 'register']);
Route::post('/user/login', [UserAuthController::class, 'login']);
Route::post('/admin/register', [AdminAuthController::class, 'register']);
Route::post('/admin/login', [AdminAuthController::class, 'login']);

Route::post('/paymobCallback',[\App\Http\Controllers\PaymentController::class,'paymobCallback'])->name('paymob.callback');

Route::middleware('auth:api')->group(function () {
    // For user registration purposes
    Route::post('/user/setupUser',[UserDataController::class,'setupUser']);
    Route::get('/user/getUserPlates',[\App\Http\Controllers\LicensePlateController::class,'getUserPlates']);
    Route::post('/user/addLicensePlate',[\App\Http\Controllers\LicensePlateController::class,'addLicensePlate']);
    Route::post('/user/deleteLicensePlate',[\App\Http\Controllers\LicensePlateController::class,'deleteLicensePlate']);
    Route::post('/user/logout', [UserAuthController::class, 'logout']);
    // Spot reservation
    Route::post('/user/reserveSpot',[ReservationController::class,'reserveSpot']);
    Route::get('/user/getActiveReservation',[ReservationController::class,'getActiveReservation']);
    Route::post('/user/cancelReservation',[ReservationController::class,'cancelReservation']);
    Route::post('/user/deactivateReservationBlocker',[ReservationController::class,'deactivateReservationBlocker']);
    // Get separate logs
    Route::get('/user/getPublicSpotLog',[\App\Http\Controllers\PublicSpotLogController::class,'getPublicSpotLog']);
    Route::get('/user/getReservableSpotLog',[\App\Http\Controllers\ReservableSpotLogController::class,'getReservableSpotLog']);
    // Gift handling
    Route::post('/user/activateGift',[\App\Http\Controllers\UserGiftController::class,'activateGift']);
    Route::post('/user/deactivateGift',[\App\Http\Controllers\UserGiftController::class,'deactivateGift']);
    // Ask for payment request
    Route::post('/initiatePayment',[\App\Http\Controllers\PaymentController::class,'initiatePayment']);
    // Get user transaction history
    Route::get('/user/getTransactionHistory',[userDataController::class,'getTransactionHistory']);
    // Get Points And Balance
    Route::get('/user/getPointsAndBalance',[userDataController::class,'getPointsAndBalance']);
});
Route::middleware('auth:admin')->group(function () {
    // Logout
    Route::post('/admin/logout', [AdminAuthController::class, 'logout']);
    // Spot Management
    Route::post('/admin/managePrices/{type}', [\App\Http\Controllers\Admin\SpotManagementController::class, 'managePrices']);
    Route::post('/admin/editPointsPerHour', [\App\Http\Controllers\Admin\SpotManagementController::class, 'editPointsPerHour']);
    // Public Spots
    Route::get('/admin/getPublicSpots', [\App\Http\Controllers\Admin\PublicSpotController::class, 'getPublicSpots']);
    Route::post('/admin/addPublicSpot', [\App\Http\Controllers\Admin\PublicSpotController::class, 'addPublicSpot']);
    Route::post('/admin/editPublicSpot/{id}', [\App\Http\Controllers\Admin\PublicSpotController::class, 'editPublicSpot']);
    Route::post('/admin/deletePublicSpot', [\App\Http\Controllers\Admin\PublicSpotController::class, 'deletePublicSpot']);
    // Reservable Spots
    Route::get('/admin/getReservableSpots', [\App\Http\Controllers\Admin\ReservableSpotController::class, 'getReservableSpots']);
    Route::post('/admin/addReservableSpot', [\App\Http\Controllers\Admin\ReservableSpotController::class, 'addReservableSpot']);
    Route::post('/admin/editReservableSpot/{id}', [\App\Http\Controllers\Admin\ReservableSpotController::class, 'editReservableSpot']);
    Route::post('/admin/deleteReservableSpot', [\App\Http\Controllers\Admin\ReservableSpotController::class, 'deleteReservableSpot']);
    // Gifts
    Route::post('/admin/addGift',[\App\Http\Controllers\Admin\GiftController::class, 'addGift']);
    Route::post('/admin/editGift/{id}',[\App\Http\Controllers\Admin\GiftController::class, 'editGift']);
    Route::post('/admin/deleteGift',[\App\Http\Controllers\Admin\GiftController::class, 'deleteGift']);
    Route::get('/admin/getGift/{id}',[\App\Http\Controllers\Admin\GiftController::class, 'getGift']);
    // User Management
    Route::get('/admin/getAllUsers',[\App\Http\Controllers\Admin\ManageUserController::class, 'getAllUsers']);
    Route::post('/admin/changeUserStatus/{id}',[\App\Http\Controllers\Admin\ManageUserController::class, 'changeUserStatus']);
    Route::get('/admin/getGuestLogs',[\App\Http\Controllers\Admin\ManageUserController::class, 'getGuestLogs']);
    // Location Management
    Route::post('/admin/addLocation',[\App\Http\Controllers\Admin\LocationController::class, 'addLocation']);
    Route::post('/admin/editLocation/{id}',[\App\Http\Controllers\Admin\LocationController::class, 'editLocation']);
    Route::post('/admin/changeLocationStatus/{id}',[\App\Http\Controllers\Admin\LocationController::class, 'changeLocationStatus']);
    Route::post('/admin/deleteLocation',[\App\Http\Controllers\Admin\LocationController::class, 'deleteLocation']);
    // Refund
    Route::get('/admin/getRefundPercentage',[\App\Http\Controllers\Admin\RefundController::class, 'getRefundPercentage']);
    Route::post('/admin/editRefundPercentage',[\App\Http\Controllers\Admin\RefundController::class, 'editRefundPercentage']);
});
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/getAllGifts',[\App\Http\Controllers\Admin\GiftController::class, 'getAllGifts']);
    Route::get('/getSpotDetails', [\App\Http\Controllers\Admin\SpotManagementController::class, 'getSpotDetails']);
    Route::get('/getPointsPerHour', [\App\Http\Controllers\Admin\SpotManagementController::class, 'getPointsPerHour']);
    Route::get('/getUserWithLogs/{id}',[\App\Http\Controllers\Admin\ManageUserController::class, 'getUserWithLogs']);
    Route::get('/getAllLocations',[\App\Http\Controllers\Admin\LocationController::class, 'getAllLocations']);

});
Route::post('/parkCar/{location}',[\App\Http\Controllers\SpotLogController::class,'parkCar']);
Route::post('/exitParking/{location}',[\App\Http\Controllers\SpotLogController::class,'exitParking']);
Route::post('/logUsedPublicSpot/{location}',[\App\Http\Controllers\PublicSpotLogController::class,'logUsedPublicSpot']);

Route::get('/getParkedCars', function (){
    return \App\Models\Mqtt_Spot_Log::all();
});
Route::post('/resetRetain', function(){
    $mqttService = new MqttService();
    $mqttService->publish('garage/banha/entry_gate','',true);
    $mqttService->publish('garage/obour/entry_gate','',true);
    $mqttService->publish('garage/banha/exit_gate','',true);
    $mqttService->publish('garage/obour/exit_gate','',true);
    $mqttService->publish('garage/banha/spots/init','',true);
    $mqttService->publish('garage/obour/spots/init','',true);
    return 'done';
});
