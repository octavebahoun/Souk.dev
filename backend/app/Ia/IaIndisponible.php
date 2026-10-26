<?php

namespace App\Ia;

use RuntimeException;

/** Le fournisseur ne répond pas, est surchargé ou limite les requêtes : réessayer plus tard. */
final class IaIndisponible extends RuntimeException {}
