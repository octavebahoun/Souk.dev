<?php

declare(strict_types=1);

namespace App\Enums;

enum Specialite: string
{
    case Frontend = 'frontend';
    case Backend = 'backend';
    case Mobile = 'mobile';
    case Devops = 'devops';
    case Securite = 'securite';
    case Data = 'data';
}
