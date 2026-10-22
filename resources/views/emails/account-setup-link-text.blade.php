NU Clark Student Discipline Office

Dear {{ $recipientName }},

Activate your PRISM account

Your {{ $roleLabel }} account has been created. Use this secure one-time link to create your password:

{{ $setupUrl }}

Registered email: {{ $email }}
Link expires: {{ $expiresAt->format('F j, Y \a\t g:i A') }}

@if($isStudent)
After creating your password, complete your student profile and acknowledge that your information is accurate.
@else
After creating your password, continue to your assigned PRISM access.
@endif

This link can only be used once and becomes invalid when a replacement link is issued. Do not forward this email. If you did not expect this account, contact the Student Discipline Office.

© {{ $year }} NU Clark Student Discipline Office · PRISM
