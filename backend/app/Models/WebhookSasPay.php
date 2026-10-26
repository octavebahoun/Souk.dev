<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Webhook SasPay reçu (empreinte du corps) : un même événement n'est traité qu'une fois. */
class WebhookSasPay extends Model
{
    protected $table = 'webhooks_saspay';

    protected $fillable = ['empreinte', 'evenement', 'transaction_id', 'montant'];

    protected function casts(): array
    {
        return ['traite_le' => 'datetime', 'montant' => 'integer'];
    }
}
