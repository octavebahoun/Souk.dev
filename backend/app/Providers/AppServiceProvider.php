<?php

namespace App\Providers;

use App\Comptes\FrontUrl;
use App\Models\Discussion;
use App\Models\Message;
use App\Policies\DiscussionPolicy;
use App\Policies\MessagePolicy;
use App\Soukdev\ComposeRunner;
use App\Soukdev\CopyLauncher;
use App\Soukdev\EnvGenerator;
use App\Soukdev\GitCloner;
use App\Soukdev\ProcessComposeRunner;
use App\Soukdev\ProcessGitCloner;
use App\Soukdev\RandomSecretGenerator;
use App\Soukdev\RepositoryReader;
use App\Soukdev\SchemaValidator;
use App\Soukdev\SecretGenerator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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

        $this->app->singleton(ComposeRunner::class, ProcessComposeRunner::class);

        $this->app->singleton(CopyLauncher::class, function ($app) {
            return new CopyLauncher(
                $app->make(RepositoryReader::class),
                $app->make(EnvGenerator::class),
                $app->make(ComposeRunner::class),
                storage_path('app/copies'),
            );
        });

        $this->app->singleton(FrontUrl::class, function () {
            return new FrontUrl((string) config('app.frontend_url'));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();

        RateLimiter::for('discussions', function (Request $request) {
            return Limit::perHour(10)->by($request->user()?->id ?: $request->ip());
        });

        Gate::policy(Discussion::class, DiscussionPolicy::class);
        Gate::policy(Message::class, MessagePolicy::class);
    }
}
