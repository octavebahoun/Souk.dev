<?php

declare(strict_types=1);

namespace App\Echange;

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Throwable;

final class Diffuseur
{
    public function envoyer(ShouldBroadcastNow $evenement): void
    {
        try {
            broadcast($evenement);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
