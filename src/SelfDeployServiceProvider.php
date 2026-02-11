<?php

namespace Iperamuna\SelfDeploy;

use Illuminate\Support\ServiceProvider;

class SelfDeployServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        // Load Config
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/self-deploy.php' => config_path('self-deploy.php'),
            ], 'self-deploy-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/self-deploy'),
            ], 'self-deploy-views');

            // Register Commands
            $this->commands([
                Console\Commands\CreateDeploymentFile::class,
                Console\Commands\PublishDeploymentScript::class,
                Console\Commands\SelfDeploy::class,
                Console\Commands\SelfDeployRemote::class,
            ]);
        }

        // Load Views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'self-deploy');
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/self-deploy.php',
            'self-deploy'
        );
    }
}
