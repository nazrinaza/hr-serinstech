@extends('operations.layout')
@section('title','Payroll register')
@section('panels')
<section><h2>{{ $period->starts_on }} – {{ $period->ends_on }}</h2>
<p>MYR · {{ $period->released_at ? 'Locked' : 'Draft' }} · Validation copies use manually entered amounts. Statutory contributions are not calculated.</p>
@if($lines->isNotEmpty())
<table><thead><tr><th>Employee</th><th>Gross</th><th>Deductions</th><th>Net</th></tr></thead><tbody>
@foreach($lines as $line)<tr><td><a href="{{ route('pay.slip',$line->id) }}">{{ $line->employee_name }} ({{ $line->employee_code }})</a></td>
@foreach(['gross_cents','deductions_cents','net_cents'] as $key)<td>{{ number_format($line->$key/100,2) }}</td>@endforeach</tr>@endforeach
</tbody><tfoot><tr><th>Total MYR</th>@foreach(['gross_cents','deductions_cents','net_cents'] as $key)<td>{{ number_format($lines->sum($key)/100,2) }}</td>@endforeach</tr></tfoot></table>
@else<p>No entries for this period yet.</p>@endif</section>
@unless($period->released_at)
@if($staff->isNotEmpty())
<section><h2>Employee amounts</h2>
<form method="get"><label>Employee<select name="edit">@foreach($staff as $person)<option value="{{ $person->id }}" @selected(request('edit') == $person->id)>{{ $person->name }} ({{ $person->code }})</option>@endforeach</select></label><button>Load employee</button></form>
@php($selected = $staff->firstWhere('id',request('edit')))
@if($selected)
@php($entry = $lines->firstWhere('employee_id',$selected->id))
<h3>{{ $selected->name }}</h3>
<form method="post" action="{{ route('pay.save',$period->id) }}">@csrf
<input type="hidden" name="employee_id" value="{{ $selected->id }}">
@foreach($fields as $key=>$label)<label>{{ $label }} (MYR)<input type="number" min="0" max="99999999.99" step=".01" name="{{ $key }}" required value="{{ old($key,number_format(($entry?->{$key.'_cents'} ?? 0)/100,2,'.','')) }}"></label>@endforeach<button>Save amounts</button></form>
@endif</section>@endif
@if($lines->isNotEmpty())<section><h2>Release validation copies</h2><p>This locks the saved entries and makes each payslip visible to its employee.</p><form method="post" action="{{ route('pay.release',$period->id) }}">@csrf<button>Lock and release validation copies</button></form></section>@endif
@endunless
@endsection
