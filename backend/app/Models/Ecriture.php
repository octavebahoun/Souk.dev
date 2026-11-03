<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Écriture du grand livre (entiers FCFA). Jamais modifiée ni supprimée : une erreur se corrige
 * par une écriture inverse (voir App\Paiement\GrandLivre). PostgreSQL le refuse aussi (trigger).
 */
class Ecriture extends Model
{
    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = null;

    public const COMPTE_SASPAY = 'saspay';

    public const COMPTE_DEV = 'dev';

    public const COMPTE_REVENU_HEBERGEMENT = 'revenu_hebergement';

    public const COMPTE_FRAIS_REVERSEMENT = 'charge_frais_reversement';

    /** Frais retenus par SasPay sur un encaissement (ADD_ON non appliqué) : une charge de Souk.dev. */
    public const COMPTE_FRAIS_ENCAISSEMENT = 'charge_frais_encaissement';

    protected $fillable = [
        'operation', 'type', 'compte', 'dev_id', 'montant', 'facture_id', 'paiement_id',
        'reversement_id', 'avoir_id', 'contrepasse_id', 'libelle',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'integer',
            'cree_le' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Le grand livre ne se modifie pas : passez une écriture inverse.'));
        static::deleting(fn () => throw new LogicException('Le grand livre ne se supprime pas : passez une écriture inverse.'));
    }

    public function dev(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dev_id');
    }

    public function facture(): BelongsTo
    {
        return $this->belongsTo(Facture::class);
    }
}
