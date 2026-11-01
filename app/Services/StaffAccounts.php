<?php

namespace App\Services;

use App\Mail\AccountActivationMail;
use App\Models\AccountActivation;
use App\Models\Courier;
use App\Models\Rider;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Riders and couriers made by a logistics company: the login account starts "pending_activation" with an
 * unusable password, and the person sets their own password from an emailed link valid for 24 hours.
 * Resending makes a new link and cancels the old one; a used link can't be used again.
 */
class StaffAccounts
{
    /** The login account of a new rider or courier (role courier, like every rider and courier). */
    public static function makeUser(array $data): User
    {
        $user = new User();
        $user->forceFill([
            'name'            => trim($data['first_name'] . ' ' . $data['last_name']),
            'first_name'      => $data['first_name'],
            'last_name'       => $data['last_name'],
            'email'           => $data['email'],
            'contact_no'      => $data['phone'],
            'birthday'        => $data['birthday'],
            'age'             => \Carbon\Carbon::parse($data['birthday'])->age,
            'role'            => 'courier',
            'approval_status' => 'pending_activation',
            'is_active'       => true,
            'password'        => Hash::make(Str::random(40)),   // nobody knows it until the link sets one
            'email_verified_at' => null,
        ])->save();

        return $user;
    }

    /** A new link (the old unused ones stop working) and the email. Returns false if the mail could not be sent. */
    public static function sendActivation(User $user, string $company): bool
    {
        $token = Str::random(48);
        DB::transaction(function () use ($user, $token) {
            AccountActivation::where('user_id', $user->id)->whereNull('used_at')->delete();
            AccountActivation::create([
                'user_id' => $user->id, 'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addHours(AccountActivation::HOURS),
            ]);
        });

        try {
            Mail::to($user->email)->send(new AccountActivationMail($user, route('account.activate', $token), $company, AccountActivation::HOURS));
            return true;
        } catch (\Throwable $e) {
            Log::warning('Activation email not sent to ' . $user->email . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Why a link can't be used, or null when it can: unknown or replaced by a newer email, already used, expired.
     */
    public static function linkProblem(?AccountActivation $link): ?string
    {
        return match (true) {
            ! $link || ! $link->user                  => 'This activation link is invalid or has been replaced by a newer email. Ask your logistics company to resend it.',
            $link->used_at !== null || ! $link->user->isPendingActivation()
                                                      => 'This activation link has already been used. Log in to the ALVY app with your email and password.',
            $link->isExpired()                        => 'This activation link has expired (links last ' . AccountActivation::HOURS . ' hours). Ask your logistics company to resend it.',
            default                                   => null,
        };
    }

    /** Saves the password and makes the account (and its rider or courier record) Active. */
    public static function activate(AccountActivation $link, string $password): User
    {
        $user = $link->user;
        DB::transaction(function () use ($link, $user, $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                'approval_status' => 'approved',
                'email_verified_at' => now(),   // the link reached their inbox
            ])->save();
            $link->forceFill(['used_at' => now()])->save();
            AccountActivation::where('user_id', $user->id)->whereNull('used_at')->delete();

            Rider::where('user_id', $user->id)->where('application_status', 'pending_activation')
                ->update(['application_status' => 'approved', 'approved_at' => now()]);
            Courier::where('user_id', $user->id)->where('status', 'pending_activation')
                ->update(['status' => 'approved', 'reviewed_at' => now()]);
        });

        $company = Rider::where('user_id', $user->id)->value('sorting_center_id') ?? Courier::where('user_id', $user->id)->value('sorting_center_id');
        if ($company) {
            UserNotification::create(['user_id' => $company, 'title' => 'Account activated',
                'body' => $user->name . ' set a password and can now use the ALVY app.', 'type' => 'courier']);
        }

        return $user;
    }
}
