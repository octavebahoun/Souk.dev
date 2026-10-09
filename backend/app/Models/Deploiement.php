<?php

namespace App\Models;

use App\Events\DeploiementEtat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deploiement extends Model
{
    protected $table = 'deploiements';

    protected $fillable = [
        'user_id',
        'appli_id',
        'type',
        'etat',
        'url',
        'erreur',
        'copie',
        'repertoire',
        'variables',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'appli_id' => 'integer',
            'variables' => 'array',
        ];
    }

    public function appli(): BelongsTo
    {
        return $this->belongsTo(Appli::class);
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function changerEtat(string $etat, ?string $url = null, ?string $erreur = null): bool
    {
        $this->refresh();

        if ($this->etat === 'arrete' && $etat !== 'arrete') {
            return false;
        }

        $this->etat = $etat;

        if ($etat === 'en_ligne') {
            $this->url = $url;
            $this->erreur = null;
        }

        if ($etat === 'echec') {
            $this->erreur = $erreur;
            $this->url = null;
        }

        if ($etat === 'arrete') {
            $this->url = null;
        }

        $this->save();
        $this->diffuser();

        return true;
    }

    public function diffuser(): void
    {
        $this->loadMissing('appli');

        event(new DeploiementEtat($this->id, $this->versApi()));
    }

    /**
     * @return array{id: int, app_id: int, app_nom: string, type: string, etat: string, url: string|null, erreur: string|null, cree_le: string}
     */
    public function versApi(): array
    {
        $this->loadMissing('appli');

        return [
            'id' => $this->id,
            'app_id' => $this->appli_id,
            'app_nom' => $this->appli->nom,
            'type' => $this->type,
            'etat' => $this->etat,
            'url' => $this->url,
            'erreur' => $this->erreur,
            'cree_le' => $this->created_at->toIso8601String(),
        ];
    }
}
