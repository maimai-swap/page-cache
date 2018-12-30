<?php

namespace Silber\PageCache;

use Illuminate\Support\ServiceProvider;
use Silber\PageCache\Console\ClearCache;

class LaravelServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->commands(ClearCache::class);
        $this->registerConfigPublish();

        $this->app->singleton(Cache::class, function () {
            $instance = new Cache($this->app->make('files'));

            return $instance->setContainer($this->app);
        });
    }

    public function registerConfigPublish() {
        $configPath = __DIR__ . '/config/page-cache.php';
        if (function_exists('config_path')) {
            $publishPath = config_path('page-cache.php');
        } else {
            $publishPath = base_path('config/page-cache.php');
        }
        $this->publishes([$configPath => $publishPath],'config');
    }
}