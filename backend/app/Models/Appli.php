<?php

namespace App\Models;

use App\Jobs\MesurerMemoire;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appli extends Model
{
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
     * Remet la mesure à en_cours et lance le relevé en tâche de fond.
     * La taille choisie par le dev n'est pas modifiée.
     */
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

        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'description' => (string) $this->description,
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
                'username' => null,
                'avatar_url' => null,
                'github_url' => null,
                'profil' => 'dev',
                'pays' => null,
            ],
            'cree_le' => $this->created_at->toIso8601String(),
        ];
    }
}
