<?php

namespace App\Support;

use App\Models\Courier;
use App\Models\Rider;
use App\Models\User;
use Closure;
use Illuminate\Validation\Rules\Password;

/**
 * Rules for a person's details, shared by the sign-up (buyer, courier) and the logistics company's Add Rider /
 * Add Courier forms, so every form checks names, ages, passwords, mobile numbers and plates the same way.
 */
class PersonRules
{
    public const MIN_AGE = 18;

    /** 2 to 50 characters: letters (any language, e.g. ñ), spaces, hyphens, apostrophes and periods — "Ma. Peñaflor-O'Hara". */
    public static function name(): array
    {
        return ['required', 'string', 'min:2', 'max:50', "regex:/^\\pL[\\pL '’.\\-]*$/u"];
    }

    /** Messages for a name field ("first_name" → "First name"). */
    public static function nameMessages(string $field, ?string $label = null): array
    {
        $label ??= ucfirst(str_replace('_', ' ', $field));

        return [
            "$field.regex" => "$label may only contain letters, spaces, hyphens (-), apostrophes (') and periods (.) — no numbers or other symbols.",
            "$field.min"   => "$label must be at least 2 characters.",
            "$field.max"   => "$label must not be longer than 50 characters.",
        ];
    }

    /** A real past date, and at least 18 years ago. */
    public static function adultBirthday(): array
    {
        return ['required', 'date', 'before:today', 'before_or_equal:' . now()->subYears(self::MIN_AGE)->toDateString()];
    }

    public static function birthdayMessages(string $field = 'birthday'): array
    {
        return [
            "$field.before"          => 'Please enter a valid date of birth (it cannot be today or in the future).',
            "$field.before_or_equal" => 'You must be at least ' . self::MIN_AGE . ' years old.',
            "$field.date"            => 'Please enter a valid date of birth.',
        ];
    }

    /** At least 8 characters with an uppercase letter, a lowercase letter, a number and a symbol; typed twice. */
    public static function password(): array
    {
        return ['required', 'string', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()];
    }

    public static function passwordMessages(string $field = 'password'): array
    {
        return ["$field.confirmed" => 'The passwords do not match.'];
    }

    /** 09XXXXXXXXX, not used by another account, rider or courier (+639… is the same number). */
    public static function mobile(?int $ignoreUserId = null, ?int $ignoreRiderId = null, bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'regex:' . PhoneNumber::PATTERN,
            function (string $attr, $value, Closure $fail) use ($ignoreUserId, $ignoreRiderId) {
                if (self::mobileTaken((string) $value, $ignoreUserId, $ignoreRiderId)) {
                    $fail('This mobile number is already in use by another account.');
                }
            }];
    }

    public static function mobileTaken(string $number, ?int $ignoreUserId = null, ?int $ignoreRiderId = null): bool
    {
        return User::where(fn ($q) => $q->where('contact_no', $number)->orWhere('phone', $number))
                ->when($ignoreUserId, fn ($q) => $q->whereKeyNot($ignoreUserId))->exists()
            || Rider::where('phone', $number)->when($ignoreRiderId, fn ($q) => $q->whereKeyNot($ignoreRiderId))->exists()
            || Courier::where('contact_no', $number)->when($ignoreUserId, fn ($q) => $q->where('user_id', '!=', $ignoreUserId))->exists();
    }

    /** The LTO form A00-00-000000 (one letter, 11 digits), not registered to another rider or courier. */
    public const LICENSE_PATTERN = '/^[A-Z]\d{2}-\d{2}-\d{6}$/';

    public static function licenseNumber(?int $ignoreRiderId = null, ?int $ignoreCourierId = null): array
    {
        return ['required', 'string', 'regex:' . self::LICENSE_PATTERN,
            function (string $attr, $value, Closure $fail) use ($ignoreRiderId, $ignoreCourierId) {
                $taken = Rider::whereRaw('UPPER(license_number) = ?', [$value])->when($ignoreRiderId, fn ($q) => $q->whereKeyNot($ignoreRiderId))->exists()
                    || Courier::whereRaw('UPPER(license_number) = ?', [$value])->when($ignoreCourierId, fn ($q) => $q->whereKeyNot($ignoreCourierId))->exists();
                if ($taken) {
                    $fail('This driver\'s license number is already registered.');
                }
            }];
    }

    /** A plate in the OR/CR form (e.g. 123ABC, NAB4521), not registered to another rider or courier. */
    public static function plate(?int $ignoreRiderId = null, ?int $ignoreCourierId = null): array
    {
        return ['required', 'string', 'regex:/^[A-Z0-9-]{4,10}$/',
            function (string $attr, $value, Closure $fail) use ($ignoreRiderId, $ignoreCourierId) {
                if (self::plateTaken((string) $value, $ignoreRiderId, $ignoreCourierId)) {
                    $fail('This plate number is already registered.');
                }
            }];
    }

    /** A valid address with a domain like example.com, not used by any account (one email = one account). */
    public static function email(): array
    {
        return ['required', 'string', 'max:255', 'email:rfc', 'regex:/^[^@\s]+@[^@\s]+\.[A-Za-z]{2,}$/', 'unique:users,email'];
    }

    /** A JPG, PNG or PDF of at most 5 MB (5,120 KB). */
    public static function document(): array
    {
        return ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'mimetypes:image/jpeg,image/png,application/pdf', 'max:5120'];
    }

    /** Messages for the staff forms: license, expiry, plate and document fields. */
    public static function staffMessages(array $documents): array
    {
        $m = [
            'license_number.regex'          => 'Enter the license number in the form A00-00-000000 (e.g. N01-23-456789).',
            'license_expiry.after_or_equal' => 'This driver\'s license has expired. Enter an expiry date of today or later.',
            'plate_number.regex'            => 'Enter the plate number as shown on the OR/CR, e.g. 123ABC or NBC1234.',
            'email.unique'                  => 'This email is already in use by another account.',
            'email.email'                   => 'Please enter a valid email address (e.g. name@example.com).',
            'email.regex'                   => 'Please enter a valid email address (e.g. name@example.com).',
        ];
        foreach ($documents as $field => $label) {
            $m["$field.mimes"] = $m["$field.mimetypes"] = "$label must be a JPG, PNG or PDF file.";
            $m["$field.max"] = "$label must be 5 MB or smaller.";
            $m["$field.uploaded"] = "$label must be 5 MB or smaller.";
            $m["$field.required"] = "Upload the $label.";
        }

        return $m;
    }

    /** A plate not registered to another courier or rider (spaces and case ignored). */
    public static function plateTaken(string $plate, ?int $ignoreRiderId = null, ?int $ignoreCourierId = null): bool
    {
        $norm = strtoupper(preg_replace('/\s+/', '', $plate));
        $sql  = "UPPER(REPLACE(plate_number, ' ', '')) = ?";

        return Rider::whereRaw($sql, [$norm])->when($ignoreRiderId, fn ($q) => $q->whereKeyNot($ignoreRiderId))->exists()
            || Courier::whereRaw($sql, [$norm])->when($ignoreCourierId, fn ($q) => $q->whereKeyNot($ignoreCourierId))->exists();
    }
}
