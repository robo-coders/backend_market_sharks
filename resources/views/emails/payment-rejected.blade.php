@component('mail::message')
# We couldn't verify your payment

Hi {{ $user->name }},

Thanks for submitting your payment details. Unfortunately we weren't able to verify your submission this time.

@if($reason)
**{{ $reason }}**

@endif
If you think something went wrong, feel free to resubmit via your dashboard or reach out to us directly and we'll sort it out.

@component('mail::button', ['url' => config('app.url') . '/app/dashboard'])
Go to Dashboard
@endcomponent

Thanks,<br>
Market Sharks Team
@endcomponent