@component('mail::message')
# Reset your password

Hello {{ $name }},

We received a request to reset the password for your MediPath account. This link is valid for **{{ $ttl }} minutes** and can be used once.

@component('mail::button', ['url' => $url])
Choose a new password
@endcomponent

If you did not request this, you can ignore the email — your current password stays active. All other signed-in devices will be signed out after the reset.

Thanks,  
The MediPath clinic team
@endcomponent