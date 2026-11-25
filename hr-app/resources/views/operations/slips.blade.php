@extends('operations.layout')
@section('title','My payslips')
@section('panels')
<section><h2>Released validation copies</h2>
@forelse($slips as $slip)<p><a href="{{ route('pay.slip',$slip->id) }}">{{ $slip->starts_on }} – {{ $slip->ends_on }}</a></p>@empty<p>No payslips have been released for you.</p>@endforelse
@include('leave-pagination',['items'=>$slips])</section>
@endsection
