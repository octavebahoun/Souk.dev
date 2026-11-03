<?php

namespace App\Moteur;

/**
 * Une machine du moteur : un démon Docker qui héberge des copies (VPS local aujourd'hui,
 * d'autres démons demain, le serverless Datacloud quand son API sera ouverte). Le moteur
 * déploie sur « une machine » sans savoir laquelle : chaque commande docker/compose passe
 * par son environnement, chaque copie y est placée puis arrêtée.
 */
interface MachineCible
{
    /**
     * Variables ajoutées à chaque commande docker ou docker compose de la copie.
     *
     * @return array<string, string>
     */
    public function environnement(): array;

    /** Sonde de santé : le démon répond-il, avec quelle mémoire ? */
    public function sonder(): SanteMachine;

    /**
     * Processeur du conteneur le plus chargé de chaque copie, en cœurs (le plafond de la formule
     * vaut par conteneur), indexé par nom de copie (le projet docker compose). null si le démon
     * ne répond pas.
     *
     * @return array<string, float>|null
     */
    public function processeurParCopie(): ?array;
}
