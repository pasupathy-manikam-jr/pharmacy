<x-mail::message>
# {{ __(':branch: stock to act on', ['branch' => $branch->name]) }}

@if ($expiring)
## {{ __('Expiring within 60 days') }}

<x-mail::table>
| {{ __('Product') }} | {{ __('Batch') }} | {{ __('Expiry') }} | {{ __('Qty') }} |
|:--|:--|:--|--:|
@foreach ($expiring as $e)
| {{ $e['product'] }} | {{ $e['batch_no'] }} | {{ \Illuminate\Support\Carbon::parse($e['expiry_date'])->format('d M Y') }} | {{ $e['qty'] }} |
@endforeach
</x-mail::table>
@endif

@if ($low)
## {{ __('At or below reorder level') }}

<x-mail::table>
| {{ __('Product') }} | {{ __('On hand') }} | {{ __('Reorder at') }} |
|:--|--:|--:|
@foreach ($low as $p)
| {{ $p['name'] }} | {{ $p['on_hand'] }} | {{ $p['reorder_level'] }} |
@endforeach
</x-mail::table>
@endif

<x-mail::button :url="route('dashboard')">
{{ __('Open dashboard') }}
</x-mail::button>
</x-mail::message>
