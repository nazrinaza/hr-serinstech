@extends('operations.layout')
@section('title','Reports')
@section('panels')
<section><h2>Export company data</h2><form method="get" action="{{ route('reports.export') }}"><label>CSV report<select name="report"><option value="employees">Employee directory</option><option value="claims">Expense claims</option><option value="attendance">Attendance and HR decisions</option></select></label><button>Download CSV</button></form></section>
@endsection
