<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une demande d'analyse Souk AI. Elle survit à sa discussion (la limite de l'auteur ne se
 * contourne pas en supprimant le bug) ; « perimee » quand la fiche du bug a changé depuis.
 */
class AnalyseIa extends Model
{
    /** Une analyse « en cours » plus vieille que ça est abandonnée (travail perdu, file en panne). */
    public const MINUTES_AVANT_ABANDON = 10;

    protected $table = 'analyses_ia';

    protected $fillable = ['discussion_id', 'user_id', 'statut', 'consentement_le'];

    protected function casts(): array
    {
        return [
            'jetons_entree' => 'integer',
            'jetons_sortie' => 'integer',
            'consentement_le' => 'datetime',
            'termine_le' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Discussion, $this>
     */
    public function discussion(): BelongsTo
    {
        return $this->belongsTo(Discussion::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function estAbandonnee(): bool
    {
        return $this->statut === 'en_cours' && $this->created_at?->lt(now()->subMinutes(self::MINUTES_AVANT_ABANDON));
    }

    /** @return array{statut: string, texte: string|null, cree_le: string|null} */
    public function versApi(bool $avecTexte = true): array
    {
        $statut = $this->estAbandonnee() ? 'echec' : (string) $this->statut;

        return [
            'statut' => $statut,
            'texte' => $statut === 'prete' && $avecTexte ? ($this->attributes['texte'] ?? null) : null,
            'cree_le' => $this->created_at?->toIso8601String(),
        ];
    }
}
