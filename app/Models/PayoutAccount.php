<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A saved place to send a withdrawal: GCash, Maya or a bank account (PAYMENTS_SPEC §7). */
class PayoutAccount extends Model
{
    public const METHODS = ['gcash' => 'GCash', 'maya' => 'Maya', 'bank' => 'Bank'];

    protected $fillable = ['user_id', 'method', 'bank_name', 'account_name', 'account_number'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /** "GCash 0917…" / "BDO 1234…" */
    public function label(): string
    {
        return ($this->method === 'bank' ? ($this->bank_name ?: 'Bank') : (self::METHODS[$this->method] ?? $this->method)) . ' ' . $this->account_number;
    }

    /** Validation for a new account: e-wallets take an 11-digit mobile number, banks a 6–20 digit account number. */
    public static function rules(string $prefix = ''): array
    {
        return [
            $prefix . 'method'         => 'required|in:' . implode(',', array_keys(self::METHODS)),
            $prefix . 'bank_name'      => 'required_if:' . $prefix . 'method,bank|nullable|string|max:80',
            $prefix . 'account_name'   => 'required|string|min:2|max:120',
            $prefix . 'account_number' => ['required', 'string', function ($attr, $value, $fail) use ($prefix) {
                $method = request($prefix . 'method');
                $ok = $method === 'bank' ? preg_match('/^\d{6,20}$/', (string) $value) : preg_match('/^09\d{9}$/', (string) $value);
                if (! $ok) {
                    $fail($method === 'bank' ? 'Enter the bank account number (6 to 20 digits).' : 'Enter the 11-digit mobile number, e.g. 09171234567.');
                }
            }],
        ];
    }
}
