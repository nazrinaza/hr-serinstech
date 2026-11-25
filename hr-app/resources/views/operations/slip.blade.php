@extends('operations.layout')
@section('title','Payslip')
@section('panels')
<section><h2>{{ $row->company_name }}</h2><p>{{ $row->released_at ? 'Released validation copy' : 'Draft preview' }} · {{ $row->starts_on }} – {{ $row->ends_on }}</p>
<h3>{{ $row->employee_name }} · {{ $row->employee_code }}</h3>
<table><thead><tr><th>Component</th><th>MYR</th></tr></thead><tbody>
@foreach($fields as $key=>$label)<tr><th>{{ $label }}</th><td>{{ number_format($row->{$key.'_cents'}/100,2) }}</td></tr>@endforeach
<tr><th>Gross earnings</th><td>{{ number_format($row->gross_cents/100,2) }}</td></tr>
<tr><th>Net after entered deductions</th><td>{{ number_format($row->net_cents/100,2) }}</td></tr></tbody></table>
<p>For validation only. Statutory contributions are not calculated.</p></section>
@endsection
