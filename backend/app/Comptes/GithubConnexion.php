<?php

declare(strict_types=1);

namespace App\Comptes;

use App\Enums\Profil;
use App\Models\User;
use Laravel\Socialite\Two\User as UtilisateurGithub;

final class GithubConnexion
{
    /**
     * Ouvre ou lie un compte. Ne demande jamais la permission « repo ».
     */
    public function relier(?User $actuel, UtilisateurGithub $github, Profil $profilEntree): ResultatConnexion
    {
        $githubId = (string) $github->getId();
        $email = $this->email($github->getEmail());
        $parGithub = User::query()->where('github_id', $githubId)->first();

        if ($actuel !== null) {
            if ($parGithub !== null && $parGithub->isNot($actuel)) {
                return ResultatConnexion::erreur('github_deja_lie');
            }

            $compte = $actuel;
        } elseif ($parGithub !== null) {
            $compte = $parGithub;
        } else {
            $compte = $this->compteParEmail($email, $githubId, $profilEntree);

            if (! $compte instanceof User) {
                return $compte;
            }
        }

        $username = $github->getNickname();

        if (is_string($username) && $username !== '') {
            $pris = User::query()
                ->where('username', $username)
                ->when($compte->exists, fn ($query) => $query->whereKeyNot($compte->id))
                ->exists();

            if ($pris) {
                return ResultatConnexion::erreur('username_pris');
            }

            $compte->username = $username;
        }

        $compte->github_id = $githubId;
        $compte->github_lie = true;

        $nom = $github->getName();

        if ($compte->name === null && is_string($nom) && $nom !== '') {
            $compte->name = $nom;
        }

        if ($compte->email === null && $email !== null) {
            $compte->email = $email;
        }

        $avatar = $github->getAvatar();

        if (is_string($avatar) && $avatar !== '') {
            $compte->avatar_url = $avatar;
        }

        if ($compte->email !== null && $compte->email_verified_at === null) {
            $compte->email_verified_at = now();
        }

        $compte->save();

        return ResultatConnexion::ok($compte);
    }

    private function email(mixed $email): ?string
    {
        if (! is_string($email) || $email === '') {
            return null;
        }

        return mb_strtolower($email);
    }

    private function compteParEmail(?string $email, string $githubId, Profil $profilEntree): User|ResultatConnexion
    {
        if ($email !== null) {
            $parEmail = User::query()->where('email', $email)->first();

            if ($parEmail !== null) {
                if ($parEmail->github_id !== null && $parEmail->github_id !== $githubId) {
                    return ResultatConnexion::erreur('github_deja_lie');
                }

                return $parEmail;
            }
        }

        $compte = new User;
        $compte->profil = $profilEntree;
        $compte->email = $email;
        $compte->disponible = false;
        $compte->github_lie = false;

        return $compte;
    }
}
