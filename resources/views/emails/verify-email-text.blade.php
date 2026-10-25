Hi {{ $name }},

@if ($purpose === 'change')
Use this code to confirm the change to your Bayline profile:
@else
Use this code to finish creating your Bayline account:
@endif

{{ $code }}

The code works for {{ $minutes }} minutes. You can also open this link:
{{ $link }}

If you did not ask for this, you can ignore this email. Nothing changes until the code is used.
