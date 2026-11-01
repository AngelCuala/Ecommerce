<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Portal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/** Second half of App\Http\Middleware\PortalHandoff: signs the account in to this portal. */
class PortalHandoffController extends Controller
{
    public function __invoke(Request $request, string $token)
    {
        $data = Cache::pull('portal-handoff:' . $token);   // single use

        // Expired, used, meant for another portal, or opened in another browser.
        $fromCookie = $data ? $request->cookies->get(Portal::cookieName($data['from'])) : null;
        if (! $data || $data['to'] !== Portal::current() || ! $fromCookie || ! hash_equals((string) $data['from_session'], (string) $fromCookie)) {
            return redirect()->to(Portal::loginUrl())->with('error', 'That sign-in link expired. Please log in again.');
        }

        if ($data['user_id']) {
            $user = User::find($data['user_id']);
            abort_unless($user && Portal::allows($user, Portal::current()), 403);
            Auth::guard('web')->login($user, (bool) $data['remember']);
            $request->session()->regenerate();
        }
        foreach ($data['carry'] as $key => $value) {
            $request->session()->put($key, $value);
        }
        foreach ($data['flash'] as $key => $value) {
            $request->session()->flash($key, $value);
        }

        return redirect()->to($data['url']);
    }
}
