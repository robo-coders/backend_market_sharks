@component('mail::message')
# Welcome Back, {{ $user->name }}!

Your Market Sharks account has been unblocked. You now have access to your dashboard again.

@component('mail::button', ['url' => config('app.frontend_url') . '/app/dashboard'])
Open Dashboard
@endcomponent

Thanks,<br>
Market Sharks Team
@endcomponent