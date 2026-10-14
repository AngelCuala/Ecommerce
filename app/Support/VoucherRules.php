<?php

namespace App\Support;

use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Validation shared by the admin (ALVY vouchers) and seller (store vouchers) forms. */
class VoucherRules
{
    public static function validate(Request $request, ?Voucher $voucher = null): array
    {
        // Codes are case-insensitive; store them in upper case.
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        $locked = $voucher && $voucher->used_count > 0;   // already used: keep code and discount fixed

        $data = $request->validate([
            'code'           => $locked ? 'nullable|string'
                                : ['required', 'string', 'min:3', 'max:30', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('vouchers', 'code')->ignore($voucher?->id)],
            'name'           => 'required|string|max:120',
            'discount_type'  => 'required|in:fixed,percent',
            'discount_value' => ['required', 'numeric', 'min:1', $request->input('discount_type') === 'percent' ? 'max:100' : 'max:1000000'],
            'max_discount'   => 'nullable|numeric|min:1|max:1000000',
            'min_spend'      => 'nullable|numeric|min:0|max:10000000',
            'starts_at'      => 'nullable|date',
            'ends_at'        => array_filter(['nullable', 'date',
                                    self::endChanged($request, $voucher) ? 'after:now' : null,
                                    $request->filled('starts_at') ? 'after:starts_at' : null]),
            'usage_limit'    => 'nullable|integer|min:1|max:1000000',
            'per_user_limit' => 'required|integer|min:1|max:100',
            'is_active'      => 'nullable|boolean',
        ], [
            'code.regex'              => 'Use letters, numbers, - or _ only (e.g. SALE1111).',
            'code.unique'             => 'That code is already used by another voucher.',
            'discount_value.max'      => 'A percent discount can\'t be more than 100%.',
            'ends_at.after'           => 'The end must be after the start and in the future.',
        ]);

        if ($locked) {
            if (($data['code'] ?? $voucher->code) !== $voucher->code) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'code' => 'The code can\'t be changed after the voucher has been used.',
                ]);
            }
            unset($data['code']);
            if ((float) $data['discount_value'] !== (float) $voucher->discount_value || $data['discount_type'] !== $voucher->discount_type) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'discount_value' => 'The discount can\'t be changed after the voucher has been used. Turn it off and make a new one instead.',
                ]);
            }
        }

        $data['min_spend']    = $data['min_spend'] ?? 0;
        $data['max_discount'] = $data['discount_type'] === 'percent' ? ($data['max_discount'] ?? null) : null;
        $data['is_active']    = $request->boolean('is_active');

        return $data;
    }

    /** New voucher, or the end date was edited (an unchanged past end date is fine). */
    private static function endChanged(Request $request, ?Voucher $voucher): bool
    {
        if (! $voucher || ! $request->filled('ends_at')) {
            return true;
        }
        if (! $voucher->ends_at) {
            return true;
        }

        try {
            return ! \Carbon\Carbon::parse($request->input('ends_at'))->equalTo($voucher->ends_at->copy()->startOfMinute());
        } catch (\Throwable $e) {
            return true;
        }
    }
}
