<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Réponse gardée 24 h pour une requête qui déplace de l'argent, rejouée avec la même clé. */
class CleIdempotence extends Model
{
    protected $table = 'cles_idempotence';

    protected $fillable = ['user_id', 'route', 'cle', 'empreinte_corps', 'statut_http', 'reponse', 'expire_le'];

    protected function casts(): array
    {
        return [
            'statut_http' => 'integer',
            'expire_le' => 'datetime',
        ];
    }
}
