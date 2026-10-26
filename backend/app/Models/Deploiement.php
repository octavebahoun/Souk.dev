<?php

namespace App\Models;

use App\Events\CopieTestEtat;
use App\Events\CorrectifEtat;
use App\Events\DeploiementEtat;
use App\Jobs\ArreterDeploiement;
use App\Moteur\DepotGithub;
use App\Moteur\Exceptions\DepotInvalide;
use Database\Factories\DeploiementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Throwable;

class Deploiement extends Model
{
    /** @use HasFactory<DeploiementFactory> */
    use HasFactory;

    protected $table = 'deploiements';

    protected $fillable = [
        'user_id',
        'appli_id',
        'discussion_id',
        'correctif_id',
        'depot_url',
        'type',
        'taille',
        'etat',
        'url',
        'erreur',
        'copie',
        'repertoire',
        'variables',
        'expire_le',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'appli_id' => 'integer',
            'discussion_id' => 'integer',
            'correctif_id' => 'integer',
            'variables' => 'array',
            'expire_le' => 'datetime',
        ];
    }

    /** L'appli, même retirée du store : ses clients gardent leur copie. */
    public function appli(): BelongsTo
    {
        return $this->belongsTo(Appli::class)->withTrashed();
    }

    /**
     * @return BelongsTo<VersionAppli, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(VersionAppli::class, 'version_id');
    }

    /**
     * @return HasMany<MiseAJour, $this>
     */
    public function misesAJour(): HasMany
    {
        return $this->hasMany(MiseAJour::class);
    }

    /**
     * @return HasOne<MiseAJour, $this>
     */
    public function derniereMiseAJour(): HasOne
    {
        return $this->hasOne(MiseAJour::class)->latestOfMany();
    }

    /** Une version publiée plus récente que celle de la copie client, à appliquer quand le client le choisit. */
    public function versionDisponible(): ?VersionAppli
    {
        if ($this->type !== 'client' || $this->appli === null) {
            return null;
        }

        $publiee = $this->appli->versionPubliee;

        return $publiee !== null && $publiee->numero > (int) $this->version?->numero ? $publiee : null;
    }

    public function abonnement(): HasOne
    {
        return $this->hasOne(Abonnement::class);
    }

    public function discussion(): BelongsTo
    {
        return $this->belongsTo(Discussion::class);
    }

    public function correctif(): BelongsTo
    {
        return $this->belongsTo(Correctif::class);
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function changerEtat(string $etat, ?string $url = null, ?string $erreur = null): bool
    {
        $this->refresh();

        if ($this->etat === 'arrete' && $etat !== 'arrete') {
            return false;
        }

        // Un arrêt demandé ne peut plus être annulé par le lancement en cours.
        if ($this->etat === 'arret_en_cours' && ! in_array($etat, ['arrete', 'echec'], true)) {
            return false;
        }

        $this->etat = $etat;

        if ($etat === 'en_ligne') {
            $this->url = $url;
            $this->erreur = null;
        }

        if ($etat === 'echec') {
            $this->erreur = $erreur;
            $this->url = null;
        }

        if ($etat === 'arrete') {
            $this->url = null;
        }

        $this->save();
        $this->diffuser();

        return true;
    }

    /**
     * Demande l'arrêt de la copie au moteur, seul à parler à Docker (voir ArreterDeploiement).
     * Le job part avant toute diffusion : une panne de Reverb ne laisse jamais un arrêt sans job.
     * Redemandé alors qu'il est en cours, l'arrêt est relancé (le moteur est idempotent).
     */
    public function demanderArret(): void
    {
        $this->refresh();

        if ($this->etat === 'arrete') {
            return;
        }

        if ($this->etat !== 'arret_en_cours') {
            $this->forceFill(['etat' => 'arret_en_cours'])->save();
        }

        ArreterDeploiement::dispatch($this->id);
        $this->diffuser();
    }

    public function estEnCoursArret(): bool
    {
        return in_array($this->etat, ['arrete', 'arret_en_cours'], true);
    }

    /**
     * Pousse l'état par Reverb. Une panne de Reverb ne casse jamais un changement d'état :
     * l'état est déjà en base, le front le relit à la prochaine requête.
     */
    public function diffuser(): void
    {
        $this->loadMissing('appli');

        try {
            event(new DeploiementEtat($this->id, $this->versApi()));

            // La copie de test et la version corrigée se suivent aussi sur la discussion, publique.
            if ($this->type === 'test' && $this->discussion_id !== null) {
                event(new CopieTestEtat($this->discussion_id, $this->versApi()));
            }

            if ($this->type === 'correctif' && $this->correctif_id !== null) {
                $correctif = $this->correctif()->first();

                if ($correctif !== null) {
                    event(new CorrectifEtat($correctif));
                }
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Une copie de test peut partir d'un dépôt joint à une discussion, sans appli publiée.
     */
    public function depotACloner(): string
    {
        $this->loadMissing('appli');

        return (string) ($this->appli?->depot_url ?? $this->depot_url);
    }

    /**
     * @return array<string, mixed>
     */
    public function versApi(): array
    {
        $this->loadMissing(['appli.versionPubliee', 'version', 'derniereMiseAJour']);

        return [
            'id' => $this->id,
            'app_id' => $this->appli_id,
            'app_nom' => $this->appli?->nom ?? $this->nomDepot(),
            'taille' => $this->taille,
            'type' => $this->type,
            'etat' => $this->etat,
            'url' => $this->url,
            'erreur' => $this->erreur,
            'cree_le' => $this->created_at->toIso8601String(),
            'abonnement' => $this->type === 'client' ? $this->abonnement?->versApi() : null,
            'facture_a_payer' => $this->factureAPayer(),
            'version' => $this->version?->numero,
            'version_disponible' => $this->versionDisponible()?->resume(),
            'mise_a_jour' => $this->derniereMiseAJour?->versApi(),
        ];
    }

    /**
     * @return array{id: int, numero: string, montant_total: int, echeance_le: string, statut: string}|null
     */
    private function factureAPayer(): ?array
    {
        if ($this->type !== 'client') {
            return null;
        }

        $this->loadMissing('abonnement.facturesAPayer');
        $facture = $this->abonnement?->facturesAPayer->first();

        return $facture === null ? null : [
            'id' => $facture->id,
            'numero' => (string) $facture->numero,
            'montant_total' => $facture->montant_total,
            'echeance_le' => $facture->echeance_le->toDateString(),
            'statut' => $facture->statut,
        ];
    }

    private function nomDepot(): string
    {
        try {
            $depot = DepotGithub::depuisUrl((string) $this->depot_url);

            return $depot->owner.'/'.$depot->repo;
        } catch (DepotInvalide) {
            return (string) $this->depot_url;
        }
    }
}
