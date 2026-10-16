@php
    $label = ['paid' => 'Paid', 'unpaid' => 'Unpaid', 'exited' => 'Exited'][$state] ?? $state;
    $cls = ['paid' => 'ok', 'unpaid' => 'bad', 'exited' => 'done'][$state] ?? 'done';
@endphp
<span class="g-tag {{ $cls }}">{{ $label }}</span>
