<?php

namespace App\Services;

class PhoneNumberService
{
    public static function normalize(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number) ?? '';
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        } elseif (! str_starts_with($digits, '62')) {
            $digits = '62' . $digits;
        }
        if (! preg_match('/^62[0-9]{8,13}$/', $digits)) {
            throw new \InvalidArgumentException('Nomor WhatsApp tidak valid.');
        }
        return $digits;
    }
}
