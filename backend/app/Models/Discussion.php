<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DiscussionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Discussion extends Model
{
    /** @use HasFactory<DiscussionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'canal_id',
        'app_id',
        'titre',
        'texte',
        'depot_url',
        'demo_url',
        'etiquettes',
        'bug',
        'resolue',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'etiquettes' => 'array',
            'bug' => 'array',
            'resolue' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Canal, $this>
     */
    public function canal(): BelongsTo
    {
        return $this->belongsTo(Canal::class);
    }

    /**
     * Dernière copie de test lancée par l'auteur, null s'il n'en a jamais demandé.
     *
     * @return HasOne<Deploiement, $this>
     */
    public function copieTest(): HasOne
    {
        return $this->hasOne(Deploiement::class)->where('type', 'test')->latestOfMany();
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * @return HasMany<Correctif, $this>
     */
    public function correctifs(): HasMany
    {
        return $this->hasMany(Correctif::class);
    }

    /**
     * La dernière analyse Souk AI encore valable (une fiche modifiée périme les précédentes).
     *
     * @return HasOne<AnalyseIa, $this>
     */
    public function derniereAnalyse(): HasOne
    {
        return $this->hasOne(AnalyseIa::class)->ofMany(['id' => 'max'], fn ($analyses) => $analyses->where('statut', '!=', 'perimee'));
    }

    /**
     * La dernière analyse prête : elle reste affichée si une relance échoue.
     *
     * @return HasOne<AnalyseIa, $this>
     */
    public function dernierePrete(): HasOne
    {
        return $this->hasOne(AnalyseIa::class)->ofMany(['id' => 'max'], fn ($analyses) => $analyses->where('statut', 'prete'));
    }

    /** Ce que montre la discussion : l'analyse en cours ou prête, sinon un échec ; jamais une périmée. */
    public function analyseAffichee(): ?AnalyseIa
    {
        $derniere = $this->derniereAnalyse;

        if ($derniere !== null && ($derniere->statut === 'echec' || $derniere->estAbandonnee()) && $this->dernierePrete !== null) {
            return $this->dernierePrete;
        }

        return $derniere;
    }
}
