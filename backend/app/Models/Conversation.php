<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Conversation privée entre deux devs. Elle commence par une invitation : tant que le
 * destinataire ne l'a pas acceptée, personne ne peut écrire (pas de message non sollicité).
 */
class Conversation extends Model
{
    public const EN_ATTENTE = 'en_attente';

    public const ACCEPTEE = 'acceptee';

    public const REFUSEE = 'refusee';

    /** Après un refus, le même dev ne peut pas réinviter avant ce délai. */
    public const JOURS_AVANT_NOUVELLE_INVITATION = 30;

    protected $fillable = ['demandeur_id', 'destinataire_id', 'discussion_id', 'statut', 'invitation'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'demandeur_id' => 'integer',
            'destinataire_id' => 'integer',
            'discussion_id' => 'integer',
            'repondu_le' => 'datetime',
            'dernier_message_le' => 'datetime',
            'lu_demandeur_le' => 'datetime',
            'lu_destinataire_le' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function demandeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'demandeur_id');
    }

    /** @return BelongsTo<User, $this> */
    public function destinataire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destinataire_id');
    }

    /** @return BelongsTo<Discussion, $this> */
    public function discussion(): BelongsTo
    {
        return $this->belongsTo(Discussion::class);
    }

    /** @return HasMany<MessagePrive, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(MessagePrive::class);
    }

    /** @return HasOne<MessagePrive, $this> */
    public function dernierMessage(): HasOne
    {
        return $this->hasOne(MessagePrive::class)->latestOfMany();
    }

    /**
     * @param  Builder<self>  $requete
     * @return Builder<self>
     */
    public function scopeDe(Builder $requete, User $compte): Builder
    {
        return $requete->where(fn (Builder $paire) => $paire->where('demandeur_id', $compte->id)->orWhere('destinataire_id', $compte->id));
    }

    /** La conversation de ces deux comptes, quel que soit celui qui a invité l'autre. */
    public static function entre(User $un, User $autre): ?self
    {
        return self::query()
            ->where(fn (Builder $sens) => $sens->where('demandeur_id', $un->id)->where('destinataire_id', $autre->id))
            ->orWhere(fn (Builder $sens) => $sens->where('demandeur_id', $autre->id)->where('destinataire_id', $un->id))
            ->first();
    }

    public function concerne(User $compte): bool
    {
        return $this->demandeur_id === $compte->id || $this->destinataire_id === $compte->id;
    }

    public function autre(User $compte): ?User
    {
        return $this->demandeur_id === $compte->id ? $this->destinataire : $this->demandeur;
    }

    public function estAcceptee(): bool
    {
        return $this->statut === self::ACCEPTEE;
    }

    /** Un refus se lève de lui-même après le délai : le dev peut alors être réinvité. */
    public function peutEtreRelancee(): bool
    {
        return $this->statut === self::REFUSEE
            && ($this->repondu_le === null || $this->repondu_le->lte(now()->subDays(self::JOURS_AVANT_NOUVELLE_INVITATION)));
    }

    public function marquerLue(User $compte): void
    {
        $this->forceFill([$this->demandeur_id === $compte->id ? 'lu_demandeur_le' : 'lu_destinataire_le' => now()])->save();
    }

    /** Messages de l'autre arrivés depuis la dernière lecture de ce compte. */
    public function nonLus(User $compte): int
    {
        $luLe = $this->demandeur_id === $compte->id ? $this->lu_demandeur_le : $this->lu_destinataire_le;

        return $this->messages()
            ->where('auteur_id', '!=', $compte->id)
            ->when($luLe !== null, fn ($messages) => $messages->where('created_at', '>', $luLe))
            ->count();
    }
}
