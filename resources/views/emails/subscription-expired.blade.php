@component('mail::message')
# Your subscription has expired

Hi {{ $user->name }},

Your Market Sharks subscription has expired and access to live trading signals has been paused. Renew today to get back in the game.

@component('mail::button', ['url' => config('app.frontend_url') . '/app/dashboard'])
Renew Now
@endcomponent

Thanks,<br>
Market Sharks Team
@endcomponent