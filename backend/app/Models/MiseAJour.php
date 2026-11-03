<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une mise à jour d'une copie vers une nouvelle version : sauvegarde des volumes, redéploiement,
 * retour à la version précédente si la nouvelle ne démarre pas.
 */
class MiseAJour extends Model
{
    protected $table = 'mises_a_jour';

    protected $fillable = ['deploiement_id', 'de_version_id', 'vers_version_id', 'statut', 'variables'];

    protected $hidden = ['variables'];

    protected function casts(): array
    {
        return [
            // Les nouvelles variables du client peuvent contenir des clés : chiffrées en base.
            'variables' => 'encrypted:array',
            'termine_le' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Deploiement, $this>
     */
    public function deploiement(): BelongsTo
    {
        return $this->belongsTo(Deploiement::class);
    }

    /**
     * @return BelongsTo<VersionAppli, $this>
     */
    public function deVersion(): BelongsTo
    {
        return $this->belongsTo(VersionAppli::class, 'de_version_id');
    }

    /**
     * @return BelongsTo<VersionAppli, $this>
     */
    public function versVersion(): BelongsTo
    {
        return $this->belongsTo(VersionAppli::class, 'vers_version_id');
    }

    /** @return array<string, mixed> */
    public function versApi(): array
    {
        $this->loadMissing(['deVersion', 'versVersion']);

        return [
            'statut' => (string) $this->statut,
            'de_version' => $this->deVersion?->numero,
            'vers_version' => (int) $this->versVersion?->numero,
            'erreur' => $this->erreur,
            'cree_le' => $this->created_at?->toIso8601String(),
            'termine_le' => $this->termine_le?->toIso8601String(),
        ];
    }
}
