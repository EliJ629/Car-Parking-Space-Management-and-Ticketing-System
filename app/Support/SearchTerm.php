<?php

namespace App\Support;

final class SearchTerm
{
    public static function normalize(?string $value, int $maxLength = 20): string
    {
        $value = preg_replace('/[^A-Za-z0-9 -]+/', '', trim((string) $value)) ?? '';
        $value = preg_replace('/ {2,}/u', ' ', $value) ?? $value;

        return mb_substr($value, 0, $maxLength);
    }
}
