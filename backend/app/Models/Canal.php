<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CanalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Canal extends Model
{
    /** @use HasFactory<CanalFactory> */
    use HasFactory;

    protected $table = 'canaux';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'nom',
        'description',
        'pays',
    ];

    /**
     * @return HasMany<Discussion, $this>
     */
    public function discussions(): HasMany
    {
        return $this->hasMany(Discussion::class);
    }

    public static function ouvrir(string $nom, ?string $description, ?string $pays): self
    {
        $base = Str::slug($nom);

        if ($base === '') {
            $base = 'canal';
        }

        $slug = $base;
        $suffixe = 2;

        while (self::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffixe;
            $suffixe++;
        }

        $canal = new self;
        $canal->slug = $slug;
        $canal->nom = $nom;
        $canal->description = $description;
        $canal->pays = $pays;
        $canal->save();

        return $canal;
    }
}
