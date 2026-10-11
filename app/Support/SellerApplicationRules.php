<?php

namespace App\Support;

use App\Models\Category;
use App\Models\SellerApplication;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Validation shared by the seller sign-up page (new account) and the
 * "Become a Seller" form (existing buyer upgrading).
 */
class SellerApplicationRules
{
    public const DOCUMENT_TYPES = 'JPG, PNG or PDF';

    /** Shop categories offered on the form — the store's registered line of business. */
    public static function categories(): array
    {
        return Category::orderBy('name')->pluck('name')->all();
    }

    /**
     * Normalise inputs before validating: mobile "+63 917 123 4567" → "09171234567",
     * TIN "123456789000" → "123-456-789-000", email spaces removed and lower-cased.
     */
    public static function normalize(Request $request): void
    {
        $merge = [];

        if (is_string($phone = $request->input('phone'))) {
            $phone = preg_replace('/[\s\-().]/', '', $phone);
            if (str_starts_with($phone, '+63')) {
                $phone = '0' . substr($phone, 3);
            }
            $merge['phone'] = $phone;
        }

        if (is_string($tin = $request->input('tin'))) {
            $tin = trim($tin);
            if (preg_match('/^\d{3}-?\d{3}-?\d{3}-?\d{3}$/', $tin)) {
                $tin = implode('-', str_split(preg_replace('/\D/', '', $tin), 3));
            }
            $merge['tin'] = $tin;
        }

        if (is_string($email = $request->input('email'))) {
            $merge['email'] = mb_strtolower(preg_replace('/\s+/u', '', $email));
        }

        $request->merge($merge);
    }

    /**
     * @param User|null $user The account applying (null on sign-up). Its own phone,
     *                        shop name and TIN don't count as duplicates.
     */
    public static function rules(?User $user = null): array
    {
        $nameRule = ['required', 'string', 'min:2', 'max:50', 'regex:/^\pL+( \pL+)*$/u'];
        $document = ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'];
        $ownAppId = $user?->sellerApplication?->id;

        return [
            // Personal
            'first_name' => $nameRule,
            'last_name'  => $nameRule,
            'phone'      => ['required', 'string', function (string $attr, $value, Closure $fail) use ($user) {
                if (! ctype_digit((string) $value)) {
                    return $fail('Please enter a valid mobile number (e.g. 09171234567 or +63 917 123 4567).');
                }
                if (strlen($value) !== 11) {
                    return $fail('Mobile number must be exactly 11 digits (e.g. 09171234567).');
                }
                if (! str_starts_with($value, '09')) {
                    return $fail('Please enter a valid mobile number (e.g. 09171234567 or +63 917 123 4567).');
                }
                $taken = User::where(fn ($q) => $q->where('contact_no', $value)->orWhere('phone', $value))
                    ->when($user, fn ($q) => $q->where('id', '!=', $user->id))
                    ->exists();
                if ($taken) {
                    $fail('This mobile number is already in use.');
                }
            }],

            // Shop
            'shop_name' => ['required', 'string', 'min:3', 'max:30', function (string $attr, $value, Closure $fail) use ($ownAppId) {
                $taken = SellerApplication::whereRaw('LOWER(shop_name) = ?', [mb_strtolower(trim($value))])
                    ->when($ownAppId, fn ($q) => $q->where('id', '!=', $ownAppId))
                    ->exists();
                if ($taken) {
                    $fail('This shop name is already taken. Please choose another.');
                }
            }],
            'line_of_business' => ['required', 'string', Rule::in(self::categories())],
            'seller_type'      => ['required', 'string', Rule::in(SellerApplication::SELLER_TYPES)],
            'tin' => ['required', 'string', 'regex:/^\d{3}-\d{3}-\d{3}-\d{3}$/',
                Rule::unique('seller_applications', 'tin')->ignore($ownAppId)],

            // Address
            'region'       => 'required|string|max:120',
            'province'     => 'required|string|max:120',
            'municipality' => 'required|string|max:120',
            'barangay'     => 'required|string|max:120',
            'street'       => 'required|string|max:255',
            'zip_code'     => ['required', 'regex:/^\d{4}$/'],
            // Official PSGC codes from the address dropdowns (hidden inputs) — the routing origin.
            'province_code'     => ['nullable', 'regex:/^\d{9,10}$/'],
            'municipality_code' => ['nullable', 'regex:/^\d{9,10}$/'],

            // Documents — corporations register with the SEC instead of the DTI.
            'government_id'   => ['bail', 'required', ...$document, new \App\Rules\GovernmentId],
            'dti_certificate' => ['required_unless:seller_type,Corporation', 'nullable', ...$document],
            'sec_certificate' => ['required_if:seller_type,Corporation', 'nullable', ...$document],
            'bir_2303'        => ['required', ...$document],

            'terms' => 'accepted',
        ];
    }

    /** Extra rules for a brand-new seller account. */
    public static function accountRules(): array
    {
        return [
            'email'    => ['required', 'string', 'max:255', 'email:rfc', 'regex:/^[^@\s]+@[^@\s]+\.[A-Za-z]{2,}$/', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).+$/'],
        ];
    }

