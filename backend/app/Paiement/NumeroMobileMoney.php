<?php

namespace App\Paiement;

use InvalidArgumentException;

/**
 * Numéros Mobile Money : forme internationale sans « + » (ex. 2290197505050) et version
 * masquée pour l'affichage et les journaux (ex. +229 01 •• •• 50 50). Jamais le numéro entier
 * hors du champ chiffré.
 */
final class NumeroMobileMoney
{
    public static function normaliser(string $pays, string $saisie): string
    {
        $regle = config('facturation.pays.'.$pays);

        if (! is_array($regle)) {
            throw new InvalidArgumentException('Ce pays n’est pas encore pris en charge pour les reversements.');
        }

        $chiffres = preg_replace('/\D/', '', $saisie) ?? '';
        $indicatif = (string) $regle['indicatif'];

        if (str_starts_with($chiffres, '00'.$indicatif)) {
            $chiffres = substr($chiffres, 2);
        }

        $local = str_starts_with($chiffres, $indicatif) && strlen($chiffres) === strlen($indicatif) + (int) $regle['longueur']
            ? substr($chiffres, strlen($indicatif))
            : $chiffres;

        if (strlen($local) !== (int) $regle['longueur']) {
            throw new InvalidArgumentException("Le numéro doit compter {$regle['longueur']} chiffres, sans l’indicatif +{$indicatif}.");
        }

        return $indicatif.$local;
    }

    public static function masquer(string $pays, string $international): string
    {
        $indicatif = (string) config('facturation.pays.'.$pays.'.indicatif', '');
        $local = str_starts_with($international, $indicatif) ? substr($international, strlen($indicatif)) : $international;
        $visible = substr($local, 0, 2).str_repeat('•', max(0, strlen($local) - 6)).substr($local, -4);

        // Par caractères, pas par octets : « • » tient sur trois octets en UTF-8.
        return trim('+'.$indicatif.' '.implode(' ', mb_str_split($visible, 2)));
    }
}
