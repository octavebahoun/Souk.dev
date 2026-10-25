<?php

namespace App\Paiement\Passerelles;

/** SasPay parle en chaînes décimales ("3500.00") ; Souk.dev compte en entiers FCFA. */
final class Montants
{
    public static function versDecimal(int $montant): string
    {
        return $montant.'.00';
    }

    /** Frais avec centimes (ex. « 37.50 ») : arrondis au franc le plus proche. */
    public static function versEntier(mixed $valeur): ?int
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }

        if (! is_numeric($valeur)) {
            return null;
        }

        return (int) round((float) $valeur);
    }
}
