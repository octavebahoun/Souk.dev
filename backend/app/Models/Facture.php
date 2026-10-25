<?php

namespace App\Models;

use Database\Factories\FactureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facture extends Model
{
    /** @use HasFactory<FactureFactory> */
    use HasFactory;

    protected $fillable = [
        'abonnement_id', 'client_id', 'periode_debut', 'periode_fin', 'echeance_le',
        'montant_hebergement', 'montant_appli', 'montant_total', 'statut',
    ];

    protected function casts(): array
    {
        return [
            'periode_debut' => 'date',
            'periode_fin' => 'date',
            'echeance_le' => 'date',
            'montant_hebergement' => 'integer',
            'montant_appli' => 'integer',
            'montant_total' => 'integer',
            'payee_le' => 'datetime',
            'rappel_envoye_le' => 'datetime',
            'retard_signale_le' => 'datetime',
        ];
    }

    public function abonnement(): BelongsTo
    {
        return $this->belongsTo(Abonnement::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class)->latest('id');
    }

    public function avoirs(): HasMany
    {
        return $this->hasMany(Avoir::class);
    }

    /** Numéro lisible et continu, posé une fois l'identifiant connu : SD-2026-000123. */
    public function numeroter(): void
    {
        if ($this->numero === null) {
            $this->forceFill(['numero' => sprintf('SD-%s-%06d', $this->created_at->format('Y'), $this->id)])->save();
        }
    }

    public function estPayee(): bool
    {
        return $this->statut === 'payee';
    }
}
