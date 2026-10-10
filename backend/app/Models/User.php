<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Profil;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'username', 'avatar_url', 'profil', 'pays', 'bio', 'competences', 'specialites', 'disponible'])]
#[Hidden(['password', 'remember_token', 'github_id'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'profil' => Profil::class,
            'github_lie' => 'boolean',
            'est_admin' => 'boolean',
            'disponible' => 'boolean',
            'competences' => 'array',
            'specialites' => 'array',
        ];
    }

    /**
     * @return HasMany<Discussion, $this>
     */
    public function discussions(): HasMany
    {
        return $this->hasMany(Discussion::class);
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * @return HasMany<LienMagique, $this>
     */
    public function liensMagiques(): HasMany
    {
        return $this->hasMany(LienMagique::class);
    }
}
