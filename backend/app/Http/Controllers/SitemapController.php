<?php

namespace App\Http\Controllers;

use App\Models\Appli;
use App\Models\Discussion;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Plan du site pour les moteurs de recherche : les pages publiques du front, les fiches du Store,
 * les profils de devs et les discussions de l'Échange. Servi par l'API (elle seule connaît ces
 * listes) et annoncé par la ligne « Sitemap » du robots.txt du front.
 */
final class SitemapController extends Controller
{
    private const PAGES = ['/', '/store', '/echange', '/devs', '/evenements', '/legal/cgu', '/legal/cgv', '/legal/confidentialite', '/legal/abus', '/legal/mentions'];

    /** Au-delà, un plan du site doit être découpé : les plus récents suffisent largement d'ici là. */
    private const MAXIMUM_PAR_TYPE = 5000;

    public function __invoke(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHour(), fn (): string => $this->construire());

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function construire(): string
    {
        $base = rtrim((string) config('app.frontend_url'), '/');
        $lignes = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];

        foreach (self::PAGES as $page) {
            $lignes[] = $this->adresse($base.$page);
        }

        Appli::query()->latest('updated_at')->limit(self::MAXIMUM_PAR_TYPE)->get(['id', 'updated_at'])
            ->each(function (Appli $appli) use (&$lignes, $base): void {
                $lignes[] = $this->adresse("{$base}/store/{$appli->id}", $appli->updated_at?->toDateString());
            });

        User::query()->whereNotNull('username')->whereNull('suspendu_le')->latest('updated_at')->limit(self::MAXIMUM_PAR_TYPE)->get(['username', 'updated_at'])
            ->each(function (User $dev) use (&$lignes, $base): void {
                $lignes[] = $this->adresse("{$base}/devs/".rawurlencode((string) $dev->username), $dev->updated_at?->toDateString());
            });

        Discussion::query()->latest('updated_at')->limit(self::MAXIMUM_PAR_TYPE)->get(['id', 'updated_at'])
            ->each(function (Discussion $discussion) use (&$lignes, $base): void {
                $lignes[] = $this->adresse("{$base}/echange/{$discussion->id}", $discussion->updated_at?->toDateString());
            });

        $lignes[] = '</urlset>';

        return implode("\n", $lignes)."\n";
    }

    private function adresse(string $url, ?string $modifieLe = null): string
    {
        $loc = htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return $modifieLe === null
            ? "  <url><loc>{$loc}</loc></url>"
            : "  <url><loc>{$loc}</loc><lastmod>{$modifieLe}</lastmod></url>";
    }
}
