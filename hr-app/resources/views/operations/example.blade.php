@extends('operations.layout')
@section('title','Payroll example')
@section('panels')
<section><h2>Synthetic monthly calculation</h2><p>Try illustrative amounts in MYR for a fictional employee. This preview excludes statutory contributions and does not issue payslips or send payments.</p><form method="post" action="{{ route('payroll.calculate') }}">@csrf @foreach(['base'=>'Base salary','overtime'=>'Overtime earnings','allowances'=>'Allowances','service_charge'=>'Service charge allocation','deductions'=>'Other deductions'] as $key=>$label)<label>{{ $label }} (MYR)<input type="number" step="0.01" min="0" name="{{ $key }}" value="{{ old($key,$values[$key]) }}" required></label>@endforeach<button>Calculate example</button></form></section>
<section><h2>Calculation result</h2><p>Gross earnings · RM {{ number_format($gross/100,2) }}</p><p>After entered deductions · RM {{ number_format($net/100,2) }}</p></section>
@endsection
