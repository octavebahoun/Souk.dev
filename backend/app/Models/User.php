<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Profil;
use App\Notifications\TypesNotification;
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
            'est_demo' => 'boolean',
            'disponible' => 'boolean',
            'souk_score' => 'integer',
            'profil_calcule' => 'array',
            'profil_calcule_le' => 'datetime',
            'competences' => 'array',
            'specialites' => 'array',
            'preferences_notifications' => 'array',
        ];
    }

    /**
     * Reçoit ce type de notification sur ce canal (application ou email) ; tout est reçu tant que
     * le compte n'a rien coupé, et un e-mail obligatoire part toujours.
     */
    public function recoitNotification(string $type, string $canal): bool
    {
        if ($canal === 'email' && (TypesNotification::TYPES[$type]['email_obligatoire'] ?? false)) {
            return true;
        }

        return (bool) ($this->preferences_notifications[$type][$canal] ?? true);
    }

    /** Canal privé où le centre de notifications reçoit les nouvelles en direct. */
    public function receivesBroadcastNotificationsOn(): string
    {
        return 'utilisateur.'.$this->id;
    }

    /**
     * Adresse des e-mails de notification. Les comptes du jeu de démo n'en reçoivent jamais :
     * leurs adresses (@demo.souk.dev) n'existent pas, et des rebonds abîmeraient la réputation
     * du domaine d'envoi.
     */
    public function routeNotificationForMail(): ?string
    {
        return $this->est_demo ? null : $this->email;
    }

    /** Peut recevoir un e-mail (compte réel avec une adresse). */
    public function joignableParEmail(): bool
    {
        return ! $this->est_demo && is_string($this->email) && $this->email !== '';
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
}
