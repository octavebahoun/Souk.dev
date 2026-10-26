<?php

namespace App\Models;

use App\Enums\Profil;
use App\Jobs\AnalyserDependances;
use App\Jobs\MesurerMemoire;
use Database\Factories\AppliFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Appli extends Model
{
    /** @use HasFactory<AppliFactory> */
    use HasFactory, SoftDeletes;

    public const CATEGORIES = [
        'commerce', 'gestion', 'education', 'sante', 'finance',
        'association', 'restauration', 'autre',
    ];

    public const TECHNOS = [
        'laravel', 'symfony', 'php', 'node', 'express', 'nestjs', 'nextjs',
        'react', 'vue', 'angular', 'svelte', 'django', 'flask', 'fastapi',
        'spring', 'dotnet', 'go', 'postgresql', 'mysql', 'mongodb', 'redis', 'autre',
    ];

    protected $table = 'applis';

    protected $fillable = [
        'user_id',
        'nom',
        'description',
        'categorie',
        'prix',
        'type_prix',
        'taille',
        'demo_url',
        'depot_url',
        'stack',
        'captures',
        'backend',
        'variables_client',
    ];

    protected function casts(): array
    {
        return [
            'prix' => 'integer',
            'vues' => 'integer',
            'avis_nombre' => 'integer',
            'avis_somme' => 'integer',
            'stack' => 'array',
            'captures' => 'array',
            'variables_client' => 'array',
            'securite_problemes' => 'array',
            'securite_analyse_le' => 'datetime',
            'memoire_max_mo' => 'integer',
            'mesure_jeton' => 'integer',
            'mesure_le' => 'datetime',
        ];
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function deploiements(): HasMany
    {
        return $this->hasMany(Deploiement::class);
    }

    /**
     * @return HasMany<AvisAppli, $this>
     */
    public function avis(): HasMany
    {
        return $this->hasMany(AvisAppli::class);
    }

    /**
     * Seul un client qui a eu une copie payée de l'appli la note (jamais son auteur).
     */
    public function peutEtreNoteePar(User $compte): bool
    {
        return $this->user_id !== $compte->id
            && Facture::query()
                ->where('client_id', $compte->id)
                ->where('statut', 'payee')
                ->whereHas('abonnement', fn ($abonnement) => $abonnement->where('appli_id', $this->id))
                ->exists();
    }

    /**
     * Recalcule le nombre et la somme des notes depuis la table des avis : appelé dans la
     * transaction qui verrouille l'appli, deux avis simultanés ne s'écrasent pas.
     */
    public function recalculerAvis(): void
    {
        static::withoutTimestamps(fn () => $this->forceFill([
            'avis_nombre' => $this->avis()->count(),
            'avis_somme' => (int) $this->avis()->sum('note'),
        ])->saveQuietly());
    }

    /** Moyenne à une décimale, null sans avis. */
    public function noteMoyenne(): ?float
    {
        return $this->avis_nombre > 0 ? round($this->avis_somme / $this->avis_nombre, 1) : null;
    }

    /**
     * Un visiteur (compte ou IP) n'est compté qu'une fois par jour sur la fiche.
     */
    public function compterVue(Request $request): void
    {
        $visiteur = $request->user()?->id ?? $request->ip();
        $cle = 'appli-vue:'.$this->id.':'.$visiteur.':'.now()->toDateString();

        if (Cache::add($cle, true, now()->endOfDay())) {
            $this->increment('vues');
        }
    }

    /**
     * Remet la mesure à en_cours et lance le relevé en tâche de fond.
     * La taille choisie par le dev n'est pas modifiée.
     */
    /** Analyse des dépendances (Trivy) sur le moteur ; le badge reste « en cours » jusqu'au résultat. */
    public function demanderAnalyseSecurite(): void
    {
        $this->forceFill([
            'securite_statut' => 'en_cours',
            'securite_jeton' => (string) Str::uuid(),
        ])->save();

        AnalyserDependances::dispatch($this->id, (string) $this->securite_jeton);
    }

    public function demanderMesure(): void
    {
        $this->forceFill([
            'mesure_statut' => 'en_cours',
            'memoire_max_mo' => null,
            'taille_recommandee' => null,
            'mesure_le' => null,
            'mesure_jeton' => ((int) $this->mesure_jeton) + 1,
        ])->save();

        MesurerMemoire::dispatch($this->id, $this->mesure_jeton);
    }

    public function terminerMesure(int $picMo, string $tailleRecommandee): void
    {
        $this->forceFill([
            'mesure_statut' => 'terminee',
            'memoire_max_mo' => $picMo,
            'taille_recommandee' => $tailleRecommandee,
            'mesure_le' => now(),
        ])->save();
    }

    public function echouerMesure(): void
    {
        $this->forceFill([
            'mesure_statut' => 'echec',
            'memoire_max_mo' => null,
            'taille_recommandee' => null,
            'mesure_le' => now(),
        ])->save();
    }

    /**
     * @return array{statut: string, memoire_max_mo: int|null, taille_recommandee: string|null, mesure_le: string|null}
     */
    public function mesureVersApi(): array
    {
        return [
            'statut' => (string) $this->mesure_statut,
            'memoire_max_mo' => $this->memoire_max_mo,
            'taille_recommandee' => $this->taille_recommandee,
            'mesure_le' => $this->mesure_le?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function versApi(): array
    {
        $this->loadMissing('auteur');
        $profilAuteur = $this->auteur->profil;

        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'description' => (string) $this->description,
            'readme' => null,
            'categorie' => (string) $this->categorie,
            'prix' => (int) $this->prix,
            'type_prix' => (string) $this->type_prix,
            'taille' => $this->taille,
            'mesure' => $this->mesureVersApi(),
            'demo_url' => (string) $this->demo_url,
            'depot_url' => $this->depot_url,
            'captures' => $this->captures ?? [],
            'stack' => $this->stack ?? [],
            'backend' => (string) $this->backend,
            'variables_client' => $this->variables_client ?? [],
            'securite' => [
                'statut' => (string) ($this->securite_statut ?: 'en_cours'),
                'analyse_le' => $this->securite_analyse_le?->toIso8601String(),
                'problemes' => $this->securite_problemes ?? [],
            ],
            'auteur' => [
                'id' => $this->auteur->id,
                'nom' => $this->auteur->name,
                'username' => $this->auteur->username,
                'avatar_url' => $this->auteur->avatar_url,
                'github_url' => $this->auteur->username !== null ? 'https://github.com/'.$this->auteur->username : null,
                'profil' => $profilAuteur instanceof Profil ? $profilAuteur->value : ($profilAuteur ?: 'dev'),
                'pays' => $this->auteur->pays,
            ],
            'cree_le' => $this->created_at->toIso8601String(),
            'note_moyenne' => $this->noteMoyenne(),
            'nb_avis' => (int) $this->avis_nombre,
        ];
    }
}
