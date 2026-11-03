<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Numéro Mobile Money où le dev reçoit ses reversements. Chiffré en base, jamais renvoyé
 * ni journalisé en entier ; inutilisable tant que le lien de confirmation n'est pas ouvert.
 */
class CoordonneesReversement extends Model
{
    protected $table = 'coordonnees_reversement';

    protected $fillable = ['user_id', 'pays', 'reseau', 'numero', 'numero_masque'];

    protected $hidden = ['numero', 'jeton_confirmation_hash'];

    protected function casts(): array
    {
        return [
            'numero' => 'encrypted',
            'jeton_expire_le' => 'datetime',
            'confirme_le' => 'datetime',
        ];
    }

    public function compte(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function estConfirme(): bool
    {
        return $this->confirme_le !== null;
    }

    /**
     * @return array{pays: string, reseau: string, numero_masque: string, confirme: bool, confirme_le: string|null}
     */
    public function versApi(): array
    {
        return [
            'pays' => $this->pays,
            'reseau' => $this->reseau,
            'numero_masque' => $this->numero_masque,
            'confirme' => $this->estConfirme(),
            'confirme_le' => $this->confirme_le?->toIso8601String(),
        ];
    }
}
