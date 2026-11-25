@extends('operations.layout')
@section('title','Time management')
@section('panels')
<nav class="time-links" aria-label="Time management sections">
    <a href="#scheduling">Scheduling</a>
    @unless($owner)<a href="#clock">Attendance clock</a>@endunless
    <a href="#timesheets">Timesheets</a>
    @if($queue !== null)<a href="#validation">HR validation</a>@endif
</nav>
<section id="scheduling">
    <div class="roster-heading"><h2>Employee schedules</h2>@if($owner)<a href="{{ route('schedule') }}">Assign shift</a>@endif</div>
    <form class="roster-filter" method="get" action="{{ route('attendance') }}">
        <label>Week containing<input type="date" name="week" value="{{ $week->toDateString() }}"></label>
        <label>Employee name or code<input type="search" name="search" maxlength="100" value="{{ $search }}"></label>
        <button>Show schedule</button>
    </form>
    <nav class="week-links" aria-label="Schedule week">
        <a href="{{ route('attendance',['week'=>$week->subWeek()->toDateString(),'search'=>$search]) }}">Previous week</a>
        <strong>{{ $week->format('j M') }} – {{ $week->addDays(6)->format('j M Y') }} (MYT)</strong>
        <a href="{{ route('attendance',['week'=>$week->addWeek()->toDateString(),'search'=>$search]) }}">Next week</a>
    </nav>
    @if($people->count())
    <div class="roster-scroll" tabindex="0" role="region" aria-label="Weekly employee roster">
        <table><thead><tr><th scope="col">Employee</th>@foreach($days as $day)<th scope="col">{{ $day->format('D j M') }}</th>@endforeach</tr></thead>
        <tbody>@foreach($people as $person)
            <tr><th scope="row">{{ $person->name }}<small>{{ $person->code }}</small></th>
            @foreach($days as $day)
                <td>@forelse($shifts->get($person->id, collect())->filter(fn ($shift) => $shift->starts_at < $day->addDay()->toDateTimeString() && $shift->ends_at > $day->toDateTimeString()) as $shift)
                    <span class="roster-hours">{{ $shift->starts_at < $day->toDateTimeString() ? '00:00' : substr($shift->starts_at,11,5) }}–{{ $shift->ends_at >= $day->addDay()->toDateTimeString() ? '24:00' : substr($shift->ends_at,11,5) }}</span>
                @empty<span aria-label="No scheduled shift">—</span>@endforelse</td>
            @endforeach</tr>
        @endforeach</tbody></table>
    </div>
    @include('leave-pagination',['items'=>$people])
    @else<p>{{ $search !== '' ? 'No employees match your search.' : 'No employees to schedule yet.' }}</p>@endif
</section>
@unless($owner)<section id="clock"><h2>Attendance clock</h2><form method="post" action="{{ route('attendance.clock') }}">@csrf <input type="hidden" name="action" value="{{ $open ? 'out' : 'in' }}">@if($open)<input type="hidden" name="entry_id" value="{{ $open }}">@endif<button>{{ $open ? 'Clock out and submit to HR' : 'Clock in' }}</button></form></section>@endunless
<section id="timesheets"><h2>Timesheets</h2>@forelse($entries as $row)<article><strong>{{ $row->name }}</strong><p>@include('operations.shift') · {{ ucfirst($row->status) }}</p></article>@empty<p>No attendance entries yet.</p>@endforelse @include('leave-pagination',['items'=>$entries])</section>
@if($queue !== null)<div id="validation">@include('operations.queue',['module'=>'attendance'])</div>@endif
@endsection
