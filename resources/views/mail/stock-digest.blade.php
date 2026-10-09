<x-mail::message>
# {{ $branch->name }}: stock to act on

@if ($expiring)
## Expiring within 60 days

<x-mail::table>
| Product | Batch | Expiry | Qty |
|:--|:--|:--|--:|
@foreach ($expiring as $e)
| {{ $e['product'] }} | {{ $e['batch_no'] }} | {{ \Illuminate\Support\Carbon::parse($e['expiry_date'])->format('d M Y') }} | {{ $e['qty'] }} |
@endforeach
</x-mail::table>
@endif

@if ($low)
## At or below reorder level

<x-mail::table>
| Product | On hand | Reorder at |
|:--|--:|--:|
@foreach ($low as $p)
| {{ $p['name'] }} | {{ $p['on_hand'] }} | {{ $p['reorder_level'] }} |
@endforeach
</x-mail::table>
@endif

<x-mail::button :url="route('dashboard')">
Open dashboard
</x-mail::button>
</x-mail::message>
