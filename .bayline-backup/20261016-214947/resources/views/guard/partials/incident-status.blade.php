@php $cls = ['open' => 'bad', 'under_review' => 'warn', 'resolved' => 'ok'][$status] ?? 'done'; @endphp
<span class="g-tag {{ $cls }}">{{ \App\Models\Incident::statusLabel($status) }}</span>
