{{-- The login page opened while already signed in (usually the browser's Back button after logging in):
     ask before signing out, so nobody wonders why they were logged out. --}}
<x-auth-shell title="Already signed in — ALVY" heading="You're still logged in" subtitle="Do you want to log out?">
    <div class="px-6 pb-8 pt-6 sm:px-10 sm:pb-10">
        <div class="mb-6 flex items-center gap-3 rounded-xl border p-4" style="border-color:#F0E4DC;background:#FCFAF8;">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full text-base font-bold text-white" style="background:linear-gradient(135deg,#fa4e1c,#ff7a45);">
                @if ($user->profile_photo_url)
                    <img src="{{ $user->profile_photo_url }}" alt="" class="h-full w-full object-cover">
                @else
                    {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                @endif
            </div>
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold" style="color:#222;">{{ $user->name }}</p>
                <p class="truncate text-xs" style="color:#8a7a70;">{{ $user->email }}</p>
            </div>
        </div>

        <p class="mb-6 text-sm leading-relaxed" style="color:#5a4d45;">
            You went back to the login page, but you are still logged in. Log out only if you want to sign in with another account.
        </p>

        <a href="{{ $continueUrl }}" onclick="location.replace(this.href); return false;"
           class="flex w-full items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-bold text-white"
           style="background:linear-gradient(135deg,#fa4e1c,#ff7a45);box-shadow:0 12px 24px -12px rgba(250,78,28,.8);">
            No, stay logged in
        </a>

        {{-- data-confirmed: this page is already the question, so no second "Sign out?" pop-up. --}}
        <form method="POST" action="{{ $logoutUrl }}" data-confirmed="1" class="mt-3">
            @csrf
            <input type="hidden" name="next" value="login">
            <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl border px-4 py-3 text-sm font-semibold"
                    style="border-color:#E6D9CF;color:#DC2626;background:#fff;">
                Yes, log out
            </button>
        </form>
    </div>
</x-auth-shell>
