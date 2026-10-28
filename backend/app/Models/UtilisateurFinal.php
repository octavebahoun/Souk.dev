<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un utilisateur d'une appli déployée, connecté à son backend intégré par un code SMS ou e-mail. */
class UtilisateurFinal extends Model
{
    use HasUuids;

    protected $table = 'utilisateurs_finaux';

    protected $fillable = ['deploiement_id', 'canal', 'empreinte', 'destinataire'];

    protected $hidden = ['destinataire'];

    protected function casts(): array
    {
        return [
            'destinataire' => 'encrypted',
            'derniere_connexion_le' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Deploiement, $this>
     */
    public function deploiement(): BelongsTo
    {
        return $this->belongsTo(Deploiement::class);
    }
}
