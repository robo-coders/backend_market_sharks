@component('mail::message')
# Account Blocked

Hi {{ $user->name }},

Your Market Sharks account has been blocked by an administrator. You won't be able to access your dashboard or trading signals while your account is in this state.

If you believe this is a mistake, please contact our support team.

Thanks,<br>
Market Sharks Team
@endcomponent