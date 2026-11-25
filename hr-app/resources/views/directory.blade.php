@extends('workspace')
@section('title', 'Employees')
@section('menu')
@include('workspace-menu', ['owner' => true])
@endsection
@section('page-class', 'directory-page')
@section('body')
<div class="directory-title"><p>{{ $company->name }}</p><h1>Employee <span>directory</span></h1></div>
<div class="columns"><section><h2>Employees</h2>
@if($employees->isEmpty())<p>No employees on this page.</p>@else
<table><thead><tr><th>Employee</th><th>Code</th><th>Department</th></tr></thead>
<tbody>@foreach($employees as $employee)<tr><td><a href="{{ route('employees.edit', $employee->id) }}">{{ $employee->name }}</a></td><td>{{ $employee->code }}</td><td>{{ $employee->department ?? '—' }}</td></tr>@endforeach</tbody></table>@endif
<nav>@if($employees->previousPageUrl())<a href="{{ $employees->previousPageUrl() }}">Previous</a>@endif
@if($employees->nextPageUrl())<a href="{{ $employees->nextPageUrl() }}">Next</a>@endif</nav></section>
<section class="entry-panel" id="add-employee"><h2>Add employee</h2><form method="post" action="{{ route('employees.store') }}">@csrf
@include('employee-fields')
<button>Add employee</button>
</form></section></div>
@endsection
