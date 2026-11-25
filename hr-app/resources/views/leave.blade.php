@extends('workspace')
@section('title', 'Annual leave')
@section('menu')
@include('workspace-menu', ['owner' => false, 'reviewer' => $queue !== null])
@endsection
@section('page-class', 'directory-page')
@section('body')
<a href="{{ route('employees') }}">My profile</a>
<h1>Annual leave</h1>
<nav class="profile-tabs" aria-label="Annual leave"><a href="#balance">Leave balance</a><a href="#request">Request leave</a><a href="#status">Leave status</a></nav>
<section id="balance"><h2>Leave balance</h2>
<dl class="profile-inputs"><div><dt>Allocation</dt><dd>{{ $employee->leave_allocation }} days</dd></div>
<div><dt>Approved against allocation</dt><dd>{{ $employee->leave_allocation - $employee->leave_balance }} days</dd></div>
<div><dt>Pending HR review</dt><dd>{{ $pendingDays }} days</dd></div>
<div><dt>Remaining balance</dt><dd>{{ $employee->leave_balance }} days</dd></div></dl>
<p>Approved leave reduces your balance.</p></section>
<div class="columns">
<section id="status"><h2>My requests</h2>
@forelse($history as $item)
<p>{{ $item->start_date }} – {{ $item->end_date }} · {{ $item->days }}d · {{ ucfirst($item->status) }}</p>
@if($item->status === 'pending')
<form class="decision-actions" method="post" action="{{ route('leave.withdraw', $item->id) }}">
    @csrf @method('PATCH')
    <button type="submit">Withdraw request</button>
</form>
@endif
@empty<p>Your leave requests will appear here.</p>@endforelse
@include('leave-pagination', ['items' => $history])
</section>
<section class="entry-panel" id="request"><h2>Request leave</h2>
<p>{{ $employee->leave_balance }} working days available. Requests count Monday through Friday.</p>
<form method="post" action="{{ route('leave.store') }}">@csrf
<label for="start">First day</label><input id="start" type="date" name="start_date" required value="{{ old('start_date') }}">
<label for="end">Last day</label><input id="end" type="date" name="end_date" required value="{{ old('end_date') }}">
<button>Submit to HR</button></form></section>
</div>
@if($queue !== null)
<section class="leave-settings" id="hr-approvals"><h2>HR approval queue</h2>
@forelse($queue as $item)
<article><h3>{{ $item->name }}</h3><p>{{ $item->start_date }} – {{ $item->end_date }} · {{ $item->days }} working days</p>
<form class="decision-actions" method="post" action="{{ route('leave.decide', $item->id) }}">@csrf @method('PATCH')
<button name="status" value="approved">Approve</button><button name="status" value="declined">Decline</button></form></article>
@empty<p>No requests awaiting your review.</p>@endforelse
@include('leave-pagination', ['items' => $queue])
</section>@endif
@endsection
