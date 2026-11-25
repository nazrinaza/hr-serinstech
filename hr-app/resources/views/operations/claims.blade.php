@extends('operations.layout')
@section('title','Expense claims')
@section('panels')
@unless($owner)<section class="entry-panel"><h2>Submit expense claim</h2><form method="post" enctype="multipart/form-data" action="{{ route('claims.store') }}">@csrf
<label>Expense date<input type="date" name="spent_on" required></label><label>Description<input name="description" maxlength="255" required></label>
<label>Amount (MYR)<input type="number" name="amount" min="0.01" step="0.01" required></label><label>Receipt (optional, JPG, PNG or PDF up to 5 MB)<input type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf"></label><button>Submit to Accounts</button></form></section>@endunless
<section><h2>Claim history</h2>@forelse($claims as $row)<article><strong>{{ $row->name }} · RM {{ number_format($row->amount,2) }}</strong><p>{{ $row->spent_on }} · {{ $row->description }} · {{ ucfirst($row->status) }}</p>@if($row->receipt)<a href="{{ route('claims.receipt',$row->id) }}">Download receipt</a>@endif</article>@empty<p>No claims yet.</p>@endforelse @include('leave-pagination',['items'=>$claims])</section>
@include('operations.queue',['module'=>'claims'])
@endsection
