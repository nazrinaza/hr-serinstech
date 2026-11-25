@extends('workspace')
@section('title','Admin dashboard')
@section('page-class','directory-page')
@section('menu')
@include('workspace-menu',compact('owner','reviewer'))
@endsection
@section('body')
<div class="directory-title brief-heading">
    <div><p>{{ $company->name }}</p><h1>Admin <span>dashboard</span></h1></div>
    <time datetime="{{ now('Asia/Kuala_Lumpur')->toDateString() }}">{{ now('Asia/Kuala_Lumpur')->format('l, j F Y') }}</time>
</div>
<div class="columns brief-grid">
    <section class="brief-queue">
        <h2>Awaiting approval</h2>
        <dl>
            @foreach([['Leave requests','Designated HR approvers',$pendingLeave],['Timesheets','Human Resources',$pendingTimesheets],['Expense claims','Account Department',$pendingClaims]] as [$label,$department,$count])
                <div><dt>{{ $label }}<small>{{ $department }}</small></dt><dd aria-label="{{ $count }} pending">{{ number_format($count) }}</dd></div>
            @endforeach
        </dl>
    </section>
    <section class="brief-pay">
        <h2>Payroll desk</h2>
        @if($period)
            <p class="period-state">{{ $period->released_at ? 'Latest released period' : 'Latest draft period' }}</p>
            <h3>{{ \Carbon\Carbon::parse($period->starts_on)->format('j M Y') }} – {{ \Carbon\Carbon::parse($period->ends_on)->format('j M Y') }}</h3>
            <a class="workspace-action" href="{{ route('pay.register',$period->id) }}">Open payroll register</a>
        @else
            <h3>Set up your first pay period</h3>
            <p>Choose the dates to start a payroll register.</p>
            <a class="workspace-action" href="{{ route('periods') }}">Create pay period</a>
        @endif
    </section>
    <section class="brief-team"><h2>People</h2>
        <p><strong>{{ number_format($headcount) }}</strong> {{ Str::plural('employee',$headcount) }} in the directory</p>
        <div class="brief-links"><a href="{{ route('employees') }}">View directory</a><a href="{{ route('employees') }}#add-employee">Add employee</a><a href="{{ route('users') }}">Manage logins</a></div>
    </section>
    <section class="brief-posts"><h2>Company noticeboard</h2>
        @forelse($posts as $post)<article><h3>{{ $post->title }}</h3><p>{{ Str::limit($post->body,140) }}</p></article>
        @empty<p>No announcements yet. Share an update with your team.</p>@endforelse
        <a href="{{ route('posts') }}">{{ $posts->isEmpty() ? 'Write an announcement' : 'Open company posts' }}</a>
    </section>
</div>
@endsection
