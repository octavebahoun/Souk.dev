<?php

namespace App\Paiement\Passerelles;

/**
 * Encaissement par session de checkout hébergée (SasPay, ou le simulateur en développement).
 * Montants en entiers FCFA : la conversion en chaîne décimale ("3500.00") reste dans l'adaptateur.
 */
interface PasserellePaiement
{
    public function nom(): string;

    /** @throws PasserelleIndisponible|PasserelleRefus */
    public function creerSession(DemandeSession $demande): SessionPaiement;

    /** @throws PasserelleIndisponible|PasserelleRefus */
    public function statutSession(string $sessionId): StatutSession;

    /** Revérifie l'état réel d'un paiement (jamais un statut mémorisé). */
    public function verifierPaiement(string $transactionId): VerificationPaiement;

    /**
     * Transactions entrantes réussies sur une période, pour le rapprochement quotidien.
     *
     * @return iterable<TransactionDistante>
     */
    public function transactionsReussies(\DateTimeInterface $debut, \DateTimeInterface $fin): iterable;
}
