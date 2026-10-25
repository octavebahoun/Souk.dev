<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Avoir saisi par l'admin après un remboursement fait depuis le tableau de bord SasPay. */
class Avoir extends Model
{
    protected $fillable = ['facture_id', 'admin_id', 'montant_hebergement', 'montant_appli', 'motif'];

    protected function casts(): array
    {
        return [
            'montant_hebergement' => 'integer',
            'montant_appli' => 'integer',
        ];
    }

    public function facture(): BelongsTo
    {
        return $this->belongsTo(Facture::class);
    }

    public function montantTotal(): int
    {
        return $this->montant_hebergement + $this->montant_appli;
    }

    public function numeroter(): void
    {
        if ($this->numero === null) {
            $this->forceFill(['numero' => sprintf('AV-%s-%06d', $this->created_at->format('Y'), $this->id)])->save();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function versApi(): array
    {
        return [
            'id' => $this->id,
            'numero' => (string) $this->numero,
            'facture_id' => $this->facture_id,
            'montant_hebergement' => $this->montant_hebergement,
            'montant_appli' => $this->montant_appli,
            'montant_total' => $this->montantTotal(),
            'motif' => $this->motif,
            'cree_le' => $this->created_at->toIso8601String(),
        ];
    }
}
