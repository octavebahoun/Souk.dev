<?php

namespace App\Ia;

/**
 * Fournisseur des analyses Souk AI : l'API officielle DeepSeek aujourd'hui, un autre demain sans
 * toucher au reste. La clé reste sur le serveur ; ni elle ni le contenu envoyé ne sont journalisés.
 */
interface FournisseurIa
{
    /**
     * @throws IaIndisponible panne, délai dépassé, limite de requêtes : un nouvel essai plus tard
     * @throws IaRefus clé refusée, solde épuisé, demande invalide, réponse inexploitable : pas de nouvel essai
     */
    public function analyser(string $consigne, string $demande): ReponseIa;
}
