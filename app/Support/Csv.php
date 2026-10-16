<?php

namespace App\Support;

/** Safe values for CSV exports. A cell that starts with = + - or @ can run as a formula in a spreadsheet. */
class Csv
{
    public static function cell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '' || is_numeric($value)) {
            return $value;
        }

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array<int, mixed>
     */
    public static function row(array $row): array
    {
        return array_map(fn ($value) => self::cell($value), $row);
    }
}
