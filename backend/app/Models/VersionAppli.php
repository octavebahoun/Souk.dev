<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une version d'appli : « verification » le temps de la contrôler comme une publication, puis
 * « publiee » (avec son commit) ou « refusee » (avec la raison).
 */
class VersionAppli extends Model
{
    protected $table = 'versions_applis';

    protected $fillable = ['appli_id', 'numero', 'ref', 'notes', 'statut'];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'manifeste' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Appli, $this>
     */
    public function appli(): BelongsTo
    {
        return $this->belongsTo(Appli::class);
    }

    /** @return array<string, mixed> */
    public function versApi(): array
    {
        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'ref' => $this->ref,
            'commit' => $this->commit,
            'notes' => $this->notes,
            'statut' => (string) $this->statut,
            'erreur' => $this->erreur,
            'cree_le' => $this->created_at?->toIso8601String(),
        ];
    }

    /** @return array{numero: int, notes: string|null, cree_le: string|null} */
    public function resume(): array
    {
        return ['numero' => $this->numero, 'notes' => $this->notes, 'cree_le' => $this->created_at?->toIso8601String()];
    }
}
