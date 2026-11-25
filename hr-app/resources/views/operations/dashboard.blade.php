@extends('operations.layout')
@section('title','Dashboard')
@section('panels')
<section><h2>My pending submissions</h2>
<p><a href="{{ route('leave') }}#status">{{ $pendingLeave }} leave requests</a> ·
<a href="{{ route('claims') }}">{{ $pendingClaims }} expense claims</a> ·
<a href="{{ route('attendance') }}#timesheets">{{ $pendingTimesheets }} timesheets</a></p>
<a href="{{ route('pay.slips') }}">My released payslips</a></section>
@if($reviewer || $reviewCounts)<section><h2>Awaiting my review</h2>
@if($reviewer)<p><a href="{{ route('leave') }}#hr-approvals">Review leave requests</a></p>@endif
@foreach($reviewCounts as $module => $count)<p><a href="{{ route($module) }}#{{ $module === 'claims' ? 'claim-approvals' : 'validation' }}">{{ $count }} {{ $module === 'claims' ? 'claims awaiting Accounts' : 'timesheets awaiting HR' }}</a></p>@endforeach
</section>@endif
<section><h2>Latest company posts</h2>@forelse($posts as $post)<h3>{{ $post->title }}</h3><p>{{ $post->body }}</p>@empty<p>No announcements yet.</p>@endforelse<a href="{{ route('posts') }}">All posts</a></section>
@endsection
