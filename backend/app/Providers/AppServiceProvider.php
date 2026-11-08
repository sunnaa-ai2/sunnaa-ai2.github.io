<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        // Fail loudly in development when a field is not mass-assignable.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }
}
