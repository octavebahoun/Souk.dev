<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CanalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Canal extends Model
{
    /** @use HasFactory<CanalFactory> */
    use HasFactory;

    protected $table = 'canaux';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'nom',
        'description',
        'pays',
    ];

    /**
     * Canaux ouverts sur toute installation : slug => [nom, description, pays].
     *
     * @var array<string, array{string, string, ?string}>
     */
    public const PAR_DEFAUT = [
        'entraide-generale' => ['Entraide générale', 'Pour tout le reste : projets, carrière, conseils.', null],
        'bugs-et-correctifs' => ['Bugs et correctifs', 'Postez un bug avec votre dépôt : un autre dev propose un correctif à essayer.', null],
        'mobile-money' => ['Mobile Money', 'MTN MoMo, Moov, Orange Money, Wave : intégrations et pièges.', null],
        'laravel' => ['Laravel', 'Tout sur Laravel, des files d’attente aux tests.', null],
        'react' => ['React', 'Composants, performances et rendu sur réseau lent.', null],
        'devops' => ['DevOps', 'Docker, déploiement, supervision.', null],
        'benin' => ['Bénin', 'Les devs du Bénin.', 'BJ'],
        'senegal' => ['Sénégal', 'Les devs du Sénégal.', 'SN'],
        'cote-d-ivoire' => ['Côte d’Ivoire', 'Les devs de Côte d’Ivoire.', 'CI'],
        'togo' => ['Togo', 'Les devs du Togo.', 'TG'],
    ];

    /** Ouvre les canaux de départ qui manquent, sans toucher aux autres. Renvoie le nombre créé. */
    public static function ouvrirParDefaut(): int
    {
        $ouverts = 0;

        foreach (self::PAR_DEFAUT as $slug => [$nom, $description, $pays]) {
            $canal = self::query()->firstOrCreate(['slug' => $slug], [
                'nom' => $nom,
                'description' => $description,
                'pays' => $pays,
            ]);

            $ouverts += $canal->wasRecentlyCreated ? 1 : 0;
        }

        return $ouverts;
    }

    /**
     * Les comptes qui suivent ce canal : ses discussions passent en premier dans leur fil.
     *
     * @return BelongsToMany<User, $this>
     */
    public function abonnes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'canaux_suivis');
    }

    /**
     * @return HasMany<Discussion, $this>
     */
    public function discussions(): HasMany
    {
        return $this->hasMany(Discussion::class);
    }

    public static function ouvrir(string $nom, ?string $description, ?string $pays): self
    {
        $base = Str::slug($nom);

        if ($base === '') {
            $base = 'canal';
        }

        $slug = $base;
        $suffixe = 2;

        while (self::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffixe;
            $suffixe++;
        }

        $canal = new self;
        $canal->slug = $slug;
        $canal->nom = $nom;
        $canal->description = $description;
        $canal->pays = $pays;
        $canal->save();

        return $canal;
    }
}
