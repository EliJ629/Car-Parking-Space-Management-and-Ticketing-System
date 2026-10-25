<?php

namespace App\Support;

/**
 * The size limit for the PWD ID photo. The app allows 5 MB, but PHP can be set lower
 * (upload_max_filesize is only 2 MB in a fresh php.ini), so the limit shown to the driver
 * is the smaller of the two. That way the message always names the real limit.
 */
class PwdPhoto
{
    public const LIMIT_BYTES = 5 * 1024 * 1024;

    public static function limitBytes(): int
    {
        $limits = [self::LIMIT_BYTES];

        foreach (['upload_max_filesize', 'post_max_size'] as $key) {
            $bytes = self::iniBytes((string) ini_get($key));

            if ($bytes > 0) {
                // Leave room in the request for the other form fields.
                $limits[] = $key === 'post_max_size' ? max(0, $bytes - 100 * 1024) : $bytes;
            }
        }

        return max(1, min($limits));
    }

    /** The limit in kilobytes, for the Laravel "max" rule. */
    public static function limitKb(): int
    {
        return max(1, intdiv(self::limitBytes(), 1024));
    }

    /** "5 MB", "2 MB" or "1.5 MB". */
    public static function limitLabel(): string
    {
        $mb = self::limitBytes() / 1048576;

        return rtrim(rtrim(number_format($mb, 1, '.', ''), '0'), '.').' MB';
    }

    /** The explanation shown in the reservation pop-up when the photo is too large. */
    public static function tooLargeMessage(): string
    {
        return 'Your PWD ID photo was not accepted because the file is too large. The limit is '.self::limitLabel()
            .'. Choose a smaller photo, or take it again at a lower quality, then send it again.';
    }

    /** Turns "8M", "2G" or "512K" into bytes. */
    public static function iniBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return 0;
        }

        $n = (float) $value;

        return (int) match (strtolower(substr($value, -1))) {
            'g' => $n * 1024 ** 3,
            'm' => $n * 1024 ** 2,
            'k' => $n * 1024,
            default => $n,
        };
    }
}
