Hi {{ $user->first_name ?: $user->name }},

We received a request to reset the password for the ALVY account linked to {{ $user->email }}.

Open this link to choose a new password:
{{ $url }}

This link expires in {{ $minutes }} minutes and can only be used once.

Didn't ask for this? You can safely ignore this email. Your password won't change unless you open the link and set a new one. ALVY will never ask for your password by email, chat or phone.

— ALVY
