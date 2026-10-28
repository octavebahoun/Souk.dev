<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Code à 6 chiffres envoyé à un utilisateur d'une appli : gardé haché, 10 minutes, 5 essais. */
class CodeConnexion extends Model
{
    protected $table = 'codes_connexion';

    protected $fillable = ['deploiement_id', 'empreinte', 'code', 'expire_le'];

    protected $hidden = ['code'];

    protected function casts(): array
    {
        return [
            'essais' => 'integer',
            'expire_le' => 'datetime',
        ];
    }
}
