<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Message d'une conversation privée entre deux devs. */
class MessagePrive extends Model
{
    protected $table = 'messages_prives';

    protected $fillable = ['conversation_id', 'auteur_id', 'texte'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['conversation_id' => 'integer', 'auteur_id' => 'integer'];
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }
}
