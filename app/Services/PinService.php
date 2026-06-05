<?php

namespace App\Services;

use Illuminate\Support\Facades\Hash;

class PinService
{
    public function generatePin(int $length = 4): string
    {
        $min = 10 ** ($length - 1);
        $max = (10 ** $length) - 1;

        return str_pad((string) random_int($min, $max), $length, '0', STR_PAD_LEFT);
    }

    public function hashPin(string $pin): string
    {
        return Hash::make($pin);
    }

    public function verifyPin(string $pin, string $hash): bool
    {
        if (empty($hash)) {
            return false;
        }

        return Hash::check($pin, $hash);
    }

    public function generateAndHashPin(int $length = 4): array
    {
        $pin = $this->generatePin($length);

        return [
            'pin' => $pin,
            'hash' => $this->hashPin($pin),
        ];
    }
}


