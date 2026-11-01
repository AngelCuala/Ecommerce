<?php

namespace App\Rules;

use App\Services\IdCheck\IdCheckResult;
use App\Services\IdCheck\IdDocumentChecker;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Rejects uploads that aren't government-issued IDs. Put it last (after the
 * file/mimes/max rules, with `bail`) so only real image/PDF uploads are sent
 * for checking. Each result is kept for the request so the controller can
 * save it (see GovernmentId::resultFor()).
 */
class GovernmentId implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $result = app(IdDocumentChecker::class)->check($value);
        // Kept on the current request only, so results never leak between requests.
        request()->attributes->set('id_check.' . $attribute, $result);

        if ($result->rejected()) {
            $fail("This doesn't look like a government-issued ID"
                . ($result->documentType ? " (it looks like: {$result->documentType})" : '')
                . '. Please upload a clear photo of a valid ID, such as your PhilID, driver\'s license, passport or UMID.');
        }
    }

    /** The check result for a field in this request, if it was checked. */
    public static function resultFor(string $attribute): ?IdCheckResult
    {
        return request()->attributes->get('id_check.' . $attribute);
    }

    /** Attributes to store on the record that owns the ID. */
    public static function columnsFor(string $attribute): array
    {
        $r = self::resultFor($attribute);

        return $r ? ['id_check_status' => $r->status, 'id_check_note' => $r->documentType ?: $r->reason] : [];
    }
}
