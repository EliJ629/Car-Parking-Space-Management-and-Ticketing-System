<?php

namespace App\Support;

use App\Models\SystemSetting;

/**
 * What the Manager can change on the public driver page: the wording of the top banner, which sections show and in
 * which order, and how the "Find my car" ticket lookup looks. Stored as one setting (landing_page). Whatever is
 * stored is checked again when it is read, so a damaged value falls back to the default instead of breaking the page.
 * The lookup block is one component, so the driver sign in page and the landing page always show the same layout.
 */
class LandingConfig
{
    public const KEY = 'landing_page';

    /** Section key => label shown to the Manager. */
    public const SECTIONS = [
        'reserve' => 'How reserving works',
        'find' => 'Find my car (ticket lookup)',
        'grace' => 'Reservation grace period',
        'fees' => 'Fees',
    ];

    /** stacked: label above the field, button below. inline: field and button on one row. compact: no intro text, one row. */
    public const LOOKUP_LAYOUTS = [
        'stacked' => 'Stacked: heading, text, field, then a full-width button',
        'inline' => 'Inline: field and button on one row, with the text above',
        'compact' => 'Compact: heading and one row only, no text',
    ];

    public const LIMITS = [
        'hero_eyebrow' => 40,
        'hero_title' => 90,
        'hero_lede' => 300,
        'lookup_heading' => 40,
        'lookup_intro' => 200,
    ];

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'hero_eyebrow' => 'For drivers',
            'hero_title' => 'Your bay is *waiting*.',
            'hero_lede' => 'Reserve a bay for free, follow your parking from your phone and get your receipts in one place. Parked as a walk-in? Type the ticket number and see where the car is.',
            'sections' => [
                ['key' => 'reserve', 'enabled' => true],
                ['key' => 'find', 'enabled' => true],
                ['key' => 'grace', 'enabled' => true],
                ['key' => 'fees', 'enabled' => true],
            ],
            'lookup' => [
                'on_login' => true,
                'layout' => 'stacked',
                'heading' => 'Find my car',
                'intro' => 'Parked as a walk-in? Type the ticket number from your printed ticket. No account needed.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function get(): array
    {
        $stored = SystemSetting::value(self::KEY);

        return self::clean(is_array($stored) ? $stored : []);
    }

    /** The lookup block only. */
    public static function lookup(): array
    {
        return self::get()['lookup'];
    }

    /** Section keys in order, enabled ones only. @return array<int, string> */
    public static function visibleSections(): array
    {
        return collect(self::get()['sections'])->where('enabled', true)->pluck('key')->values()->all();
    }

    /** Text with *word* shown emphasised. Everything is escaped first, so nothing typed can become markup. */
    public static function emphasise(string $text): string
    {
        return preg_replace('/\*([^*]+)\*/u', '<em>$1</em>', e($text), 1) ?? e($text);
    }

    /**
     * Merge stored values over the defaults and force every field into a safe shape.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function clean(array $input): array
    {
        $d = self::defaults();
        $text = function (mixed $value, string $key, string $default) : string {
            $value = is_string($value) ? trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '') : '';

            return $value === '' ? $default : mb_substr($value, 0, self::LIMITS[$key]);
        };

        $sections = [];
        foreach ((array) ($input['sections'] ?? []) as $row) {
            $key = is_array($row) ? ($row['key'] ?? null) : null;

            if (is_string($key) && isset(self::SECTIONS[$key]) && ! isset($sections[$key])) {
                $sections[$key] = ['key' => $key, 'enabled' => (bool) ($row['enabled'] ?? true)];
            }
        }
        foreach ($d['sections'] as $row) {
            $sections[$row['key']] ??= $row;
        }

        $lookup = (array) ($input['lookup'] ?? []);
        $layout = $lookup['layout'] ?? $d['lookup']['layout'];

        return [
            'hero_eyebrow' => $text($input['hero_eyebrow'] ?? null, 'hero_eyebrow', $d['hero_eyebrow']),
            'hero_title' => $text($input['hero_title'] ?? null, 'hero_title', $d['hero_title']),
            'hero_lede' => $text($input['hero_lede'] ?? null, 'hero_lede', $d['hero_lede']),
            'sections' => array_values($sections),
            'lookup' => [
                'on_login' => (bool) ($lookup['on_login'] ?? $d['lookup']['on_login']),
                'layout' => is_string($layout) && isset(self::LOOKUP_LAYOUTS[$layout]) ? $layout : $d['lookup']['layout'],
                'heading' => $text($lookup['heading'] ?? null, 'lookup_heading', $d['lookup']['heading']),
                'intro' => $text($lookup['intro'] ?? null, 'lookup_intro', $d['lookup']['intro']),
            ],
        ];
    }

    /** What the public page needs from the live settings, so the text never repeats a number that has changed. @return array<string, mixed> */
    public static function liveFacts(): array
    {
        $rate = \App\Models\ParkingRateSet::current();
        $discounts = \App\Models\ParkingDiscount::query()->where('active', true)->get();
        $seniorPwd = $discounts->filter(fn ($d) => preg_match('/senior|pwd/i', $d->name))->max('percent');

        return [
            'advance_days' => max(1, (int) Lot::setting('maximum_advance_booking_days', 7)),
            'grace' => Lot::graceMinutes(),
            'first' => (float) ($rate?->first_hour_fee ?? 30),
            'extra' => (float) ($rate?->additional_hour_fee ?? 20),
            'cap' => (float) ($rate?->daily_maximum ?? 300),
            'lost' => (float) ($rate?->lost_ticket_penalty ?? 100),
            'over' => (float) ($rate?->overstay_penalty ?? 150),
            'discount' => $seniorPwd !== null ? (float) $seniorPwd : null,
        ];
    }
}
