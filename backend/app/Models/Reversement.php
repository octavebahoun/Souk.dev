<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Paiement d'un dev sur son Mobile Money (payout SasPay, simulé tant que non activé). */
class Reversement extends Model
{
    protected $fillable = [
        'dev_id', 'montant', 'passerelle', 'cle_idempotence', 'pays', 'reseau', 'numero_masque',
        'beneficiaire', 'declenchement', 'statut',
    ];

    protected $hidden = ['beneficiaire'];

    protected function casts(): array
    {
        return [
            'beneficiaire' => 'encrypted:array',
            'montant' => 'integer',
            'frais' => 'integer',
            'verifie_le' => 'datetime',
            'alerte_le' => 'datetime',
            'essais' => 'integer',
        ];
    }

    public function dev(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dev_id');
    }
}
