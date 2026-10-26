<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une demande d'analyse Souk AI : la dernière d'une discussion est affichée, toutes comptent
 * pour la limite quotidienne de leur auteur et gardent la consommation du fournisseur.
 */
class AnalyseIa extends Model
{
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

    /** @return array{statut: string, texte: string|null, cree_le: string|null} */
    public function versApi(): array
    {
        return [
            'statut' => (string) $this->statut,
            'texte' => $this->statut === 'prete' ? $this->texte : null,
            'cree_le' => $this->created_at?->toIso8601String(),
        ];
    }
}
