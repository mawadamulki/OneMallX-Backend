<?php

use App\Http\Controllers\AreaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\FloorController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ServiceItemController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\SubscribtionPlanController;
use App\Http\Controllers\SubscriptionExtensionController;
use App\Http\Controllers\SubscriptionNewRequestController;
use App\Http\Controllers\SubscriptionRequestController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// ___________________ Auth Routes ___________________
Route::post('/register', [AuthController::class, 'register']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
Route::post('/login', [AuthController::class, 'login']);
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// ___________________ Delete All Media Route ___________________
Route::delete('/delete-all-media', [MediaController::class, 'destroyAll']);

// ___________________ Floors Mobile Routes ___________________
Route::get('/floors', [FloorController::class, 'index']);
Route::get('/floors/{id}', [FloorController::class, 'show']);
Route::get('/getFloorMedia/{id}', [FloorController::class, 'media']);

Route::get('/areasInFloor/{floorID}', [AreaController::class, 'index']);
Route::get('/areaDetails/{id}', [AreaController::class, 'show']);
Route::get('/getAreaMedia/{id}', [AreaController::class, 'media']);

// ___________________ Services Mobile Routes ___________________
Route::get('/getServicesByArea/{areaID}', [ServiceController::class, 'index']);
Route::get('/serviceDetails/{id}', [ServiceController::class, 'getServiceDetails']);
Route::get('/serviceItemsInService/{serviceID}', [ServiceItemController::class, 'getItemsInService']);
Route::get('/serviceItemAvailability/{id}', [ServiceItemController::class, 'getAvailability']);
// Booking
Route::post('/book', [BookingController::class, 'store']);
Route::get('/serviceBookings/{serviceId}', [BookingController::class, 'serviceBookings']);
// Days
Route::get('/serviceItemDays/{id}', [ServiceItemController::class, 'days']);
// cancel booking
Route::post('/bookings/{id}/cancel', [BookingController::class, 'cancel']);

// ___________________ Stores Mobile Routes ___________________
Route::get('/stores', [StoreController::class, 'index']);
Route::get('/storeDetails/{storeId}', [StoreController::class, 'show']);

// ___________________ Subscription Web Routes ___________________
Route::middleware('throttle:20,1')->group(function () {
    Route::post('/addStoreRequest', [SubscriptionRequestController::class, 'submitStore']);
    Route::post('/addServiceRequest', [SubscriptionRequestController::class, 'submitService']);
});
Route::get('/storePlans', [SubscribtionPlanController::class, 'getStorePlansForSubscription']);
Route::get('/servicePlans', [SubscribtionPlanController::class, 'getServicePlansForSubscription']);

Route::middleware('auth:sanctum')->group(function () {

    Route::delete('/logout', [AuthController::class, 'logout']);

    Route::middleware(['permission:manage subscription plans'])->group(function () {
        Route::get('/getAdminStorePlans', [SubscribtionPlanController::class, 'getStorePlansForAdmin']);
        Route::get('/getAdminServicePlans', [SubscribtionPlanController::class, 'getServicePlansForAdmin']);
        Route::post('/addStorePlan', [SubscribtionPlanController::class, 'createStorePlan']);
        Route::post('/addServicePlan', [SubscribtionPlanController::class, 'createServicePlan']);
        Route::get('/storesInPlan/{planId}', [SubscribtionPlanController::class, 'getStoresInPlan']);
        Route::get('/servicesInPlan/{planId}', [SubscribtionPlanController::class, 'getServicesInPlan']);
        Route::get('/storeDetails/{storeId}', [SubscribtionPlanController::class, 'getStoreDetails']);
        Route::get('/serviceDetails/{serviceId}', [SubscribtionPlanController::class, 'getServiceDetails']);
        Route::post('/stopStorePlan/{planId}', [SubscribtionPlanController::class, 'stopStorePlan']);
        Route::post('/stopServicePlan/{planId}', [SubscribtionPlanController::class, 'stopServicePlan']);
        Route::post('/rerunStorePlan/{planId}', [SubscribtionPlanController::class, 'rerunStorePlan']);
        Route::post('/rerunServicePlan/{planId}', [SubscribtionPlanController::class, 'rerunServicePlan']);
    });

    Route::middleware(['permission:manage floors'])->group(function () {
        Route::post('/createFloors', [FloorController::class, 'store']);
        Route::put('/updateFloor/{id}', [FloorController::class, 'update']);
        Route::delete('/deleteFloor/{id}', [FloorController::class, 'destroy']);
        Route::post('/addFloorMedia/{id}', [FloorController::class, 'storeMedia']);
    });

    Route::middleware(['permission:manage areas'])->group(function () {
        Route::post('/floors/createAreas/{floorId}', [AreaController::class, 'store']);
        Route::put('/updateArea/{id}', [AreaController::class, 'update']);
        Route::delete('/deleteArea/{id}', [AreaController::class, 'destroy']);
        Route::post('/addAreaMedia/{id}', [AreaController::class, 'storeMedia']);
        Route::get('/getStoreAreas/{floorID}', [AreaController::class, 'getStoreAreas']);
        Route::get('/getServiceAreas/{floorID}', [AreaController::class, 'getServiceAreas']);
    });

    Route::middleware(['permission:manage store subscription requests'])->group(function () {
        // first requests
        Route::get('/subscriptionRequests/stores/{status}', [SubscriptionRequestController::class, 'indexStore'])
            ->whereIn('status', ['all', 'pending', 'approved', 'rejected']);
        Route::post('/subscriptionRequests/approveStore/{id}', [SubscriptionRequestController::class, 'approveStore']);
        Route::post('/subscriptionRequests/rejectStore/{id}', [SubscriptionRequestController::class, 'rejectStore']);
        // extension requests
        Route::get('/subscriptionExtensionRequests/stores/{status}', [SubscriptionExtensionController::class, 'indexStore'])
            ->whereIn('status', ['all', 'pending', 'approved', 'rejected']);
        Route::post('/subscriptionExtensionRequests/approveStore/{id}', [SubscriptionExtensionController::class, 'approveStore']);
        Route::post('/subscriptionExtensionRequests/rejectStore/{id}', [SubscriptionExtensionController::class, 'rejectStore']);
        // new plan requests
        Route::get('/subscriptionChangeRequests/stores/{status}', [SubscriptionNewRequestController::class, 'indexStore'])
            ->whereIn('status', ['all', 'pending', 'approved', 'rejected']);
        Route::post('/subscriptionChangeRequests/approveStore/{id}', [SubscriptionNewRequestController::class, 'approveStore']);
        Route::post('/subscriptionChangeRequests/rejectStore/{id}', [SubscriptionNewRequestController::class, 'rejectStore']);
    });

    Route::middleware(['permission:manage service subscription requests'])->group(function () {
        // first requests
        Route::get('/subscriptionRequests/services/{status}', [SubscriptionRequestController::class, 'indexService'])
            ->whereIn('status', ['all', 'pending', 'approved', 'rejected']);
        Route::post('/subscriptionRequests/approveService/{id}', [SubscriptionRequestController::class, 'approveService']);
        Route::post('/subscriptionRequests/rejectService/{id}', [SubscriptionRequestController::class, 'rejectService']);
        // extension requests
        Route::get('/subscriptionExtensionRequests/services/{status}', [SubscriptionExtensionController::class, 'indexService'])
            ->whereIn('status', ['all', 'pending', 'approved', 'rejected']);
        Route::post('/subscriptionExtensionRequests/approveService/{id}', [SubscriptionExtensionController::class, 'approveService']);
        Route::post('/subscriptionExtensionRequests/rejectService/{id}', [SubscriptionExtensionController::class, 'rejectService']);
        // new plan requests
        Route::get('/subscriptionChangeRequests/services/{status}', [SubscriptionNewRequestController::class, 'indexService'])
            ->whereIn('status', ['all', 'pending', 'approved', 'rejected']);
        Route::post('/subscriptionChangeRequests/approveService/{id}', [SubscriptionNewRequestController::class, 'approveService']);
        Route::post('/subscriptionChangeRequests/rejectService/{id}', [SubscriptionNewRequestController::class, 'rejectService']);
    });

    Route::middleware(['permission:show floors and areas'])->group(function () {
        Route::get('/getAdminFloors', [FloorController::class, 'AdminFloors']);
        Route::get('/getShortFloors', [FloorController::class, 'ShortFloors']);
        Route::get('/getAdminFloorsOverviewCounts', [FloorController::class, 'AdminFloorsOverviewCounts']);
    });

    Route::middleware(['permission:manage stores'])->group(function () {
        Route::get('/getAdminStores', [StoreController::class, 'adminStoresSummary']);
        Route::get('/adminStoreDetails/{storeId}', [StoreController::class, 'adminStoreDetails']);
        Route::get('/adminStoreProducts/{storeId}', [StoreController::class, 'adminStoreProducts']);
        Route::get('/adminProductDetails/{productId}', [StoreController::class, 'adminProductDetails']);
    });

    Route::middleware(['permission:manage services'])->group(function () {
        Route::get('/getAdminServices', [ServiceController::class, 'adminServicesSummary']);
        Route::get('/adminServiceDetails/{serviceId}', [ServiceController::class, 'adminServiceDetails']);
        Route::get('/adminServiceItems/{serviceId}', [ServiceController::class, 'adminServiceItems']);
        Route::get('/adminServiceItemDetails/{serviceItemId}', [ServiceController::class, 'adminServiceItemDetails']);
    });

    Route::middleware(['permission:manage users'])->group(function () {
        Route::get('/getAdminUsers', [UserController::class, 'adminAllUsers']);
        Route::get('/getAdminStoreOwners', [UserController::class, 'adminStoreOwners']);
        Route::get('/getAdminServiceProviders', [UserController::class, 'adminServiceProviders']);
        Route::get('/getAdminCustomers', [UserController::class, 'adminCustomers']);
    });

    Route::middleware(['throttle:20,1', 'permission:manage store subscriptions'])->group(function () {
        Route::post('/storeSubscriptions/extend', [SubscriptionExtensionController::class, 'submitStore']);
        Route::post('/storeSubscriptions/newPlanRequest', [SubscriptionNewRequestController::class, 'submitStore']);
        Route::get('/storeSubscriptions/me', [SubscriptionRequestController::class, 'myStoreSubscription']);
    });

    Route::middleware(['throttle:20,1', 'permission:manage service subscriptions'])->group(function () {
        Route::post('/serviceSubscriptions/extend', [SubscriptionExtensionController::class, 'submitService']);
        Route::post('/serviceSubscriptions/newPlanRequest', [SubscriptionNewRequestController::class, 'submitService']);
        Route::get('/serviceSubscriptions/me', [SubscriptionRequestController::class, 'myServiceSubscription']);
    });

});
