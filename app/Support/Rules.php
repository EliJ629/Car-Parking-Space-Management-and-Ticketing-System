<?php

namespace App\Support;

use App\Models\User;
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

    /** One part of a name (first, middle, last): letters only, one space between words ("Mary Ann", "De la Cruz"). */
    public const NAME_PART_PATTERN = "/^\\p{L}[\\p{L}\\p{M}]*(?: \\p{L}[\\p{L}\\p{M}]*)*$/u";

    /** The letters that may not appear in a name part. The screens use this word list in their messages. */
    public const NAME_PART_MESSAGE = 'Use letters only. No numbers or special characters.';

    /**
     * Rules for the three name fields of a sign up form, plus the "name already used" check on the joined name.
     * Call Rules::joinNameFields($request) first so the joined name is in the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function nameParts(): array
    {
        return [
            'first_name' => ['required', 'string', 'min:2', 'max:40', 'regex:'.self::NAME_PART_PATTERN],
            'middle_name' => ['nullable', 'string', 'max:40', 'regex:'.self::NAME_PART_PATTERN],
            'last_name' => ['required', 'string', 'min:2', 'max:40', 'regex:'.self::NAME_PART_PATTERN],
            'name' => [
                // Looked up only when the parts themselves are well formed: a badly typed name has nothing to look up.
                fn (string $attribute, mixed $value, \Closure $fail) => self::namePartsWellFormed() && self::driverNameTaken((string) $value)
                    ? $fail('An account with this name already exists. If it is yours, sign in instead.')
                    : null,
            ],
        ];
    }

    /** @return array<string, string> */
    public static function namePartMessages(): array
    {
        return [
            'first_name.required' => 'Enter your first name.',
            'first_name.min' => 'Enter your first name.',
            'first_name.max' => 'The first name can have up to 40 characters.',
            'first_name.regex' => 'First name: '.self::NAME_PART_MESSAGE,
            'middle_name.max' => 'The middle name can have up to 40 characters.',
            'middle_name.regex' => 'Middle name: '.self::NAME_PART_MESSAGE,
            'last_name.required' => 'Enter your last name.',
            'last_name.min' => 'Enter your last name.',
            'last_name.max' => 'The last name can have up to 40 characters.',
            'last_name.regex' => 'Last name: '.self::NAME_PART_MESSAGE,
        ];
    }

    /** Tidy the three fields and put the joined full name ("First Middle Last") in the request as "name". */
    public static function joinNameFields(Request $request): void
    {
        self::tidy($request, 'first_name', 'middle_name', 'last_name');
        $request->merge(['name' => self::squish(implode(' ', array_filter([
            (string) $request->input('first_name'),
            (string) $request->input('middle_name'),
            (string) $request->input('last_name'),
        ], fn (string $part) => $part !== '')))]);
    }

    /**
     * A saved full name cut back into three fields, for a form that must open again with what was typed.
     * First word is the first name, last word the last name, anything between is the middle name.
     *
     * @return array{first_name:string,middle_name:string,last_name:string}
     */
    public static function splitName(?string $name): array
    {
        $words = array_values(array_filter(explode(' ', self::squish($name))));

        if (count($words) <= 1) {
            return ['first_name' => $words[0] ?? '', 'middle_name' => '', 'last_name' => ''];
        }

        return [
            'first_name' => $words[0],
            'middle_name' => implode(' ', array_slice($words, 1, -1)),
            'last_name' => $words[count($words) - 1],
        ];
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

    /**
     * Philippine mobile number. Only digits and a leading + are allowed (no letters, spaces or dashes).
     * A number that starts with 09 has exactly 11 digits. A number that starts with +63 has exactly 13 characters
     * (+63 and 10 digits, for example +639171234567). Lot::normalizePhone turns a valid +63 number into the 09 form
     * before this rule runs, so the stored value is always 09 and 9 more digits.
     */
    public static function mobile(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'bail',
            function (string $attribute, mixed $value, \Closure $fail): void {
                $message = self::mobileProblem((string) $value);

                if ($message !== null) {
                    $fail($message);
                }
            },
        ];
    }

    /** What is wrong with the number, or null when it is fine. */
    public static function mobileProblem(string $phone): ?string
    {
        if ($phone === '') {
            return null;
        }

        if (! preg_match('/^\+?\d+$/', $phone)) {
            return 'Use digits only, or a + at the start. No letters, spaces or dashes.';
        }

        if (str_starts_with($phone, '09')) {
            return preg_match('/^09\d{9}$/', $phone) ? null : 'A number that starts with 09 must be exactly 11 digits, for example 09171234567.';
        }

        if (str_starts_with($phone, '+63')) {
            return strlen($phone) === 13 && preg_match('/^\+639\d{9}$/', $phone)
                ? null
                : 'A number that starts with +63 must be exactly 13 characters, for example +639171234567.';
        }

        if (str_starts_with($phone, '63')) {
            return strlen($phone) === 12 && preg_match('/^639\d{9}$/', $phone)
                ? null
                : 'A number that starts with 63 must be exactly 12 digits, for example 639171234567.';
        }

        return 'The number must start with 09, 63 or +63.';
    }

    public const MOBILE_MESSAGE = 'Enter a mobile number like 09171234567, 639171234567 or +639171234567.';

    /**
     * A normal email address with a real domain: a dot and a two-or-more-letter suffix.
     * Local parts are checked by Laravel's RFC validator; this pattern refuses domains such as "example".
     */
    public const EMAIL_DOMAIN_PATTERN = '/^[^@\s]+@(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,}$/';

    public const EMAIL_MESSAGE = 'Enter a valid email with a proper domain, for example name@example.com.';

    /** @return array<int, string> */
    public static function staffEmail(): array
    {
        return ['nullable', 'string', 'max:255', 'email:rfc', 'regex:'.self::EMAIL_DOMAIN_PATTERN];
    }

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

    /** A plate in one of the supported Philippine formats (see PlateNumber). */
    public static function plate(bool $required = true, ?string $typeField = null): array
    {
        return PlateNumber::rules($required, $typeField);
    }

    public const PLATE_MESSAGE = PlateNumber::MESSAGE;

    /**
     * A plate that is about to be used to park, reserve or register a vehicle: a supported format and not on the banned
     * list. Searches, incident reports and ban management use Rules::plate(), which does not look at the ban list.
     */
    public static function parkablePlate(bool $required = true, ?string $typeField = null): array
    {
        return array_merge(['bail'], PlateNumber::rules($required, $typeField), [
            function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && $value !== '' && app(\App\Services\BanService::class)->isBanned($value)) {
                    $fail(\App\Services\VehicleBanned::MESSAGE);
                }
            },
        ]);
    }

    /** The same as parkablePlate, for a temporary plate (the box is optional while the vehicle is still in registration). */
    public static function temporaryPlate(?string $typeField = null): array
    {
        return array_merge(['bail'], PlateNumber::temporaryRules($typeField), [
            function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && $value !== '' && app(\App\Services\BanService::class)->isBanned($value)) {
                    $fail(\App\Services\VehicleBanned::MESSAGE);
                }
            },
        ]);
    }

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

    public const GMAIL_MESSAGE = 'Use a real Gmail address: 6 to 30 letters, numbers or dots before @gmail.com, for example maria.santos@gmail.com.';

    /**
     * Rules for the email of a driver account: a Gmail address that is not taken. Gmail ignores dots, so
     * maria.santos@gmail.com and mariasantos@gmail.com are one mailbox and count as one account.
     *
     * @return array<int, mixed>
     */
    public static function driverEmail(?int $ignoreUserId = null): array
    {
        return [
            'bail', 'required', 'string', 'max:255', 'email:rfc', 'regex:'.self::GMAIL_PATTERN,
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

        return User::query()
            ->when($ignoreUserId, fn ($query) => $query->where('id', '!=', $ignoreUserId))
            ->where(fn ($query) => $query->where('email_key', $key)->orWhere('email', self::email($email))->orWhere('pending_email', self::email($email)))
            ->exists();
    }

    /** A name written the same way: lower case, with spaces, dots and hyphens removed. "Juan De La Cruz" and "juan dela cruz" match. */
    public static function nameKey(?string $name): string
    {
        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower((string) $name));
    }

    /** First and last name, and the middle name when given, pass the letters-only rule. */
    private static function namePartsWellFormed(): bool
    {
        foreach (['first_name', 'last_name'] as $part) {
            $value = (string) request()->input($part, '');

            if (mb_strlen(trim($value)) < 2 || ! preg_match(self::NAME_PART_PATTERN, $value)) {
                return false;
            }
        }

        $middle = trim((string) request()->input('middle_name', ''));

        return $middle === '' || (bool) preg_match(self::NAME_PART_PATTERN, $middle);
    }

    /** True when another driver account already has this name. */
    public static function driverNameTaken(string $name, ?int $ignoreUserId = null): bool
    {
        return User::query()
            ->where('role', User::ROLE_DRIVER)
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
