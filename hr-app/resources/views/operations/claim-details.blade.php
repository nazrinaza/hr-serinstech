@if($row->claim_type)<p>{{ $row->claim_type }}</p>@endif
@if($row->items)
<ul>@foreach(json_decode($row->items, true) as $item)
<li>{{ $item['spent_on'] }} · {{ $item['description'] }} · RM {{ number_format($item['amount'],2) }}</li>
@endforeach</ul>
@endif
