<?php

namespace App\Support;

/**
 * Écrit une moyenne en lettres : 10.99 devient « dix virgule quatre-vingt-dix-neuf ».
 */
class NombreEnLettres
{
    private const UNITES = [
        'zéro',
        'un',
        'deux',
        'trois',
        'quatre',
        'cinq',
        'six',
        'sept',
        'huit',
        'neuf',
        'dix',
        'onze',
        'douze',
        'treize',
        'quatorze',
        'quinze',
        'seize',
        'dix-sept',
        'dix-huit',
        'dix-neuf',
    ];

    private const DIZAINES = [2 => 'vingt', 3 => 'trente', 4 => 'quarante', 5 => 'cinquante', 6 => 'soixante'];

    public static function moyenne(?float $valeur): string
    {
        if ($valeur === null) {
            return '';
        }

        $centiemes = (int) round($valeur * 100);
        $entier = intdiv($centiemes, 100);
        $decimales = $centiemes % 100;

        $texte = self::entier($entier);

        if ($decimales > 0) {
            $texte .= ' virgule ' . ($decimales < 10 ? 'zéro ' : '') . self::entier($decimales);
        }

        return $texte;
    }

    // De 0 à 99
    private static function entier(int $n): string
    {
        if ($n < 20) {
            return self::UNITES[$n];
        }

        if ($n < 70) {
            $dizaine = intdiv($n, 10);
            $unite = $n % 10;

            return self::DIZAINES[$dizaine] . match (true) {
                $unite === 0 => '',
                $unite === 1 => ' et un',
                default => '-' . self::UNITES[$unite],
            };
        }

        if ($n < 80) { // 70 à 79
            return $n === 71 ? 'soixante et onze' : 'soixante-' . self::UNITES[$n - 60];
        }

        // 80 à 99
        return $n === 80 ? 'quatre-vingts' : 'quatre-vingt-' . self::UNITES[$n - 80];
    }
}
