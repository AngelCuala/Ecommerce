<x-layout title="Get the ALVY app — ALVY">
<div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">

    <nav class="mb-6 text-xs" style="color:#6b90aa;">
        <a href="{{ route('home') }}" onmouseover="this.style.color='#fa4e1c';" onmouseout="this.style.color='#6b90aa';">Home</a>
        <span class="mx-1.5">›</span>
        <span>Get the app</span>
    </nav>

    <h1 class="font-display text-3xl font-extrabold mb-2" style="color:#222222;">Get the ALVY app</h1>
    <p class="text-sm mb-8" style="color:#6b90aa;">
        Shop, sell, deliver and run your logistics company from your phone. It is the same ALVY as this website:
        sign in with the same account, and everything you do shows up on both.
    </p>

    <div class="card p-8">
        @if ($apk)
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl text-lg font-extrabold text-white" style="background:#fa4e1c;">ALVY</span>
                    <div>
                        <p class="font-bold" style="color:#222222;">ALVY for Android</p>
                        <p class="text-xs" style="color:#6b90aa;">
                            {{ number_format($apk['size'] / 1048576, 1) }} MB · updated {{ $apk['updated_at']->format('M j, Y') }}
                        </p>
                    </div>
                </div>
                <a href="{{ route('app.download') }}" data-download
                   class="inline-flex items-center justify-center gap-2 rounded-xl px-6 py-3 text-sm font-semibold text-white transition hover:opacity-90"
                   style="background:#fa4e1c;">
                    Download for Android
                </a>
            </div>

            <h2 class="mt-8 mb-3 text-sm font-semibold" style="color:#222222;">How to install</h2>
            <ol class="list-decimal space-y-2 pl-5 text-sm" style="color:#444444;">
                <li>Open this page on your Android phone and tap <strong>Download for Android</strong>.</li>
                <li>Open the downloaded <strong>ALVYSHOP.apk</strong>. If your phone asks, allow installing apps from your browser or files app.</li>
                <li>Tap <strong>Install</strong>, then open ALVY and sign in with your ALVY account (or create one).</li>
            </ol>
            <p class="mt-6 text-xs" style="color:#6b90aa;">
                Android only for now. Installing a new version over the old one keeps you signed in.
            </p>
        @else
            <div class="flex flex-col items-center gap-3 py-8 text-center">
                <span class="text-4xl">📱</span>
                <p class="font-bold" style="color:#222222;">The app is not available for download yet</p>
                <p class="text-sm" style="color:#6b90aa;">Check back soon. Meanwhile, everything works here on the website.</p>
            </div>
        @endif
    </div>
</div>
</x-layout>
