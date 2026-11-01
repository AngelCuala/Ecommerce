<?php

namespace App\Http\Controllers;

use App\Models\AccountActivation;
use App\Services\StaffAccounts;
use App\Support\PersonRules;
use Illuminate\Http\Request;

/**
 * The page the activation email opens (a rider or courier made by a logistics company): set a password,
 * then go to the app. It works on a phone with or without the app installed.
 */
class AccountActivationController extends Controller
{
    public function show(string $token)
    {
        $link = AccountActivation::findByToken($token);

        return view('account.activate', ['token' => $token, 'problem' => StaffAccounts::linkProblem($link), 'user' => $link?->user, 'done' => false]);
    }

    public function store(Request $request, string $token)
    {
        $link = AccountActivation::findByToken($token);
        if ($problem = StaffAccounts::linkProblem($link)) {
            return view('account.activate', ['token' => $token, 'problem' => $problem, 'user' => $link?->user, 'done' => false]);
        }

        $data = $request->validate(['password' => PersonRules::password()], PersonRules::passwordMessages()
            + ['password.min' => 'The password must be at least 8 characters, with an uppercase letter, a lowercase letter, a number and a symbol.']);
        $user = StaffAccounts::activate($link, $data['password']);

        return view('account.activate', ['token' => $token, 'problem' => null, 'user' => $user, 'done' => true]);
    }
}
