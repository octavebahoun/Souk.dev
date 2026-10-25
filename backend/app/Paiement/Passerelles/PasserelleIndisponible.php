<?php

namespace App\Paiement\Passerelles;

use RuntimeException;

/** Réseau coupé, délai dépassé, 429 ou 5xx : l'opération peut être retentée plus tard. */
final class PasserelleIndisponible extends RuntimeException {}
