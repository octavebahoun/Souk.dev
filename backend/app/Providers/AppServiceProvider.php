<?php

namespace App\Providers;

use App\Soukdev\RandomSecretGenerator;
use App\Soukdev\SchemaValidator;
use App\Soukdev\SecretGenerator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SchemaValidator::class, function () {
            return new SchemaValidator(dirname(base_path()).'/soukdev.schema.json');
        });

        $this->app->singleton(SecretGenerator::class, RandomSecretGenerator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
