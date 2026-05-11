@component('mail::message')
# Congrats, {{ $user->name }}!

You're all set. We've reviewed your payment and everything looks good. Your Market Sharks subscription is now active.

Head over to your dashboard to start exploring your signals.

@component('mail::button', ['url' => config('app.frontend_url') . '/app/dashboard'])
Open Dashboard
@endcomponent

Thanks,<br>
Market Sharks Team
@endcomponent