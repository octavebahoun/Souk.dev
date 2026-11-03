<?php

namespace App\Models;

use Database\Factories\AvisAppliFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Avis d'un client sur une appli dont il a eu une copie payée (le nom Avis sert aux événements).
 */
class AvisAppli extends Model
{
    /** @use HasFactory<AvisAppliFactory> */
    use HasFactory;

    protected $table = 'avis_applis';

    protected $fillable = ['appli_id', 'user_id', 'note', 'commentaire'];

    protected function casts(): array
    {
        return [
            'note' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Appli, $this>
     */
    public function appli(): BelongsTo
    {
        return $this->belongsTo(Appli::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
