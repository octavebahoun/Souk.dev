<?php

declare(strict_types=1);

namespace App\Models;

use App\Http\Resources\DevResource;
use Database\Factories\SignalementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Un signalement de modération : un membre signale une discussion, un message, une appli,
 * une copie, un compte ou un événement. L'équipe le traite (action : suppression, suspension)
 * ou le rejette. Le résumé de la cible est calculé à la lecture : si la cible a été supprimée
 * entre-temps, le signalement le dit au lieu de casser la liste.
 */
class Signalement extends Model
{
    /** @use HasFactory<SignalementFactory> */
    use HasFactory;

    protected $table = 'signalements';

    protected $fillable = [
        'user_id',
        'cible',
        'cible_id',
        'motif',
        'details',
    ];

    public const CIBLES = ['discussion', 'message', 'appli', 'copie', 'compte', 'evenement'];

    public const MOTIFS = ['spam', 'abus', 'hameconnage', 'minage', 'contenu_illegal', 'autre'];

    public const STATUTS = ['ouvert', 'traite', 'rejete'];

    protected function casts(): array
    {
        return [
            'cible_id' => 'integer',
            'traite_le' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function signalePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function traitePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traite_par');
    }

    /**
     * Chemin du front (ou adresse de la copie) vers la cible, pour l'examiner avant de
     * décider. Null quand la cible n'a pas de page (copie sans adresse, compte sans
     * GitHub lié, cible supprimée).
     */
    public function cibleChemin(): ?string
    {
        return match ($this->cible) {
            'discussion' => "/echange/{$this->cible_id}",
            'message' => $this->cheminMessage(),
            'appli' => "/store/{$this->cible_id}",
            'copie' => $this->cheminCopie(),
            'compte' => $this->cheminCompte(),
            'evenement' => "/evenements#{$this->cible_id}",
            default => null,
        };
    }

    private function cheminMessage(): ?string
    {
        $discussionId = Message::query()->whereKey($this->cible_id)->value('discussion_id');

        return $discussionId === null ? null : "/echange/{$discussionId}";
    }

    private function cheminCopie(): ?string
    {
        $url = Deploiement::query()->whereKey($this->cible_id)->value('url');

        return is_string($url) && $url !== '' ? $url : null;
    }

    private function cheminCompte(): ?string
    {
        $username = User::query()->whereKey($this->cible_id)->value('username');

        return is_string($username) && $username !== '' ? "/devs/{$username}" : null;
    }

    /** Résumé lisible de la cible pour l'équipe (titre, nom, adresse…). */
    public function cibleResume(): string
    {
        return match ($this->cible) {
            'discussion' => $this->resumeDiscussion(),
            'message' => $this->resumeMessage(),
            'appli' => $this->resumeAppli(),
            'copie' => $this->resumeCopie(),
            'compte' => $this->resumeCompte(),
            'evenement' => $this->resumeEvenement(),
            default => "Cible inconnue #{$this->cible_id}",
        };
    }

    private function resumeDiscussion(): string
    {
        $discussion = Discussion::query()->find($this->cible_id);

        return $discussion === null
            ? "Discussion #{$this->cible_id} (supprimée)"
            : "Discussion #{$discussion->id} : ".Str::limit((string) $discussion->titre, 80);
    }

    private function resumeMessage(): string
    {
        $message = Message::query()->find($this->cible_id);

        return $message === null
            ? "Message #{$this->cible_id} (supprimé)"
            : "Message #{$message->id} (discussion #{$message->discussion_id}) : ".Str::limit((string) $message->texte, 80);
    }

    private function resumeAppli(): string
    {
        $appli = Appli::query()->withTrashed()->find($this->cible_id);

        return $appli === null
            ? "Appli #{$this->cible_id} (supprimée)"
            : "Appli #{$appli->id} : {$appli->nom}";
    }

    private function resumeCopie(): string
    {
        $copie = Deploiement::query()->with('appli')->find($this->cible_id);

        if ($copie === null) {
            return "Copie #{$this->cible_id} (supprimée)";
        }

        $nom = $copie->appli?->nom ?? $copie->url ?? "copie #{$copie->id}";

        return "Copie #{$copie->id} ({$nom}, {$copie->etat})";
    }

    private function resumeCompte(): string
    {
        $compte = User::query()->find($this->cible_id);

        if ($compte === null) {
            return "Compte #{$this->cible_id} (supprimé)";
        }

        $nom = $compte->username ?? $compte->email ?? "compte #{$compte->id}";

        return "Compte #{$compte->id} : {$nom}";
    }

    private function resumeEvenement(): string
    {
        $evenement = Evenement::query()->find($this->cible_id);

        return $evenement === null
            ? "Événement #{$this->cible_id} (supprimé)"
            : "Événement #{$evenement->id} : ".Str::limit((string) $evenement->titre, 80);
    }

    /**
     * @return array<string, mixed>
     */
    public function versApi(): array
    {
        $this->loadMissing(['signalePar', 'traitePar']);

        return [
            'id' => $this->id,
            'cible' => $this->cible,
            'cible_id' => $this->cible_id,
            'cible_resume' => $this->cibleResume(),
            'cible_chemin' => $this->cibleChemin(),
            'motif' => $this->motif,
            'details' => $this->details,
            'statut' => $this->statut,
            'signale_par' => DevResource::champs($this->signalePar),
            'cree_le' => $this->created_at->toIso8601String(),
            'traite_par' => $this->traitePar === null ? null : DevResource::champs($this->traitePar),
            'traite_le' => $this->traite_le?->toIso8601String(),
            'note' => $this->note,
        ];
    }
}
