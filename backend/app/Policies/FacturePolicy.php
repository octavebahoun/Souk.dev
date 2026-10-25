<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Facture;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Une facture est privée : seul son client la voit et la paie. Pour un autre compte, elle
 * n'existe pas (404), pour ne pas révéler qu'un numéro de facture existe.
 */
final class FacturePolicy
{
    public function view(User $compte, Facture $facture): Response
    {
        return $facture->client_id === $compte->id ? Response::allow() : Response::denyAsNotFound();
    }

    public function payer(User $compte, Facture $facture): Response
    {
        return $this->view($compte, $facture);
    }
}
