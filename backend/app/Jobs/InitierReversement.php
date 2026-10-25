<?php

namespace App\Jobs;

use App\Models\Reversement;
use App\Paiement\Reversements;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class InitierReversement implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 120, 600, 1800];

    public function __construct(public int $reversementId)
    {
        $this->onQueue('paiements');
    }

    public function handle(Reversements $reversements): void
    {
        $reversement = Reversement::query()->find($this->reversementId);

        if ($reversement !== null) {
            $reversements->initier($reversement);
        }
    }
}
