<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Une page vue, sans donnée personnelle (voir App\Audience\Mesure). */
class Visite extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['chemin', 'referent', 'source', 'pays', 'appareil', 'visiteur'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
