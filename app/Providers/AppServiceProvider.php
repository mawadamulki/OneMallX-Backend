<?php

namespace App\Providers;

use App\DAO\AdminAnalyticsClass;
use App\DAO\AdminAnalyticsInterface;
use App\DAO\AdvertisementClass;
use App\DAO\AdvertisementInterface;
use App\DAO\BusinessCategoryClass;
use App\DAO\BusinessCategoryInterface;
use App\DAO\AreaDAO;
use App\DAO\AreaDAOInterface;
use App\DAO\CollectionClass;
use App\DAO\CollectionInterface;
use App\DAO\CategoryClass;
use App\DAO\CategoryInterface;
use App\DAO\FloorDAO;
use App\DAO\FloorDAOInterface;
use App\DAO\ProductAttributeClass;
use App\DAO\ProductAttributeInterface;
use App\DAO\ProductClass;
use App\DAO\ProductInterface;
use App\DAO\RateClass;
use App\DAO\RateInterface;
use App\DAO\ServiceAnalyticsClass;
use App\DAO\ServiceAnalyticsInterface;
use App\DAO\ServiceProviderClass;
use App\DAO\ServiceProviderEmployeeClass;
use App\DAO\ServiceProviderEmployeeInterface;
use App\DAO\ServiceProviderInterface;
use App\DAO\ServiceProviderItemClass;
use App\DAO\ServiceProviderItemInterface;
use App\DAO\StoreAnalyticsClass;
use App\DAO\StoreAnalyticsInterface;
use App\DAO\SearchClass;
use App\DAO\SearchInterface;
use App\DAO\StoreClass;
use App\DAO\StoreInterface;
use App\DAO\SubscriptionExtensionClass;
use App\DAO\SubscriptionExtensionInterface;
use App\DAO\SubscriptionNewRequestClass;
use App\DAO\SubscriptionNewRequestInterface;
use App\DAO\SubscriptionPlanClass;
use App\DAO\SubscriptionPlanInterface;
use App\DAO\SubscriptionRequestClass;
use App\DAO\SubscriptionRequestInterface;
use App\DAO\UserDAO;
use App\DAO\UserDAOInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {

        $this->app->bind(UserDAOInterface::class, UserDAO::class);
        $this->app->bind(AdminAnalyticsInterface::class, AdminAnalyticsClass::class);
        $this->app->bind(AdvertisementInterface::class, AdvertisementClass::class);
        $this->app->bind(BusinessCategoryInterface::class, BusinessCategoryClass::class);
        $this->app->bind(AreaDAOInterface::class, AreaDAO::class);
        $this->app->bind(FloorDAOInterface::class, FloorDAO::class);
        $this->app->bind(SearchInterface::class, SearchClass::class);
        $this->app->bind(StoreInterface::class, StoreClass::class);
        $this->app->bind(StoreAnalyticsInterface::class, StoreAnalyticsClass::class);
        $this->app->bind(RateInterface::class, RateClass::class);
        $this->app->bind(ProductInterface::class, ProductClass::class);
        $this->app->bind(CollectionInterface::class, CollectionClass::class);
        $this->app->bind(CategoryInterface::class, CategoryClass::class);
        $this->app->bind(ProductAttributeInterface::class, ProductAttributeClass::class);
        $this->app->bind(ServiceProviderInterface::class, ServiceProviderClass::class);
        $this->app->bind(ServiceAnalyticsInterface::class, ServiceAnalyticsClass::class);
        $this->app->bind(ServiceProviderItemInterface::class, ServiceProviderItemClass::class);
        $this->app->bind(ServiceProviderEmployeeInterface::class, ServiceProviderEmployeeClass::class);

        $this->app->bind(SubscriptionPlanInterface::class, SubscriptionPlanClass::class);
        $this->app->bind(SubscriptionRequestInterface::class, SubscriptionRequestClass::class);
        $this->app->bind(SubscriptionExtensionInterface::class, SubscriptionExtensionClass::class);
        $this->app->bind(SubscriptionNewRequestInterface::class, SubscriptionNewRequestClass::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
