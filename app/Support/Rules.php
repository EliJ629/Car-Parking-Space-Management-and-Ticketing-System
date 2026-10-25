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
     * one number, one capital letter and one special character.
     *
     * @return array<int, string>
     */
    public static function driverPassword(): array
    {
        return ['required', 'string', 'min:10', 'max:'.self::PASSWORD_MAX, 'regex:/[A-Z]/', 'regex:/[0-9]/', 'regex:/[^A-Za-z0-9\s]/', 'confirmed'];
    }

    public const DRIVER_PASSWORD_RULE = 'Use at least 10 characters with one number, one capital letter and one special character.';

    /** @return array<string, string> */
    public static function driverPasswordMessages(): array
    {
        $rule = self::DRIVER_PASSWORD_RULE;

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

    /** Three letters then three or four digits, written without spaces: ABC1234. */
    public static function plate(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'regex:'.Vehicle::PLATE_PATTERN];
    }

    public const PLATE_MESSAGE = 'Enter a plate like ABC 1234. Short plates of high offices, such as 5 or SENATE 3, are accepted too.';

    public static function makeModel(): array
    {
        return ['required', 'string', 'min:2', 'max:60', 'regex:'.self::MAKE_MODEL_PATTERN];
    }

    /** @return array<string, string> */
    public static function makeModelMessages(): array
    {
        return [
            'make_model.required' => 'Enter the vehicle make and model, for example Toyota Vios.',
            'make_model.min' => 'Enter the vehicle make and model, for example Toyota Vios.',
            'make_model.max' => 'The vehicle model can have up to 60 characters.',
            'make_model.regex' => 'Use letters and numbers for the vehicle model, for example Toyota Vios.',
        ];
    }

    /**
     * A Gmail address as Google writes them: 6 to 30 letters, numbers and dots, no dot at either end or twice in a row.
     * Plus tags such as maria+x@gmail.com are not Gmail usernames, so they are refused.
     */
    public const GMAIL_PATTERN = '/^(?=[^@]{6,30}@)[a-z0-9]+(?:\.[a-z0-9]+)*@gmail\.com$/';

    public const GMAIL_MESSAGE = 'Use a real Gmail address, for example maria.santos@gmail.com.';

    /**
     * Rules for the email of a driver account: a Gmail address that is not taken. Gmail ignores dots, so
     * maria.santos@gmail.com and mariasantos@gmail.com are one mailbox and count as one account.
     *
     * @return array<int, mixed>
     */
    public static function driverEmail(?int $ignoreUserId = null): array
    {
        return [
            'required', 'string', 'max:255', 'email:rfc', 'regex:'.self::GMAIL_PATTERN,
            function (string $attribute, mixed $value, \Closure $fail) use ($ignoreUserId): void {
                if (self::emailTaken((string) $value, $ignoreUserId)) {
                    $fail('This Gmail account is already registered. Try signing in.');
                    return;
                }

                $allowed = self::registrationAllowlist();

                if ($allowed !== [] && ! in_array(self::email($value), $allowed, true)) {
                    $fail('This address cannot be used right now. Use the Gmail address your team added for testing.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public static function driverEmailMessages(): array
    {
        return [
            'email.required' => 'Enter your Gmail address.',
            'email.email' => self::GMAIL_MESSAGE,
            'email.regex' => self::GMAIL_MESSAGE,
            'email.max' => 'The email can have up to 255 characters.',
        ];
    }

    /**
     * Only these addresses may register, when the list is set (a comma separated list in REGISTRATION_EMAIL_ALLOWLIST).
     * Useful while the mail service only delivers to approved test addresses. Empty means any Gmail address.
     *
     * @return list<string>
     */
    public static function registrationAllowlist(): array
    {
        return array_values(array_filter(array_map(
            fn ($email) => self::email($email),
            (array) config('mail.registration_allowlist', []),
        )));
    }

    /** The same mailbox written the same way: Gmail dots are dropped. */
    public static function emailKey(?string $email): string
    {
        $email = self::email($email);

        if (! str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);

        if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
            return str_replace('.', '', explode('+', $local, 2)[0]).'@gmail.com';
        }

        return $email;
    }

    /** True when another account already uses this mailbox. */
    public static function emailTaken(string $email, ?int $ignoreUserId = null): bool
    {
        $key = self::emailKey($email);

        return \App\Models\User::query()
            ->when($ignoreUserId, fn ($query) => $query->where('id', '!=', $ignoreUserId))
            ->where(fn ($query) => $query->where('email_key', $key)->orWhere('email', self::email($email))->orWhere('pending_email', self::email($email)))
            ->exists();
    }

    /** A name written the same way: lower case, with spaces, dots and hyphens removed. "Juan De La Cruz" and "juan dela cruz" match. */
    public static function nameKey(?string $name): string
    {
        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower((string) $name));
    }

    /** True when another driver account already has this name. */
    public static function driverNameTaken(string $name, ?int $ignoreUserId = null): bool
    {
        return \App\Models\User::query()
            ->where('role', \App\Models\User::ROLE_DRIVER)
            ->when($ignoreUserId, fn ($query) => $query->where('id', '!=', $ignoreUserId))
            ->where('name_key', self::nameKey($name))
            ->exists();
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
