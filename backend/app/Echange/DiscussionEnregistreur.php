<?php

declare(strict_types=1);

namespace App\Echange;

use App\Models\Discussion;
use App\Models\User;

final class DiscussionEnregistreur
{
    /**
     * @param  array<string, mixed>  $donnees
     */
    public function creer(User $auteur, array $donnees): Discussion
    {
        $discussion = new Discussion;
        $discussion->user_id = $auteur->id;
        $discussion->titre = $donnees['titre'];
        $discussion->texte = $donnees['texte'];
        $discussion->depot_url = $donnees['depot_url'] ?? null;
        $discussion->demo_url = $donnees['demo_url'] ?? null;
        $discussion->etiquettes = array_values($donnees['etiquettes'] ?? []);
        $discussion->resolue = false;
        $discussion->canal_id = $donnees['canal_id'] ?? null;
        $discussion->app_id = $donnees['app_id'] ?? null;
        $discussion->bug = $this->bug($discussion->etiquettes, $donnees['bug'] ?? null);
        $discussion->save();

        return $discussion;
    }

    /**
     * @param  array<string, mixed>  $donnees
     */
    public function modifier(Discussion $discussion, array $donnees): Discussion
    {
        foreach (['titre', 'texte', 'depot_url', 'demo_url'] as $champ) {
            if (array_key_exists($champ, $donnees)) {
                $discussion->{$champ} = $donnees[$champ];
            }
        }

        if (array_key_exists('etiquettes', $donnees)) {
            $discussion->etiquettes = array_values($donnees['etiquettes']);
        }

        if (array_key_exists('resolue', $donnees)) {
            $discussion->resolue = (bool) $donnees['resolue'];
        }

        $etiquettes = $discussion->etiquettes ?? [];

        if (! in_array('bug', $etiquettes, true)) {
            $discussion->bug = null;
        } elseif (array_key_exists('bug', $donnees)) {
            $discussion->bug = $this->bug($etiquettes, $donnees['bug']);
        }

        $discussion->save();

        return $discussion;
    }

    /**
     * @param  list<string>  $etiquettes
     * @param  array<string, mixed>|null  $bug
     * @return array<string, mixed>|null
     */
    private function bug(array $etiquettes, ?array $bug): ?array
    {
        if (! in_array('bug', $etiquettes, true) || $bug === null) {
            return null;
        }

        return [
            'erreur_obtenue' => $bug['erreur_obtenue'],
            'comportement_attendu' => $bug['comportement_attendu'],
            'techno' => $bug['techno'] ?? null,
            'code' => $bug['code'] ?? null,
            'difficulte' => $bug['difficulte'] ?? null,
        ];
    }
}
