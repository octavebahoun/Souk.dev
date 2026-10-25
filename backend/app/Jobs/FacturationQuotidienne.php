<?php

namespace App\Jobs;

use App\Paiement\CycleFacturation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/** Chaque matin : factures, rappels, relances, suspensions et arrêts (voir CycleFacturation). */
class FacturationQuotidienne implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('paiements');
    }

    public function handle(CycleFacturation $cycle): void
    {
        Log::info('facturation: passage quotidien', $cycle->executer());
    }
}
