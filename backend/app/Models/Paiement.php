<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tentative de paiement d'une facture par une session de checkout SasPay (ou simulée).
 * Son statut reflète toujours la dernière vérification par l'API, jamais le navigateur.
 */
class Paiement extends Model
{
    public const STATUTS_FINAUX = ['reussi', 'echoue', 'annule'];

    protected $fillable = [
        'facture_id', 'client_id', 'passerelle', 'session_id', 'session_slug', 'checkout_url',
        'montant', 'statut',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'integer',
            'frais_client' => 'integer',
            'montant_debite' => 'integer',
            'verifie_le' => 'datetime',
        ];
    }

    public function facture(): BelongsTo
    {
        return $this->belongsTo(Facture::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function estFinal(): bool
    {
        return in_array($this->statut, self::STATUTS_FINAUX, true);
    }
}
