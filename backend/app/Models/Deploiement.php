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

    public function appli(): BelongsTo
    {
        return $this->belongsTo(Appli::class);
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
     */
    public function demanderArret(): void
    {
        $this->refresh();

        if (in_array($this->etat, ['arrete', 'arret_en_cours'], true)) {
            return;
        }

        $this->changerEtat('arret_en_cours');
        ArreterDeploiement::dispatch($this->id);
    }

    public function estEnCoursArret(): bool
    {
        return in_array($this->etat, ['arrete', 'arret_en_cours'], true);
    }

    public function diffuser(): void
    {
        $this->loadMissing('appli');

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
     * @return array{id: int, app_id: int|null, app_nom: string, taille: string, type: string, etat: string, url: string|null, erreur: string|null, cree_le: string}
     */
    public function versApi(): array
    {
        $this->loadMissing('appli');

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
