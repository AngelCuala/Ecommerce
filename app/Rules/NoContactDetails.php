<?php

namespace App\Rules;

use App\Models\ContactStrike;
use App\Support\ContactGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Listings and store details may not carry a phone number, email, link or social account (deals stay on ALVY). */
class NoContactDetails implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ($found = ContactGuard::find($value)) === []) {
            return;
        }
        $what = implode(', ', array_map(fn ($k) => ContactStrike::KIND_LABELS[$k] ?? $k, $found));
        $fail("Remove the {$what} from this field. Buyers order and chat through ALVY, so contact details aren't allowed.");
    }
}
