<?php

namespace App\Models;

use App\Moteur\LimitesTaille;
use App\Moteur\MachineCible;
use App\Moteur\MachineDocker;
use Database\Factories\MachineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un serveur du moteur : un démon Docker qui héberge des copies. « locale » est le démon de
 * la plateforme elle-même. Réservé à l'équipe (voir MachineController).
 */
class Machine extends Model
{
    /** @use HasFactory<MachineFactory> */
    use HasFactory;

    protected $table = 'machines';

    protected $fillable = [
        'nom',
        'hote_docker',
        'etat',
        'capacite_mo',
    ];

    protected function casts(): array
    {
        return [
            'joignable' => 'boolean',
            'memoire_totale_mo' => 'integer',
            'capacite_mo' => 'integer',
            'sondee_le' => 'datetime',
        ];
    }

    /** États d'une copie qui occupent encore de la mémoire sur sa machine. */
    public const ETATS_OCCUPES = [
        'en_file', 'construction', 'demarrage', 'en_ligne', 'suspendu', 'mise_a_jour', 'arret_en_cours',
    ];

    /**
     * @return HasMany<Deploiement, $this>
     */
    public function deploiements(): HasMany
    {
        return $this->hasMany(Deploiement::class);
    }

    /** Le démon local existe toujours : le placement le recrée s'il manque. */
    public static function assurerLocale(): self
    {
        return self::query()->firstOrCreate(
            ['nom' => 'locale'],
            ['hote_docker' => null, 'etat' => 'active', 'joignable' => true],
        );
    }

    public function cible(): MachineCible
    {
        return new MachineDocker($this->hote_docker);
    }

    /** Copies hébergées : ni arrêtées, ni en échec (leurs conteneurs sont libérés). */
    public function nbCopies(): int
    {
        return $this->deploiements()->whereIn('etat', self::ETATS_OCCUPES)->count();
    }

    /** Plafonds des copies hébergées, en Mo. */
    public function memoireReserveeMo(): int
    {
        return $this->deploiements()
            ->whereIn('etat', self::ETATS_OCCUPES)
            ->pluck('taille')
            ->sum(fn (string $taille) => LimitesTaille::connue($taille) ? LimitesTaille::plafondMo($taille) : 0);
    }

    /** Capacité utile : déclarée par l'équipe, sinon la mémoire sondée, sinon sans limite. */
    public function capaciteUtileMo(): ?int
    {
        return $this->capacite_mo ?? $this->memoire_totale_mo;
    }

    /** Mémoire encore plaçable, en Mo ; null sans limite connue. */
    public function memoireLibreMo(): ?int
    {
        $capacite = $this->capaciteUtileMo();

        return $capacite === null ? null : $capacite - $this->memoireReserveeMo();
    }

    public function recoitEncoreDesCopies(): bool
    {
        return $this->etat === 'active' && $this->joignable;
    }

    /**
     * @return array<string, mixed>
     */
    public function versApi(): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'hote_docker' => $this->hote_docker,
            'etat' => $this->etat,
            'joignable' => $this->joignable,
            'version_docker' => $this->version_docker,
            'memoire_totale_mo' => $this->memoire_totale_mo,
            'capacite_mo' => $this->capacite_mo,
            'memoire_reservee_mo' => $this->memoireReserveeMo(),
            'memoire_libre_mo' => $this->memoireLibreMo(),
            'copies' => $this->nbCopies(),
            'sonde_erreur' => $this->sonde_erreur,
            'sondee_le' => $this->sondee_le?->toIso8601String(),
        ];
    }
}
