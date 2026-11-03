<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Comparaison quotidienne des transactions SasPay avec les paiements enregistrés. */
class Rapprochement extends Model
{
    protected $fillable = ['jour', 'transactions_saspay', 'paiements_locaux', 'ecarts'];

    protected function casts(): array
    {
        return [
            'jour' => 'date',
            'transactions_saspay' => 'integer',
            'paiements_locaux' => 'integer',
            'ecarts' => 'array',
        ];
    }
}
