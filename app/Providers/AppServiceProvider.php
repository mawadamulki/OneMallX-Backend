<?php

namespace App\Providers;

use App\DAO\AreaDAO;
use App\DAO\AreaDAOInterface;
use App\DAO\FloorDAO;
use App\DAO\FloorDAOInterface;
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
        $this->app->bind(AreaDAOInterface::class, AreaDAO::class);
        $this->app->bind(FloorDAOInterface::class, FloorDAO::class);
        $this->app->bind(StoreInterface::class, StoreClass::class);

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
