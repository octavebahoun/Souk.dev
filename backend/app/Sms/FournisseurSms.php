<?php

namespace App\Sms;

/**
 * Envoi de SMS (codes de connexion des utilisateurs d'une appli au backend intégré). Le fournisseur
 * réel se branche ici dès qu'il est choisi (SMS_PROVIDER, SMS_API_KEY) ; sans clé, le simulateur.
 */
interface FournisseurSms
{
    /** @throws SmsNonEnvoye */
    public function envoyer(string $numero, string $message): void;
}
