<?php

namespace App\Providers;

use App\Comptes\EmetteurLienMagique;
use App\Comptes\FrontUrl;
use App\Exploitation\Battements;
use App\Models\Discussion;
use App\Models\Evenement;
use App\Models\Message;
use App\Moteur\RouteurCopies;
use App\Policies\DiscussionPolicy;
use App\Policies\EvenementPolicy;
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
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Event;
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
            return new SchemaValidator(base_path('soukdev.schema.json'));
        });

        $this->app->singleton(SecretGenerator::class, RandomSecretGenerator::class);

        $this->app->bind(RouteurCopies::class, fn () => RouteurCopies::depuisConfig());

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

        $this->app->singleton(Battements::class, fn () => new Battements(storage_path('framework/sante')));

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

        // Par adresse IP comme avant, et 3 liens par quart d'heure pour une même adresse email.
        RateLimiter::for('lien-magique', function (Request $request) {
            $email = $request->input('email');

            return [
                Limit::perMinute(60)->by('ip:'.$request->ip()),
                Limit::perMinutes(15, 3)->by('email:'.(is_string($email) ? EmetteurLienMagique::normaliser($email) : '')),
            ];
        });

        RateLimiter::for('missions', function (Request $request) {
            return Limit::perHour(10)->by('missions:'.($request->user()?->id ?? $request->ip()));
        });

        RateLimiter::for('correctifs', function (Request $request) {
            return Limit::perHour(10)->by('correctifs:'.($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('deploiements', function (Request $request) {
            return Limit::perHour(3)->by('deploiements:'.($request->user()?->id ?? $request->ip()));
        });

        $this->battreDansLesWorkers();

        Gate::policy(Discussion::class, DiscussionPolicy::class);
        Gate::policy(Message::class, MessagePolicy::class);
        Gate::policy(Evenement::class, EvenementPolicy::class);
    }

    /**
     * Contrôle de santé des workers : en attente, ils battent à chaque tour de boucle ;
     * pendant un job, ils restent « au travail » le temps qu'il faut.
     */
    private function battreDansLesWorkers(): void
    {
        $battre = fn (string $etat) => fn () => $this->app->make(Battements::class)->battre('worker', $etat);

        Event::listen(Looping::class, $battre('attente'));
        Event::listen(JobProcessing::class, $battre('travail'));
        Event::listen(JobProcessed::class, $battre('attente'));
        Event::listen(JobFailed::class, $battre('attente'));
    }
}
