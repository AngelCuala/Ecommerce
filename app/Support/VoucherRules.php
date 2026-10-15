<?php

namespace App\Support;

use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Validation shared by the admin (ALVY vouchers) and seller (store vouchers) forms. */
class VoucherRules
{
    public static function validate(Request $request, ?Voucher $voucher = null, ?int $sellerId = null): array
    {
        // Codes are case-insensitive; store them in upper case.
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        // A27: once any buyer has claimed (or used) it, only the end date and usage limit can change.
        $locked = $voucher && ($voucher->used_count > 0 || $voucher->claims()->exists());

        $data = $request->validate([
            'code'           => $locked ? 'nullable|string'
                                : ['required', 'string', 'regex:/^[A-Z0-9]{4,12}$/', Rule::unique('vouchers', 'code')->ignore($voucher?->id)],
            'name'           => 'required|string|max:120',
            'discount_type'  => 'required|in:fixed,percent',
            'discount_value' => ['required', 'numeric', 'min:1', $request->input('discount_type') === 'percent' ? 'max:99' : 'max:1000000'],
            'max_discount'   => 'nullable|numeric|min:1|max:1000000',
            'min_spend'      => 'nullable|numeric|min:0|max:10000000',
            'starts_at'      => 'nullable|date',
            'ends_at'        => array_filter(['nullable', 'date',
                                    self::endChanged($request, $voucher) ? 'after:now' : null,
                                    $request->filled('starts_at') ? 'after:starts_at' : null]),
            'usage_limit'    => 'nullable|integer|min:1|max:1000000',
            'per_user_limit' => array_filter(['required', 'integer', 'min:1', 'max:100', $request->filled('usage_limit') && is_numeric($request->input('usage_limit')) ? 'lte:usage_limit' : null]),
            // Store vouchers can be limited to some of the seller's own products.
            'product_ids'    => $sellerId ? 'nullable|array' : 'prohibited',
            'product_ids.*'  => $sellerId ? ['integer', Rule::exists('products', 'id')->where('seller_id', $sellerId)->whereNull('deleted_at')] : 'prohibited',
            'is_active'      => 'nullable|boolean',
            'is_claimable'   => 'nullable|boolean',
        ], [
            'code.regex'              => 'Voucher codes are 4 to 12 letters and numbers, with no spaces or symbols (e.g. SALE1111).',
            'code.unique'             => 'That code is already used by another voucher.',
            'discount_value.min'      => 'The discount must be more than 0.',
            'discount_value.max'      => $request->input('discount_type') === 'percent' ? 'A percent discount must be from 1% to 99%.' : 'The discount is too large.',
            'usage_limit.min'         => 'The total uses must be at least 1 (leave it empty for unlimited).',
            'per_user_limit.lte'      => 'Uses per buyer can\'t be more than the total uses allowed.',
            'product_ids.*.exists'    => 'Choose only your own products.',
            'ends_at.after'           => 'The end must be after the start and in the future.',
        ]);

        if ($locked) {
            $msg = 'can\'t be changed after buyers claimed this voucher. You can only extend the end date or raise the usage limit.';
            $errors = [];
            $same = fn ($x, $y) => (string) $x === (string) $y;
            $num  = fn ($x) => $x === null || $x === '' ? null : round((float) $x, 2);
            $dt   = fn ($x) => $x ? \Carbon\Carbon::parse($x)->startOfMinute()->toDateTimeString() : null;
            if (($data['code'] ?? $voucher->code) !== $voucher->code) $errors['code'] = 'The code ' . $msg;
            if (trim($data['name']) !== $voucher->name) $errors['name'] = 'The name ' . $msg;
            if ($data['discount_type'] !== $voucher->discount_type || $num($data['discount_value']) !== $num($voucher->discount_value)) $errors['discount_value'] = 'The discount ' . $msg;
            if ($data['discount_type'] === 'percent' && $num($data['max_discount'] ?? null) !== $num($voucher->max_discount)) $errors['max_discount'] = 'The maximum discount ' . $msg;
            if ($num($data['min_spend'] ?? 0) !== $num($voucher->min_spend ?? 0)) $errors['min_spend'] = 'The minimum spend ' . $msg;
            if ($dt($data['starts_at'] ?? null) !== $dt($voucher->starts_at)) $errors['starts_at'] = 'The start ' . $msg;
            if ((int) $data['per_user_limit'] !== (int) $voucher->per_user_limit) $errors['per_user_limit'] = 'The uses per buyer ' . $msg;
            if ($sellerId && array_map('intval', (array) ($data['product_ids'] ?? [])) != array_map('intval', (array) ($voucher->product_ids ?? []))) $errors['product_ids'] = 'The products ' . $msg;
            if ($request->has('is_claimable') && $request->boolean('is_claimable') !== (bool) $voucher->is_claimable) $errors['is_claimable'] = 'Showing or hiding it ' . $msg;
            // The end date can only move later (or stay); the usage limit can only go up.
            if ($voucher->ends_at && (empty($data['ends_at']) ? false : \Carbon\Carbon::parse($data['ends_at'])->lt($voucher->ends_at->copy()->startOfMinute()))) $errors['ends_at'] = 'The end date can only be extended. Turn the voucher off to end it.';
            if ($voucher->usage_limit !== null && ! empty($data['usage_limit']) && (int) $data['usage_limit'] < $voucher->usage_limit) $errors['usage_limit'] = 'The usage limit can only be raised.';
            if ($errors) {
                throw \Illuminate\Validation\ValidationException::withMessages($errors);
            }
            unset($data['code']);
        }

        // A fixed discount bigger than the minimum spend would make orders free or negative.
        if ($data['discount_type'] === 'fixed' && ($data['min_spend'] ?? 0) > 0 && $data['discount_value'] > $data['min_spend']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'discount_value' => 'The discount (₱' . number_format($data['discount_value'], 2) . ') can\'t be more than the minimum spend (₱' . number_format($data['min_spend'], 2) . ').',
            ]);
        }


        if ($sellerId) {
            $data['product_ids'] = array_values(array_unique(array_map('intval', (array) ($data['product_ids'] ?? [])))) ?: null;
        }
        $data['min_spend']    = $data['min_spend'] ?? 0;
        $data['max_discount'] = $data['discount_type'] === 'percent' ? ($data['max_discount'] ?? null) : null;
        $data['is_active']    = $request->boolean('is_active');
        $data['is_claimable'] = $request->has('is_claimable') ? $request->boolean('is_claimable') : ($voucher->is_claimable ?? true);

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
