<?php

namespace App\Sms;

use RuntimeException;

/** Le fournisseur n'a pas pris le SMS en charge. */
final class SmsNonEnvoye extends RuntimeException {}
