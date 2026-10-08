Account Deletion Request

A staff member has requested that their account be deleted.

Staff Member: {{ $staffName }}
Email: {{ $staffEmail }}
Role: {{ ucfirst($staffRole) }}
Requested: {{ $requestedAt }}
@if($reason)
Reason: {{ $reason }}
@endif

Please review this request in the admin portal.

Note: care records this staff member entered for residents are retained and will not be deleted along with their account.

Thank you,
{{ config('mail.from.name') }}
