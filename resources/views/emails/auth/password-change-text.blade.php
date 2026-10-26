Hi {{ $user->first_name ?: $user->name }},

Someone asked to change the password of your ALVY account{{ $ip ? ' (from IP ' . $ip . ')' : '' }}. To confirm it was you, enter this code on the security page:

{{ $code }}

The code expires in {{ $minutes }} minutes. Your password does not change until the code is entered.

Didn't ask for this? Don't share the code with anyone, not even someone saying they're from ALVY. Someone may know your current password, so sign in and change it, or use "Forgot password" on the login page.

© {{ date('Y') }} ALVY. This is an automated security message.
