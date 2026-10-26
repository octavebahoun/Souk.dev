<?php

namespace App\Notifications;

/**
 * Types de notification : chacun se règle séparément, dans Souk.dev et par e-mail. Les e-mails
 * de suspension ne se coupent pas : ils préviennent avant la suppression des données.
 */
final class TypesNotification
{
    /** @var array<string, array{libelle: string, description: string, email_obligatoire: bool}> */
    public const TYPES = [
        'correctif_propose' => [
            'libelle' => 'Correctif proposé',
            'description' => 'Un dev propose un correctif dans une de vos discussions.',
            'email_obligatoire' => false,
        ],
        'correctif_accepte' => [
            'libelle' => 'Correctif accepté',
            'description' => 'L’auteur d’une discussion accepte votre correctif.',
            'email_obligatoire' => false,
        ],
        'message_mission' => [
            'libelle' => 'Missions',
            'description' => 'Une nouvelle demande de mission, ou un message dans le fil privé d’une de vos missions (un e-mail par fil toutes les 30 minutes au plus).',
            'email_obligatoire' => false,
        ],
        'echeance' => [
            'libelle' => 'Factures et échéances',
            'description' => 'Facture à régler avant l’échéance, retard de paiement, paiement reçu.',
            'email_obligatoire' => false,
        ],
        'suspension' => [
            'libelle' => 'Suspension d’une copie',
            'description' => 'Copie suspendue faute de paiement, dernier avertissement, arrêt, copie qui ne démarre pas.',
            'email_obligatoire' => true,
        ],
        'mise_a_jour' => [
            'libelle' => 'Mises à jour de vos copies',
            'description' => 'Une nouvelle version d’une appli que vous utilisez, puis le résultat de sa mise à jour.',
            'email_obligatoire' => false,
        ],
        'reversement' => [
            'libelle' => 'Reversements',
            'description' => 'Argent envoyé sur votre Mobile Money, ou refusé par le réseau.',
            'email_obligatoire' => false,
        ],
    ];

    public const CANAUX = ['application', 'email'];
}
