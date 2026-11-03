<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Session de paiement ou payout du simulateur SasPay (développement et tests). */
class SimulationSasPay extends Model
{
    use HasUuids;

    protected $table = 'simulations_saspay';

    protected $fillable = ['type', 'transaction_id', 'reference', 'montant', 'frais', 'statut', 'reseau', 'return_url', 'metadata'];

    protected function casts(): array
    {
        return [
            'montant' => 'integer',
            'frais' => 'integer',
            'metadata' => 'array',
        ];
    }
}
