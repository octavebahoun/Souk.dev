<?php

declare(strict_types=1);

namespace App\Comptes;

use App\Enums\Profil;
use App\Mail\LienMagiqueMail;
use App\Models\LienMagique;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final class EmetteurLienMagique
{
    public const int DUREE_MINUTES = 15;

    public function demander(string $email): void
    {
        $email = self::normaliser($email);
        $token = Str::random(64);

        LienMagique::query()->create([
            'email' => $email,
            'token_hash' => hash('sha256', $token),
            'expire_le' => now()->addMinutes(self::DUREE_MINUTES),
        ]);

        $url = rtrim((string) config('app.url'), '/').'/auth/lien-magique/'.$token;

        // Les adresses du jeu de démo n'existent pas : aucun e-mail ne part vers elles.
        if (str_ends_with($email, '@demo.souk.dev')) {
            return;
        }

        Mail::to($email)->send(new LienMagiqueMail($url));
    }

    public function utiliser(string $token): ?User
    {
        $hash = hash('sha256', $token);

        // Mise à jour conditionnelle : deux ouvertures simultanées du même lien, une seule réussit.
        $utilise = LienMagique::query()
            ->where('token_hash', $hash)
            ->whereNull('utilise_le')
            ->where('expire_le', '>', now())
            ->update(['utilise_le' => now()]);

        if ($utilise === 0) {
            return null;
        }

        $email = LienMagique::query()->where('token_hash', $hash)->value('email');
        $compte = User::query()->where('email', $email)->first();

        // Le compte naît ici, quand l'adresse est prouvée, et pas à la demande du lien.
        if ($compte === null) {
            $compte = new User;
            $compte->email = $email;
            $compte->profil = Profil::Client;
            $compte->github_lie = false;
            $compte->disponible = false;
        }

        if ($compte->email_verified_at === null) {
            $compte->email_verified_at = now();
        }

        $compte->save();

        return $compte;
    }

    public static function normaliser(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