    public static function messages(): array
    {
        $types = self::DOCUMENT_TYPES;

        return [
            'required'                 => 'The :attribute field is required.',

            'first_name.regex'         => 'First name may only contain letters (and spaces between words).',
            'first_name.min'           => 'First name must be at least 2 characters.',
            'first_name.max'           => 'First name must not be longer than 50 characters.',
            'last_name.regex'          => 'Last name may only contain letters (and spaces between words).',
            'last_name.min'            => 'Last name must be at least 2 characters.',
            'last_name.max'            => 'Last name must not be longer than 50 characters.',

            'email.email'              => 'Please enter a valid email address (e.g. juan@gmail.com).',
            'email.regex'              => 'Please enter a valid email address (e.g. juan@gmail.com).',
            'email.unique'             => 'This email address is already in use. Please log in or use a different email.',

            'password.min'             => 'Password must be at least 8 characters.',
            'password.regex'           => 'Password must have at least 8 characters, including an uppercase letter, a lowercase letter, a number and a symbol (e.g. Tindahan@2026).',
            'password.confirmed'       => 'The passwords do not match.',
            'password.current_password'=> 'The password is incorrect. Enter your ALVY account password.',

            'shop_name.min'            => 'Shop name must be at least 3 characters.',
            'shop_name.max'            => 'Shop name must not be longer than 30 characters.',
            'line_of_business.in'      => 'Please choose a shop category from the list.',
            'seller_type.in'           => 'Please choose a seller type from the list.',
            'tin.regex'                => 'Please enter a valid 12-digit TIN in the format 000-000-000-000.',
            'tin.unique'               => 'This TIN is already registered.',
            'zip_code.regex'           => 'Please enter a 4-digit postal code.',

            'dti_certificate.required_unless' => 'The DTI Certificate is required.',
            'sec_certificate.required_if'     => 'The SEC Certificate is required for corporations.',
            'government_id.mimes'      => "The government-issued ID must be a {$types} file.",
            'dti_certificate.mimes'    => "The DTI Certificate must be a {$types} file.",
            'sec_certificate.mimes'    => "The SEC Certificate must be a {$types} file.",
            'bir_2303.mimes'           => "BIR Form 2303 must be a {$types} file.",
            'government_id.max'        => 'The government-issued ID must not be larger than 5 MB.',
            'dti_certificate.max'      => 'The DTI Certificate must not be larger than 5 MB.',
            'sec_certificate.max'      => 'The SEC Certificate must not be larger than 5 MB.',
            'bir_2303.max'             => 'BIR Form 2303 must not be larger than 5 MB.',
            'government_id.uploaded'   => "The government-issued ID could not be uploaded. Use a {$types} file up to 5 MB.",
            'bir_2303.uploaded'        => "BIR Form 2303 could not be uploaded. Use a {$types} file up to 5 MB.",

            'terms.accepted'           => 'You must accept the Terms and Conditions and Privacy Policy.',
        ];
    }

    public static function attributes(): array
    {
        return [
            'first_name'       => 'first name',
            'last_name'        => 'last name',
            'email'            => 'email',
            'phone'            => 'mobile number',
            'shop_name'        => 'shop name',
            'line_of_business' => 'shop category',
            'seller_type'      => 'seller type',
            'tin'              => 'TIN',
            'region'           => 'region',
            'province'         => 'province',
            'municipality'     => 'city / municipality',
            'barangay'         => 'barangay',
            'street'           => 'street address',
            'zip_code'         => 'postal code',
            'government_id'    => 'government-issued ID',
            'dti_certificate'  => 'DTI Certificate',
            'sec_certificate'  => 'SEC Certificate',
            'bir_2303'         => 'BIR Form 2303',
            'password'         => 'password',
            'terms'            => 'Terms and Privacy Policy',
        ];
    }

    /** Store the uploaded documents and build the application's attributes. */
    public static function applicationData(Request $request, array $data): array
    {
        $store = fn (string $field, string $dir) => $request->hasFile($field)
            ? $request->file($field)->store($dir, 'public')
            : null;

        return [
            'first_name'           => $data['first_name'],
            'last_name'            => $data['last_name'],
            'full_name'            => trim($data['first_name'] . ' ' . $data['last_name']),
            'phone'                => $data['phone'],
            'shop_name'            => trim($data['shop_name']),
            'line_of_business'     => $data['line_of_business'],
            'seller_type'          => $data['seller_type'],
            'tin'                  => $data['tin'],
            'region'               => $data['region'],
            'province'             => $data['province'],
            'province_code'        => $data['province_code'] ?? null,
            'municipality'         => $data['municipality'],
            'municipality_code'    => $data['municipality_code'] ?? null,
            'barangay'             => $data['barangay'],
            'street'               => $data['street'],
            'zip_code'             => $data['zip_code'],
            'address'              => implode(', ', array_filter([
                $data['street'], $data['barangay'], $data['municipality'], $data['province'], $data['zip_code'],
            ])),
            'government_id_path'   => $store('government_id', 'seller-ids'),
            ...\App\Rules\GovernmentId::columnsFor('government_id'),
            'dti_certificate_path' => $store('dti_certificate', 'seller-dti'),
            'sec_certificate_path' => $store('sec_certificate', 'seller-sec'),
            'bir_2303_path'        => $store('bir_2303', 'seller-bir'),
        ];
    }
}
