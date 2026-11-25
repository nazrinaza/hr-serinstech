@extends('operations.layout')
@section('title','Pay periods')
@section('panels')
<section class="entry-panel"><h2>New pay period</h2><form method="post" action="{{ route('periods.store') }}">@csrf<label>Start date<input type="date" name="starts_on" required></label><label>End date<input type="date" name="ends_on" required></label><button>Create period</button></form></section>
<section><h2>Periods</h2>@forelse($periods as $period)<p><a href="{{ route('pay.register',$period->id) }}">{{ $period->starts_on }} – {{ $period->ends_on }}</a> · {{ $period->released_at ? 'Locked' : 'Draft' }}</p>@empty<p>No pay periods yet.</p>@endforelse @include('leave-pagination',['items'=>$periods])</section>
@endsection
