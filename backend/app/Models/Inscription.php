<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inscription extends Model
{
    protected $table = 'inscriptions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'evenement_id',
        'user_id',
    ];

    /**
     * @return BelongsTo<Evenement, $this>
     */
    public function evenement(): BelongsTo
    {
        return $this->belongsTo(Evenement::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function compte(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
