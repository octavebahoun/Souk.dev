<?php

namespace App\Providers;

use App\Comptes\EmetteurLienMagique;
use App\Comptes\FrontUrl;
use App\Exploitation\Battements;
use App\Models\Appli;
use App\Models\Correctif;
use App\Models\Deploiement;
use App\Models\Discussion;
use App\Models\Evenement;
use App\Models\Facture;
use App\Models\Message;
use App\Models\Mission;
use App\Models\User;
use App\Moteur\RouteurCopies;
use App\Paiement\Passerelles\ClientSasPay;
use App\Paiement\Passerelles\PasserellePaiement;
use App\Paiement\Passerelles\PasserelleReversement;
use App\Paiement\Passerelles\SasPayPaiement;
use App\Paiement\Passerelles\SasPayReversement;
use App\Paiement\Passerelles\SimulateurSasPay;
use App\Policies\AppliPolicy;
use App\Policies\CorrectifPolicy;
use App\Policies\DeploiementPolicy;
use App\Policies\DiscussionPolicy;
use App\Policies\EvenementPolicy;
use App\Policies\FacturePolicy;
use App\Policies\MessagePolicy;
use App\Policies\MissionPolicy;
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
use Illuminate\Auth\Access\Response as GateResponse;
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
use Illuminate\Support\Facades\URL;
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

        $this->enregistrerPasserelles();

        $this->app->singleton(FrontUrl::class, function () {
            return new FrontUrl((string) config('app.frontend_url'));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Les liens absolus (simulateur de paiement, lien de paiement signé, confirmation d'un numéro)
        // visent toujours l'API (APP_URL), même quand la requête arrive par le proxy du front.
        if (is_string(config('app.url')) && config('app.url') !== '') {
            URL::forceRootUrl((string) config('app.url'));
        }

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

        // Argent : ouvrir une session de paiement, revérifier au retour, demander un reversement.
        RateLimiter::for('paiements', fn (Request $request) => Limit::perMinute(10)->by('paiements:'.($request->user()?->id ?? $request->ip())));
        RateLimiter::for('verifications', fn (Request $request) => Limit::perMinute(30)->by('verifications:'.($request->user()?->id ?? $request->ip())));
        RateLimiter::for('reversements', fn (Request $request) => Limit::perHour(5)->by('reversements:'.($request->user()?->id ?? $request->ip())));
        RateLimiter::for('coordonnees', fn (Request $request) => Limit::perHour(5)->by('coordonnees:'.($request->user()?->id ?? $request->ip())));

        // Une analyse de sécurité occupe le moteur plusieurs minutes : 5 relances par heure.
        RateLimiter::for('securite', fn (Request $request) => Limit::perHour(5)->by('securite:'.($request->user()?->id ?? $request->ip())));

        $this->battreDansLesWorkers();

        // Publier une appli ou proposer un correctif, c'est lier un dépôt : GitHub obligatoire.
        Gate::define('lierUnDepot', fn (User $compte) => $compte->github_lie
            ? GateResponse::allow()
            : GateResponse::deny('GitHub n\'est pas lié à ce compte.'));

        Gate::policy(Appli::class, AppliPolicy::class);
        Gate::policy(Correctif::class, CorrectifPolicy::class);
        Gate::policy(Deploiement::class, DeploiementPolicy::class);
        Gate::policy(Mission::class, MissionPolicy::class);
        Gate::policy(Facture::class, FacturePolicy::class);

        // Administration : finances, modération, serveurs.
        Gate::define('administrer', fn (User $compte) => $compte->est_admin === true);
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

    /**
     * Paiement : SasPay si la clé est là, sinon le simulateur — jamais le simulateur en
     * production, où l'encaissement doit être réel. Reversements : toujours simulés, sauf
     * REVERSEMENTS_REELS=true avec SasPay (un payout fait sortir de l'argent du compte).
     */
    private function enregistrerPasserelles(): void
    {
        $this->app->singleton(ClientSasPay::class, fn () => new ClientSasPay(
            (string) config('services.saspay.cle'),
            (string) config('services.saspay.url'),
            (int) config('services.saspay.delai', 20),
        ));

        $this->app->singleton(SimulateurSasPay::class);

        $this->app->bind(PasserellePaiement::class, function ($app) {
            if (config('facturation.passerelle') === 'saspay') {
                return $app->make(SasPayPaiement::class);
            }

            if ($app->isProduction()) {
                throw new \RuntimeException('Paiement non configuré : SASPAY_SECRET_KEY est obligatoire en production.');
            }

            return $app->make(SimulateurSasPay::class);
        });

        $this->app->bind(PasserelleReversement::class, function ($app) {
            return config('facturation.reversements_reels') && config('facturation.passerelle') === 'saspay'
                ? $app->make(SasPayReversement::class)
                : $app->make(SimulateurSasPay::class);
        });
    }
}
