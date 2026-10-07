<?php

namespace App\Providers;

use App\Soukdev\GitCloner;
use App\Soukdev\ProcessGitCloner;
use App\Soukdev\RandomSecretGenerator;
use App\Soukdev\RepositoryReader;
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

        $this->app->singleton(GitCloner::class, ProcessGitCloner::class);

        $this->app->singleton(RepositoryReader::class, function ($app) {
            return new RepositoryReader(
                $app->make(GitCloner::class),
                $app->make(SchemaValidator::class),
                storage_path('app/checkouts'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
