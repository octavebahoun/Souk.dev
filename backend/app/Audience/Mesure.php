<?php

declare(strict_types=1);

namespace App\Audience;

use App\Models\User;
use App\Models\Visite;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Enregistre une page vue sans donnée personnelle : pas de cookie, pas d'adresse IP gardée. Le
 * visiteur est une empreinte de son adresse et de son navigateur, salée par la date : elle change
 * chaque jour, on compte des visiteurs par jour sans pouvoir suivre personne d'un jour à l'autre.
 * Les robots et les comptes de l'équipe ne sont pas comptés.
 */
final class Mesure
{
    private const ROBOTS = '/bot|crawl|spider|slurp|headless|lighthouse|preview|facebookexternalhit|whatsapp|telegram|curl|wget|python|axios|go-http|java\/|monitor|uptime/i';

    /**
     * @param  array<string, mixed>  $donnees
     */
    public function enregistrer(Request $request, array $donnees, ?User $compte): bool
    {
        $navigateur = (string) $request->userAgent();

        if ($navigateur === '' || preg_match(self::ROBOTS, $navigateur) === 1 || $compte?->est_admin === true) {
            return false;
        }

        $chemin = $this->chemin($donnees['chemin'] ?? null);

        if ($chemin === null || str_starts_with($chemin, '/admin')) {
            return false;
        }

        Visite::query()->create([
            'chemin' => $chemin,
            'referent' => $this->referent($donnees['referent'] ?? null),
            'source' => is_string($donnees['source'] ?? null) ? Str::limit(Str::lower(trim($donnees['source'])), 60, '') ?: null : null,
            'pays' => PaysEstime::depuis($donnees['fuseau'] ?? null, $donnees['langue'] ?? null),
            'appareil' => $this->appareil($donnees['largeur'] ?? null),
            'visiteur' => hash('sha256', now()->toDateString().'|'.config('app.key').'|'.$request->ip().'|'.$navigateur),
        ]);

        return true;
    }

    /** Le chemin seul, sans paramètres ni fragment (ils peuvent porter un jeton ou une adresse). */
    private function chemin(mixed $chemin): ?string
    {
        if (! is_string($chemin) || ! str_starts_with($chemin, '/') || str_starts_with($chemin, '//')) {
            return null;
        }

        $chemin = (string) parse_url($chemin, PHP_URL_PATH);

        return Str::limit($chemin, 255, '') ?: null;
    }

    /** Le site d'où vient le visiteur, sans « www. » ; le site lui-même ne compte pas comme source. */
    private function referent(mixed $adresse): ?string
    {
        if (! is_string($adresse) || $adresse === '') {
            return null;
        }

        $hote = parse_url($adresse, PHP_URL_HOST);

        if (! is_string($hote) || $hote === '') {
            return null;
        }

        $hote = Str::lower(preg_replace('/^www\./', '', $hote) ?? $hote);
        $site = parse_url((string) config('app.frontend_url'), PHP_URL_HOST);

        return $hote === $site ? null : Str::limit($hote, 120, '');
    }

    private function appareil(mixed $largeur): string
    {
        $largeur = is_numeric($largeur) ? (int) $largeur : 0;

        return match (true) {
            $largeur <= 0 => 'inconnu',
            $largeur < 768 => 'mobile',
            $largeur < 1200 => 'tablette',
            default => 'ordinateur',
        };
    }
}
