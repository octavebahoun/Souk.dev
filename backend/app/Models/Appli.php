<?php

namespace App\Models;

use App\Jobs\MesurerMemoire;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appli extends Model
{
    protected $table = 'applis';

    protected $fillable = [
        'user_id',
        'nom',
        'depot_url',
        'taille',
    ];

    protected function casts(): array
    {
        return [
            'memoire_max_mo' => 'integer',
            'mesure_jeton' => 'integer',
            'mesure_le' => 'datetime',
        ];
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
}
