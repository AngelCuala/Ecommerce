<x-layout title="Sign In — ALVY">

<div class="mx-auto flex min-h-[70vh] max-w-md flex-col justify-center px-4 py-16 sm:px-6">
    <div class="text-center">
        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full font-display text-xl font-bold"
              style="background:#fa4e1c;color:#FFFFFF;">B</span>
        <h1 class="mt-4 font-display text-2xl font-bold" style="color:#222222;">Welcome back</h1>
        <p class="mt-1 text-sm" style="color:#555555;">Sign in to continue to ALVY.</p>
    </div>

    <div class="card mt-8 p-8">
        <form action="{{ route('login') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="text-xs font-semibold" style="color:#6b90aa;">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" class="input mt-1" required autofocus style="border-color:#FFDCC2;">
            </div>
            <div>
                <label class="text-xs font-semibold" style="color:#6b90aa;">Password</label>
                <input type="password" name="password" class="input mt-1" required style="border-color:#FFDCC2;">
            </div>
            <div class="flex items-center justify-between text-sm">
                <label class="flex items-center gap-2" style="color:#555555;">
                    <input type="checkbox" name="remember" style="accent-color:#fa4e1c;"> Remember me
                </label>
                <a href="#" style="color:#fa4e1c;" onmouseover="this.style.textDecoration='underline';" onmouseout="this.style.textDecoration='none';">Forgot password?</a>
            </div>
            <button type="submit" class="btn-gold w-full" style="background:#fa4e1c;border-color:#fa4e1c;color:#FFFFFF;">Sign In</button>
        </form>
        <p class="mt-6 text-center text-sm" style="color:#555555;">
            Don't have an account? <a href="{{ route('register') }}" class="font-semibold" style="color:#fa4e1c;" onmouseover="this.style.textDecoration='underline';" onmouseout="this.style.textDecoration='none';">Create one</a>
        </p>
    </div>

    <p class="mt-6 text-center text-xs" style="color:#6b90aa;">Demo admin: admin@ALVY.test / password</p>
</div>

</x-layout>