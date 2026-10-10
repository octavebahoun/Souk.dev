<?php

namespace App\Jobs;

use App\Models\Appli;
use App\Moteur\MesureMemoire;
use App\Moteur\RecommandationTaille;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class MesurerMemoire implements ShouldQueue
{
    use Queueable;

    public int $tries = 60;

    public int $maxExceptions = 1;

    public int $timeout = 1200;

    public bool $failOnTimeout = true;

    public function __construct(
        public int $appliId,
        public int $jeton,
        public string $copie = '',
    ) {
        if ($this->copie === '') {
            $this->copie = 'm'.$appliId.'-'.bin2hex(random_bytes(4));
        }
    }

    /**
     * Une seule copie de mesure à la fois pour cette appli.
     * Un second appel attend, puis n'écrit que s'il a encore le jeton en cours.
     *
     * @return list<WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('appli-'.$this->appliId))
                ->releaseAfter(30)
                ->expireAfter($this->timeout),
        ];
    }

    public function handle(MesureMemoire $mesure, RecommandationTaille $recommandation): void
    {
        try {
            $appli = $this->appliEnCours();

            if ($appli === null) {
                return;
            }

            $pic = $mesure->pic((string) $appli->depot_url, $this->copie);
            $appli = $this->appliEnCours();

            if ($appli === null) {
                return;
            }

            $appli->terminerMesure($pic, $recommandation->depuisPic($pic));
        } catch (Throwable $exception) {
            report($exception);
            $this->marquerEchec();
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->marquerEchec();
    }

    private function appliEnCours(): ?Appli
    {
        $appli = Appli::query()->find($this->appliId);

        if ($appli === null || $appli->mesure_statut !== 'en_cours' || $appli->mesure_jeton !== $this->jeton) {
            return null;
        }

        return $appli;
    }

    private function marquerEchec(): void
    {
        $this->appliEnCours()?->echouerMesure();
    }
}
