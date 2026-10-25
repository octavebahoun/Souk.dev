<?php

namespace App\Paiement\Passerelles;

/**
 * Encaissement réel par le checkout hébergé de SasPay. Frais en ADD_ON : le client les paie,
 * Souk.dev reçoit le montant plein. Aucun champ hors de la spécification OpenAPI de SasPay.
 */
final class SasPayPaiement implements PasserellePaiement
{
    public function __construct(private ClientSasPay $client) {}

    public function nom(): string
    {
        return 'saspay';
    }

    public function creerSession(DemandeSession $demande): SessionPaiement
    {
        $corps = [
            'amount' => Montants::versDecimal($demande->montant),
            'currency' => 'XOF',
            'description' => $demande->description,
            'customer_email' => $demande->email,
            'customer_name' => $demande->nom,
            'return_url' => $demande->urlRetour,
            'metadata' => $demande->metadata,
            'fee_charge_mode' => 'ADD_ON',
        ];

        if ($demande->pays !== null) {
            $corps['country'] = $demande->pays;
        }

        $session = $this->client->post('/checkout-sessions/', $corps);

        if (! is_string($session['id'] ?? null) || ! is_string($session['checkout_url'] ?? null)) {
            throw new PasserelleIndisponible('Réponse de SasPay incomplète (session sans identifiant).');
        }

        return new SessionPaiement($session['id'], (string) ($session['slug'] ?? ''), $session['checkout_url']);
    }

    public function statutSession(string $sessionId): StatutSession
    {
        $statut = $this->client->get('/checkout-sessions/'.ClientSasPay::identifiant($sessionId).'/status/');

        return new StatutSession(
            strtoupper((string) ($statut['status'] ?? 'PENDING')),
            is_string($statut['transaction_id'] ?? null) ? $statut['transaction_id'] : null,
            is_string($statut['transaction_status'] ?? null) ? strtoupper($statut['transaction_status']) : null,
        );
    }

    public function verifierPaiement(string $transactionId): VerificationPaiement
    {
        $transaction = $this->client->get('/payments/'.ClientSasPay::identifiant($transactionId).'/verify/');

        return new VerificationPaiement(
            strtoupper((string) ($transaction['status'] ?? 'PENDING')),
            Montants::versEntier($transaction['requested_amount'] ?? 0) ?? 0,
            Montants::versEntier($transaction['client_fee'] ?? null),
            Montants::versEntier($transaction['debited_amount'] ?? null),
            Montants::versEntier($transaction['net_amount'] ?? null),
            is_string($transaction['reference'] ?? null) ? $transaction['reference'] : null,
            is_string($transaction['currency'] ?? null) ? $transaction['currency'] : null,
        );
    }

    public function transactionsReussies(\DateTimeInterface $debut, \DateTimeInterface $fin): iterable
    {
        $curseur = null;

        // Pagination par curseur : on suit le paramètre cursor de « next », jamais l'URL elle-même.
        for ($page = 0; $page < 100; $page++) {
            $parametres = [
                'status' => 'SUCCESS',
                'flow_direction' => 'INBOUND',
                'created_at__gte' => $debut->format(DATE_ATOM),
                'created_at__lte' => $fin->format(DATE_ATOM),
                'page_size' => 200,
            ];

            if ($curseur !== null) {
                $parametres['cursor'] = $curseur;
            }

            $liste = $this->client->get('/transactions/', $parametres);

            foreach ((array) ($liste['results'] ?? []) as $transaction) {
                if (! is_array($transaction) || ! is_string($transaction['id'] ?? null)) {
                    continue;
                }

                yield new TransactionDistante(
                    $transaction['id'],
                    is_string($transaction['reference'] ?? null) ? $transaction['reference'] : null,
                    Montants::versEntier($transaction['requested_amount'] ?? 0) ?? 0,
                    Montants::versEntier($transaction['net_amount'] ?? null),
                    (string) ($transaction['created_at'] ?? ''),
                    is_string($transaction['description'] ?? null) ? $transaction['description'] : null,
                );
            }

            $suivante = $liste['next'] ?? null;
            parse_str((string) parse_url(is_string($suivante) ? $suivante : '', PHP_URL_QUERY), $requete);
            $curseur = is_string($requete['cursor'] ?? null) ? $requete['cursor'] : null;

            if ($curseur === null) {
                return;
            }
        }
    }
}
