<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appli extends Model
{
    protected $table = 'applis';

    protected $fillable = [
        'user_id',
        'nom',
        'depot_url',
        'taille',
    ];

    public function deploiements(): HasMany
    {
        return $this->hasMany(Deploiement::class);
    }
}
