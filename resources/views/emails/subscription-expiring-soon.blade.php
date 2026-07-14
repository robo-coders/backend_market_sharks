@component('mail::message')
# Your subscription is expiring soon

Hi {{ $user->name }},

Your {{ ucfirst($subscription->plan) }} subscription expires on **{{ $subscription->expires_at->format('d M Y') }}**. Renew now to keep uninterrupted access to your trading signals.

@component('mail::button', ['url' => config('app.frontend_url') . '/app/dashboard'])
Renew Subscription
@endcomponent

Thanks,<br>
Market Sharks Team
@endcomponent