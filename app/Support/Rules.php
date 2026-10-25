<?php

namespace App\Support;

use App\Models\Vehicle;
use Illuminate\Http\Request;

/**
 * The validation rules that more than one screen shares, so every form says the same thing.
 * Values follow section 11 of the system process document and the Driver screen designs.
 */
class Rules
{
    /** Letters, spaces and the marks real names carry. At least one letter first. */
    public const NAME_PATTERN = "/^\\p{L}[\\p{L}\\p{M}\\s.'\\-]*$/u";

    /** Make and model: letters, digits and the marks car names carry. */
    public const MAKE_MODEL_PATTERN = "/^[\\p{L}\\p{N}][\\p{L}\\p{N}\\s.\\-\\/&+']*$/u";

    /** Bcrypt reads 72 bytes. Longer input would be cut off without a word. */
    public const PASSWORD_MAX = 64;

    /** @return array<int, string> */
    public static function personName(): array
    {
        return ['required', 'string', 'min:2', 'max:120', 'regex:'.self::NAME_PATTERN];
    }

    /** @return array<string, string> */
    public static function personNameMessages(string $field = 'name'): array
    {
        return [
            "{$field}.required" => 'Enter your full name.',
            "{$field}.min" => 'Enter your full name.',
            "{$field}.max" => 'The name can have up to 120 characters.',
            "{$field}.regex" => 'Use letters only in the name. Spaces, dots, apostrophes and hyphens are fine.',
        ];
    }

    /** Philippine mobile number after normalizing: 09 and nine more digits. */
    public static function mobile(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'regex:/^09\d{9}$/'];
    }

    public const MOBILE_MESSAGE = 'Enter a mobile number like 0917 555 0142.';

    /**
     * Driver password, from the sign up and reset screens: at least 10 characters,
     * one number and one capital letter.
     *
     * @return array<int, string>
     */
    public static function driverPassword(): array
    {
        return ['required', 'string', 'min:10', 'max:'.self::PASSWORD_MAX, 'regex:/[A-Z]/', 'regex:/[0-9]/', 'confirmed'];
    }

    /** @return array<string, string> */
    public static function driverPasswordMessages(): array
    {
        $rule = 'Use at least 10 characters with one number and one capital letter.';

        return [
            'password.required' => 'Choose a password.',
            'password.min' => $rule,
            'password.regex' => $rule,
            'password.max' => 'The password can have up to '.self::PASSWORD_MAX.' characters.',
            'password.confirmed' => 'Passwords do not match.',
        ];
    }

    /**
     * Staff password: 12 characters or more, with a letter and a number. This is stricter than the
     * driver rule because staff accounts can change rates and records.
     *
     * @return array<int, string>
     */
    public static function staffPassword(): array
    {
        return ['required', 'string', 'min:12', 'max:'.self::PASSWORD_MAX, 'regex:/\\p{L}/u', 'regex:/[0-9]/', 'confirmed'];
    }

    /** @return array<string, string> */
    public static function staffPasswordMessages(): array
    {
        $rule = 'Use at least 12 characters with a letter and a number.';

        return [
            'password.required' => 'Choose a new password.',
            'password.min' => $rule,
            'password.regex' => $rule,
            'password.max' => 'The password can have up to '.self::PASSWORD_MAX.' characters.',
            'password.confirmed' => 'Passwords do not match.',
            'password.different' => 'Choose a password that is different from the current one.',
        ];
    }

    /** Valid Philippine vehicle plate (standard, legacy, vintage, or protocol). */
    public static function plate(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'regex:'.Vehicle::PLATE_PATTERN];
    }

    public const PLATE_MESSAGE = 'Enter a valid Philippine plate (e.g. ABC 1234, AB 12345, or protocol plate 1).';

    public static function makeModel(): array
    {
        return ['required', 'string', 'min:2', 'max:60', 'regex:'.self::MAKE_MODEL_PATTERN];
    }

    /** @return array<string, string> */
    public static function makeModelMessages(): array
    {
        return [
            'make_model.required' => 'Enter the car make and model, for example Toyota Vios.',
            'make_model.min' => 'Enter the car make and model, for example Toyota Vios.',
            'make_model.max' => 'The car model can have up to 60 characters.',
            'make_model.regex' => 'Use letters and numbers for the car model, for example Toyota Vios.',
        ];
    }

    /** Email as typed, lowercase and trimmed. */
    public static function email(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
    }

    /** One space between words, no space at the ends. */
    public static function squish(?string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $text));
    }

    /** Trim and squish named fields in place, before validation runs. */
    public static function tidy(Request $request, string ...$fields): void
    {
        foreach ($fields as $field) {
            if ($request->has($field) && is_string($request->input($field))) {
                $request->merge([$field => self::squish($request->input($field))]);
            }
        }
    }
}
