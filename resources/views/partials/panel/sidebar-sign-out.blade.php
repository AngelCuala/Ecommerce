{{-- Sign Out button pinned to the bottom of a panel sidebar --}}
<div class="border-t px-3 py-3" style="border-color:#ECE7E3;">
    <form action="{{ route('logout') }}" method="POST" onsubmit="return confirm('Sign out of ALVY?')">
        @csrf
        <button type="submit"
                class="flex w-full items-center justify-center gap-2 rounded-md px-3 py-2 text-sm font-semibold text-white transition hover:opacity-90"
                style="background:#DC2626;">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg>
            Sign Out
        </button>
    </form>
</div>
