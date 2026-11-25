@extends('operations.layout')
@section('title','Expense claims')
@section('panels')
@unless($owner)
<section class="entry-panel"><h2>{{ $draft ? 'Edit draft claim' : 'Request expense claim' }}</h2>
<form method="post" enctype="multipart/form-data" action="{{ $draft ? route('claims.draft',$draft->id) : route('claims.store') }}">
@csrf @if($draft) @method('PATCH') @endif
<label>Claim type<input name="claim_type" maxlength="100" value="{{ old('claim_type',$draft->claim_type ?? '') }}"></label>
<label>Claim description<input name="description" required maxlength="255" value="{{ old('description',$draft->description ?? '') }}"></label>
<h3>Expense details</h3>
@php($items = old('items', $draft ? json_decode($draft->items, true) : [['spent_on'=>now('Asia/Kuala_Lumpur')->toDateString()]]))
<div id="expense-lines">
@foreach(is_array($items) ? $items : [[]] as $item)
<fieldset><legend>Expense item</legend>
<label>Expense description<input data-field="description" name="items[{{ $loop->index }}][description]" required maxlength="255" value="{{ $item['description'] ?? '' }}"></label>
<label>Date spent<input data-field="spent_on" type="date" name="items[{{ $loop->index }}][spent_on]" required value="{{ $item['spent_on'] ?? '' }}"></label>
<label>Amount (MYR)<input data-field="amount" type="number" name="items[{{ $loop->index }}][amount]" required min=".01" step=".01" value="{{ $item['amount'] ?? '' }}"></label>
<button type="button" data-remove>Remove item</button></fieldset>
@endforeach
</div>
<button type="button" id="add-expense">Add expense item</button>
<p>Total <output id="expense-total" aria-live="polite"></output></p>
<label>Receipt (optional, JPG, PNG or PDF up to 5 MB)<input type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf"></label>
@if($draft?->receipt)<p>The saved receipt is attached. Choose a file to replace it.</p>@endif
<div class="decision-actions"><button name="intent" value="draft">Save draft</button><button name="intent" value="submit">Submit to Accounts</button></div>
</form></section>
<script>
const lines = document.getElementById('expense-lines');
const addItem = document.getElementById('add-expense');
function refreshItems() {
    let cents = 0;
    Array.from(lines.children).forEach((row, index) => {
        row.querySelector('legend').textContent = 'Expense item ' + (index + 1);
        for (const input of row.querySelectorAll('[data-field]')) input.name = 'items[' + index + '][' + input.dataset.field + ']';
        row.querySelector('[data-remove]').disabled = lines.children.length === 1;
        cents += Math.round((Number(row.querySelector('[data-field="amount"]').value) || 0) * 100);
    });
    document.getElementById('expense-total').value = 'RM ' + (cents / 100).toFixed(2);
    addItem.disabled = lines.children.length >= 50;
}
addItem.addEventListener('click', () => {
    const row = lines.firstElementChild.cloneNode(true);
    for (const input of row.querySelectorAll('input')) input.value = '';
    lines.append(row);
    refreshItems();
    row.querySelector('input').focus();
});
lines.addEventListener('click', event => {
    if (event.target.matches('[data-remove]') && lines.children.length > 1) {
        event.target.closest('fieldset').remove();
        refreshItems();
    }
});
lines.addEventListener('input', refreshItems);
refreshItems();
</script>
@endunless
<section><h2>Claim history</h2>@forelse($claims as $row)<article><strong>{{ $row->name }} · RM {{ number_format($row->amount,2) }}</strong><p>{{ $row->spent_on }} · {{ $row->description }} · {{ ucfirst($row->status) }}</p>@include('operations.claim-details')
@if(!$owner && $row->status === 'draft')<a href="{{ route('claims',['draft'=>$row->id]) }}">Continue draft</a>@endif
@if($row->receipt)<a href="{{ route('claims.receipt',$row->id) }}">Download receipt</a>@endif</article>@empty<p>No claims yet.</p>@endforelse @include('leave-pagination',['items'=>$claims])</section>
@include('operations.queue',['module'=>'claims'])
@endsection
