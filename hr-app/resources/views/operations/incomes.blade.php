@extends('operations.layout')
@section('title','Previous incomes')
@section('panels')
<section><h2>Import monthly income</h2><p>Upload a CSV with the columns <code>employee_code,month,amount_myr</code>. Use YYYY-MM for the month. Importing the same employee and month replaces its amount.</p><form method="post" enctype="multipart/form-data" action="{{ route('incomes.import') }}">@csrf<label>Income CSV (up to 1 MB)<input type="file" name="csv" accept=".csv,text/csv" required></label><button>Import incomes</button></form></section>
<section><h2>Income history</h2><a href="{{ route('reports.export',['report'=>'incomes']) }}">Export income CSV</a>@forelse($rows as $row)<p>{{ $row->name }} · {{ substr($row->month,0,7) }} · RM {{ number_format($row->amount_cents/100,2) }}</p>@empty<p>No previous income records.</p>@endforelse @include('leave-pagination',['items'=>$rows])</section>
@endsection
