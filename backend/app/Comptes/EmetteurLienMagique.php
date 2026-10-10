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
        $email = mb_strtolower(trim($email));
        $compte = User::query()->where('email', $email)->first();

        if ($compte === null) {
            $compte = new User;
            $compte->email = $email;
            $compte->profil = Profil::Client;
            $compte->github_lie = false;
            $compte->disponible = false;
            $compte->save();
        }

        $token = Str::random(64);

        $compte->liensMagiques()->create([
            'token_hash' => hash('sha256', $token),
            'expire_le' => now()->addMinutes(self::DUREE_MINUTES),
        ]);

        $url = rtrim((string) config('app.url'), '/').'/auth/lien-magique/'.$token;

        Mail::to($email)->send(new LienMagiqueMail($url));
    }

    public function utiliser(string $token): ?User
    {
        $lien = LienMagique::query()->where('token_hash', hash('sha256', $token))->first();

        if ($lien === null || $lien->utilise_le !== null || $lien->expire_le->isPast()) {
            return null;
        }

        $lien->utilise_le = now();
        $lien->save();

        $compte = $lien->compte;

        if ($compte->email_verified_at === null) {
            $compte->email_verified_at = now();
            $compte->save();
        }

        return $compte;
    }
}
