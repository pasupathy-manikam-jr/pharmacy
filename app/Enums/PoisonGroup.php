<?php

namespace App\Enums;

/**
 * Poisons Act 1952 classification.
 * ponytail: register/prescription mapping per MIMS summary; confirm with the client's pharmacist before go-live.
 */
enum PoisonGroup: string
{
    case None = 'none';
    case B = 'B';
    case C = 'C';
    case D = 'D';
    case Psychotropic = 'psychotropic';
    case Dda = 'dda';

    public function register(): ?string
    {
        return match ($this) {
            self::None => null,
            self::B, self::C => 'prescription_book',
            self::D => 'poisons_book',
            self::Psychotropic => 'psychotropic',
            self::Dda => 'dda',
        };
    }

    public function requiresPrescription(): bool
    {
        return in_array($this, [self::B, self::Psychotropic, self::Dda], true);
    }
}
