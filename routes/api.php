<?php

use App\Http\Controllers\AdminAnalyticsController;
use App\Http\Controllers\BusinessCategoryController;
use App\Http\Controllers\AdvertisementController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\BasketController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\FloorController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\ServiceProviderEmployeeController;
use App\Http\Controllers\ServiceProviderItemController;
use App\Http\Controllers\ServiceAnalyticsController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ServiceItemController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductAttributeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RateController;
use App\Http\Controllers\StoreAnalyticsController;
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
Route::get('/serviceItemDays/{id}', [ServiceItemController::class, 'days']);

// ___________________ Stores Mobile Routes ___________________
Route::get('/stores', [StoreController::class, 'index']);
Route::get('/storeDetails/{storeId}', [StoreController::class, 'show']);

// ___________________ Rates (public read) ___________________
Route::get('/rates/{type}/{id}', [RateController::class, 'index'])
    ->where('type', 'store|product|service|service_item');

// ___________________ Business Categories (public) ___________________
Route::get('/businessCategories', [BusinessCategoryController::class, 'index']);

// ___________________ Advertisements (public) ___________________
Route::get('/ads/home', [AdvertisementController::class, 'homeAds']);
Route::get('/ads/deals', [AdvertisementController::class, 'dealAds']);


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
        Route::get('/adminStoreRate/{storeId}', [StoreController::class, 'adminStoreRate']);
        Route::get('/adminProductRate/{productId}', [StoreController::class, 'adminProductRate']);
        Route::get('/getAdminAds', [AdvertisementController::class, 'adminAdsIndex']);
    });

    Route::middleware(['permission:manage services'])->group(function () {
        Route::get('/getAdminServices', [ServiceController::class, 'adminServicesSummary']);
        Route::get('/adminServiceDetails/{serviceId}', [ServiceController::class, 'adminServiceDetails']);
        Route::get('/adminServiceItems/{serviceId}', [ServiceController::class, 'adminServiceItems']);
        Route::get('/adminServiceItemDetails/{serviceItemId}', [ServiceController::class, 'adminServiceItemDetails']);
        Route::get('/adminServiceRate/{serviceId}', [ServiceController::class, 'adminServiceRate']);
        Route::get('/adminServiceItemRate/{serviceItemId}', [ServiceController::class, 'adminServiceItemRate']);
    });

    Route::middleware(['permission:manage users'])->group(function () {
        Route::get('/getAdminUsers', [UserController::class, 'adminAllUsers']);
        Route::get('/getAdminStoreOwners', [UserController::class, 'adminStoreOwners']);
        Route::get('/getAdminServiceProviders', [UserController::class, 'adminServiceProviders']);
        Route::get('/getAdminCustomers', [UserController::class, 'adminCustomers']);
        Route::post('/adminUsers/deactivate/{userId}', [RateController::class, 'adminDeactivateUser'])
            ->whereNumber('userId');
    });

    Route::middleware(['permission:view admin analytics'])->group(function () {
        Route::get('/adminAnalytics/dashboard', [AdminAnalyticsController::class, 'dashboard']);
        Route::get('/adminAnalytics/export', [AdminAnalyticsController::class, 'export']);
    });

    // ___________________ User Profile Routes ___________________
    Route::get('/user/me', [UserController::class, 'me']);
    Route::post('/user/profilePicture', [UserController::class, 'uploadProfilePicture']);

    // ___________________ Booking Routes ___________________
    Route::middleware(['permission:book services'])->group(function () {
        Route::post('/book', [BookingController::class, 'store']);
        Route::get('/myBookings', [BookingController::class, 'myBookings']);
        Route::get('/bookings/{id}', [BookingController::class, 'show']);
        Route::post('/bookings/cancel/{id}', [BookingController::class, 'cancel']);
    });


    // ___________________ Basket Routes ___________________
    Route::middleware(['permission:manage basket'])->group(function () {
        Route::get('/basket', [BasketController::class, 'show']);
        Route::post('/basket/items', [BasketController::class, 'storeItem']);
        Route::put('/basket/items/{id}', [BasketController::class, 'updateItem']);
        Route::delete('/basket/items/{id}', [BasketController::class, 'destroyItem']);
        Route::delete('/basket', [BasketController::class, 'clear']);
    });

    // ___________________ Checkout & Orders ___________________
    Route::middleware(['permission:place orders'])->group(function () {
        Route::post('/checkout', [OrderController::class, 'checkout']);
        Route::get('/myOrders', [OrderController::class, 'myOrders']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);
    });


    // ___________________ Rates Routes ___________________
    Route::middleware(['permission:rate entities'])->group(function () {
        Route::post('/rates', [RateController::class, 'store']);
        Route::put('/rates/{id}', [RateController::class, 'update']);
        Route::delete('/rates/{id}', [RateController::class, 'destroy']);
        Route::get('/rates/me', [RateController::class, 'myRates']);
    });

    Route::middleware(['permission:report rates'])->group(function () {
        Route::post('/rates/report/{id}', [RateController::class, 'report']);
        Route::delete('/rates/report/{id}', [RateController::class, 'unreport']);
    });

    Route::middleware(['permission:view store ratings'])->group(function () {
        Route::get('/storeRates', [RateController::class, 'storeRates']);
        Route::get('/storeProductRates', [RateController::class, 'storeProductRates']);
        Route::get('/storeProductRates/{productId}', [RateController::class, 'storeProductRates']);
    });

    Route::middleware(['permission:view service ratings'])->group(function () {
        Route::get('/serviceRates', [RateController::class, 'serviceRates']);
        Route::get('/serviceItemRates', [RateController::class, 'serviceItemRates']);
        Route::get('/serviceItemRates/{itemId}', [RateController::class, 'serviceItemRates']);
    });

    Route::middleware(['permission:manage rates'])->group(function () {
        Route::get('/adminRates', [RateController::class, 'adminIndex']);
        Route::get('/adminRates/reportedUsers', [RateController::class, 'adminReportedUsers']);
        Route::get('/adminRates/{id}', [RateController::class, 'adminShow'])
            ->whereNumber('id');
        Route::delete('/adminRates/{id}', [RateController::class, 'adminDestroy'])
            ->whereNumber('id');
        Route::get('/adminRateReports/{status}', [RateController::class, 'adminReports'])
            ->whereIn('status', ['all', 'pending', 'dismissed', 'action_taken']);
        Route::get('/adminRateReports/{id}', [RateController::class, 'adminShowReport'])
            ->whereNumber('id');
        Route::post('/adminRateReports/dismiss/{id}', [RateController::class, 'adminDismissReport'])
            ->whereNumber('id');
        Route::post('/adminRateReports/takeAction/{id}', [RateController::class, 'adminTakeActionOnReport'])
            ->whereNumber('id');
    });


    // ___________________ Subscription Routes ___________________
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


    // ___________________ Store Products Routes ___________________
    Route::middleware(['permission:manage store products'])->group(function () {
        Route::get('/store', [StoreController::class, 'showForOwner']);
        Route::get('/storePlan', [StoreController::class, 'planForOwner']);
        Route::put('/store', [StoreController::class, 'updateForOwner']);
        Route::post('/storeLogo', [StoreController::class, 'storeLogo']);
        Route::delete('/storeLogo', [StoreController::class, 'destroyLogo']);
        Route::post('/storeMedia', [StoreController::class, 'storeMedia']);
        Route::delete('/storeMedia/{mediaId}', [StoreController::class, 'destroyMedia']);

        Route::get('/storeProducts', [ProductController::class, 'index']);
        Route::get('/storeProducts/{productId}', [ProductController::class, 'show']);
        Route::post('/storeProducts', [ProductController::class, 'store']);
        Route::put('/storeProducts/{productId}', [ProductController::class, 'update']);
        Route::delete('/storeProducts/{productId}', [ProductController::class, 'destroy']);
        Route::post('/storeProductMedia/{productId}', [ProductController::class, 'storeMedia']);
        Route::delete('/storeProductMedia/{mediaId}', [ProductController::class, 'destroyMedia']);

        Route::post('/storeProducts/variants/{productId}', [ProductController::class, 'storeVariant']);
        Route::put('/storeProductVariants/{variantId}', [ProductController::class, 'updateVariant']);
        Route::delete('/storeProductVariants/{variantId}', [ProductController::class, 'destroyVariant']);

        Route::get('/storeCategories', [CategoryController::class, 'index']);
        Route::post('/storeCategories', [CategoryController::class, 'store']);
        Route::put('/storeCategories/{categoryId}', [CategoryController::class, 'update']);
        Route::delete('/storeCategories/{categoryId}', [CategoryController::class, 'destroy']);

        Route::get('/storeCollections', [CollectionController::class, 'index']);
        Route::get('/storeCollections/{collectionId}', [CollectionController::class, 'show']);
        Route::post('/storeCollections', [CollectionController::class, 'store']);
        Route::put('/storeCollections/{collectionId}', [CollectionController::class, 'update']);
        Route::delete('/storeCollections/{collectionId}', [CollectionController::class, 'destroy']);

        Route::get('/storeAttributes', [ProductAttributeController::class, 'index']);
        Route::post('/storeAttributes', [ProductAttributeController::class, 'store']);
        Route::put('/storeAttributes/{attributeId}', [ProductAttributeController::class, 'update']);
        Route::delete('/storeAttributes/{attributeId}', [ProductAttributeController::class, 'destroy']);
        Route::post('/storeAttributes/values/{attributeId}', [ProductAttributeController::class, 'storeValue']);
        Route::put('/storeAttributes/values/{valueId}', [ProductAttributeController::class, 'updateValue']);
        Route::delete('/storeAttributes/values/{valueId}', [ProductAttributeController::class, 'destroyValue']);

        Route::get('/storeAds', [AdvertisementController::class, 'storeAdsIndex']);
        Route::get('/storeAds/products', [AdvertisementController::class, 'storeAdsProducts']);
        Route::get('/storeAds/{adId}', [AdvertisementController::class, 'storeAdsShow']);
        Route::post('/storeAds', [AdvertisementController::class, 'storeAdsStore']);
        Route::put('/storeAds/{adId}', [AdvertisementController::class, 'storeAdsUpdate']);
        Route::delete('/storeAds/{adId}', [AdvertisementController::class, 'storeAdsDestroy']);
    });

    Route::middleware(['permission:view store orders'])->group(function () {
        Route::get('/storeOrders', [OrderController::class, 'storeOrders']);
        Route::get('/storeOrders/{orderId}', [OrderController::class, 'storeOrderShow']);
    });

    Route::middleware(['permission:view store analytics'])->group(function () {
        Route::get('/storeAnalytics/dashboard', [StoreAnalyticsController::class, 'dashboard']);
        Route::get('/storeAnalytics/export', [StoreAnalyticsController::class, 'export']);
    });


    // ___________________ Service Catalog Routes ___________________
    Route::middleware(['permission:manage service catalog'])->group(function () {
        Route::get('/service', [ServiceController::class, 'showForOwner']);
        Route::get('/servicePlan', [ServiceController::class, 'planForOwner']);
        Route::put('/service', [ServiceController::class, 'updateForOwner']);
        Route::put('/service/workingDays', [ServiceController::class, 'syncWorkingDays']);
        Route::post('/serviceMedia', [ServiceController::class, 'storeMedia']);
        Route::delete('/serviceMedia/{mediaId}', [ServiceController::class, 'destroyMedia']);
        Route::post('/serviceLogo', [ServiceController::class, 'storeLogo']);
        Route::delete('/serviceLogo', [ServiceController::class, 'destroyLogo']);

        Route::get('/serviceProviderItems', [ServiceProviderItemController::class, 'index']);
        Route::get('/serviceProviderItems/names', [ServiceProviderItemController::class, 'names']);
        Route::get('/serviceProviderItems/{itemId}', [ServiceProviderItemController::class, 'show']);
        Route::post('/serviceProviderItems', [ServiceProviderItemController::class, 'store']);
        Route::put('/serviceProviderItems/{itemId}', [ServiceProviderItemController::class, 'update']);
        Route::delete('/serviceProviderItems/{itemId}', [ServiceProviderItemController::class, 'destroy']);
        Route::post('/serviceProviderItems/{itemId}/media', [ServiceProviderItemController::class, 'storeMedia']);
        Route::delete('/serviceProviderItemMedia/{mediaId}', [ServiceProviderItemController::class, 'destroyMedia']);

        Route::get('/serviceProviderEmployees', [ServiceProviderEmployeeController::class, 'index']);
        Route::get('/serviceProviderEmployees/{employeeId}', [ServiceProviderEmployeeController::class, 'show']);
        Route::post('/serviceProviderEmployees', [ServiceProviderEmployeeController::class, 'store']);
        Route::put('/serviceProviderEmployees/{employeeId}', [ServiceProviderEmployeeController::class, 'update']);
        Route::delete('/serviceProviderEmployees/{employeeId}', [ServiceProviderEmployeeController::class, 'destroy']);
        Route::put('/serviceProviderEmployees/workingDays/{employeeId}', [ServiceProviderEmployeeController::class, 'syncWorkingDays']);
        Route::post('/serviceProviderEmployees/photo/{employeeId}', [ServiceProviderEmployeeController::class, 'storePhoto']);
        Route::delete('/serviceProviderEmployees/photo/{employeeId}', [ServiceProviderEmployeeController::class, 'destroyPhoto']);

        Route::get('/serviceAds', [AdvertisementController::class, 'serviceAdsIndex']);
        Route::get('/serviceAds/items', [AdvertisementController::class, 'serviceAdsItems']);
        Route::get('/serviceAds/{adId}', [AdvertisementController::class, 'serviceAdsShow']);
        Route::post('/serviceAds', [AdvertisementController::class, 'serviceAdsStore']);
        Route::put('/serviceAds/{adId}', [AdvertisementController::class, 'serviceAdsUpdate']);
        Route::delete('/serviceAds/{adId}', [AdvertisementController::class, 'serviceAdsDestroy']);
    });

    Route::middleware(['permission:view service bookings'])->group(function () {
        Route::get('/serviceBookings/day', [BookingController::class, 'serviceBookingsDay']);
        Route::get('/serviceBookings/week', [BookingController::class, 'serviceBookingsWeek']);
        Route::get('/serviceBookings/month', [BookingController::class, 'serviceBookingsByMonth']);
    });

    Route::middleware(['permission:view service analytics'])->group(function () {
        Route::get('/serviceAnalytics/dashboard', [ServiceAnalyticsController::class, 'dashboard']);
        Route::get('/serviceAnalytics/export', [ServiceAnalyticsController::class, 'export']);
    });

});
