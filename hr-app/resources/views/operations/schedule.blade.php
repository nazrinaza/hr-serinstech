@extends('operations.layout')
@section('title','Shift schedule')
@section('panels')
@if($owner)<section><h2>Schedule a shift</h2>@if(count($staff))<form method="post" action="{{ route('schedule.store') }}">@csrf<label>Employee<select name="employee_id">@foreach($staff as $person)<option value="{{ $person->id }}">{{ $person->name }} ({{ $person->code }})</option>@endforeach</select></label><label>Start (MYT)<input type="datetime-local" name="starts_at" required></label><label>End (MYT)<input type="datetime-local" name="ends_at" required></label><button>Schedule shift</button></form>@else<p>Add an employee first.</p>@endif</section>@endif
<section><h2>Scheduled shifts</h2>@forelse($shifts as $shift)<p>{{ $shift->name }} · {{ $shift->starts_at }} – {{ $shift->ends_at }} (MYT)</p>@empty<p>No shifts scheduled.</p>@endforelse @include('leave-pagination',['items'=>$shifts])</section>
@endsection
