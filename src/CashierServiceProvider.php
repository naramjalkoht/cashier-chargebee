<?php

namespace Laravel\CashierChargebee;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CashierServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     */
    public function boot() : void
    {
        $this->registerRoutes();
        $this->registerResources();
        $this->registerPublishing();
        Cashier::configureEnvironment();
    }

    /**
     * Register any application services.
     */
    public function register() : void
    {
        $this->configure();
    }

    /**
     * Setup the configuration for Cashier.
     */
    protected function configure() : void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/cashier.php',
            'cashier'
        );
    }

    /**
     * Register the package routes.
     */
    protected function registerRoutes() : void
    {
        if (Cashier::$registersRoutes) {
            Route::group([
                'prefix' => config('cashier.path'),
                'namespace' => 'Laravel\CashierChargebee\Http\Controllers',
                'as' => 'cashier.',
            ], function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            });
        }
    }

    /**
     * Register the package resources.
     */
    protected function registerResources() : void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'cashier');
    }

    /**
     * Register the package's publishable resources.
     */
    protected function registerPublishing() : void
    {
        if ($this->app->runningInConsole()) {
            $publishesMigrationsMethod = method_exists($this, 'publishesMigrations')
                ? 'publishesMigrations'
                : 'publishes';

            $this->{$publishesMigrationsMethod}([
                __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'cashier-migrations');

            $this->publishes([
                __DIR__.'/../config/cashier.php' => $this->app->configPath('cashier.php'),
            ], 'cashier-config');

            $this->publishes([
                __DIR__.'/../resources/views' => $this->app->resourcePath('views/vendor/cashier'),
            ], 'cashier-views');
        }
    }
}
