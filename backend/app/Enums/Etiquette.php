<?php

declare(strict_types=1);

namespace App\Enums;

enum Etiquette: string
{
    case Bug = 'bug';
    case Question = 'question';
    case Tuto = 'tuto';
    case Projet = 'projet';
    case Entraide = 'entraide';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
