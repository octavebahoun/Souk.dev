<?php

namespace App\Models;

use Database\Factories\AbonnementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Facturation d'une copie client, à date anniversaire. Prix figés à la commande :
 * un changement de prix ou de formule de l'appli ne touche que les nouveaux clients.
 */
class Abonnement extends Model
{
    /** @use HasFactory<AbonnementFactory> */
    use HasFactory;

    public const STATUTS_ACTIFS = ['actif', 'en_retard'];

    protected $fillable = [
        'deploiement_id', 'client_id', 'dev_id', 'appli_id', 'formule', 'montant_hebergement',
        'prix_appli', 'type_prix', 'statut', 'periode_debut', 'prochaine_echeance',
    ];

    protected function casts(): array
    {
        return [
            'montant_hebergement' => 'integer',
            'prix_appli' => 'integer',
            'periode_debut' => 'date',
            'prochaine_echeance' => 'date',
            'suspendu_le' => 'datetime',
            'avertissement_arret_le' => 'datetime',
            'resilie_le' => 'datetime',
        ];
    }

    public function deploiement(): BelongsTo
    {
        return $this->belongsTo(Deploiement::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function dev(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dev_id');
    }

    public function appli(): BelongsTo
    {
        return $this->belongsTo(Appli::class)->withTrashed();
    }

    public function factures(): HasMany
    {
        return $this->hasMany(Facture::class);
    }

    /** Factures à régler, la plus ancienne d'abord. */
    public function facturesAPayer(): HasMany
    {
        return $this->hasMany(Facture::class)->where('statut', 'a_payer')->orderBy('echeance_le')->orderBy('id');
    }

    /** Ce que le client paie chaque mois hors prix unique (hébergement, plus l'appli si mensuelle). */
    public function montantMensuel(): int
    {
        return $this->montant_hebergement + ($this->type_prix === 'mensuel' ? $this->prix_appli : 0);
    }

    /**
     * @return array{statut: string, formule: string, montant_hebergement: int, prix_appli: int, type_prix: string, prochaine_echeance: string|null, suspendu_le: string|null}
     */
    public function versApi(): array
    {
        return [
            'statut' => $this->statut,
            'formule' => $this->formule,
            'montant_hebergement' => $this->montant_hebergement,
            'prix_appli' => $this->prix_appli,
            'type_prix' => $this->type_prix,
            'prochaine_echeance' => $this->prochaine_echeance?->toDateString(),
            'suspendu_le' => $this->suspendu_le?->toIso8601String(),
        ];
    }
}
