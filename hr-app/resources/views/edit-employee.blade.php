@extends('workspace')
@section('title', 'Edit employee')
@section('menu')
@include('workspace-menu', ['owner' => true])
@endsection
@section('page-class', 'directory-page')
@section('body')
<a href="{{ route('employees') }}">Employee directory</a>
<h1>{{ $profile->name }}</h1>
@include('employee-profile-tabs', ['editable' => true])
@if($tab === 'personal')
<div class="columns">
    <section class="entry-panel">
        <h2>Profile details</h2>
        <form method="post" action="{{ route('employees.update', $profile->id) }}">
            @csrf @method('PATCH')
            @include('employee-fields')
            <button>Save changes</button>
        </form>
    </section>
    <section id="employee-access">
        <h2>Employee access</h2>
        @if($profile->user_id)
            <p>Employee login linked. <a href="{{ route('users') }}">Manage login access</a></p>
        @else
            <p>This employee can sign in to view their own profile.</p>
            @if(session('invite_code'))
                <p>Share this code privately with {{ $profile->name }} and direct them to <a href="{{ route('join') }}">{{ route('join') }}</a>. The code expires in 24 hours.</p>
                <code class="invite-code">{{ session('invite_code') }}</code>
            @elseif($profile->invite_hash)
                <p>A new code replaces any previous invitation.</p>
            @endif
            <form method="post" action="{{ route('employees.invite', $profile->id) }}">
                @csrf <button>{{ $profile->invite_hash ? 'Replace invitation code' : 'Create invitation code' }}</button>
            </form>
        @endif
    </section>
</div>
<section class="entry-panel leave-settings"><h2>Leave settings</h2>
<form method="post" action="{{ route('leave.settings', $profile->id) }}">@csrf @method('PATCH')
<label for="opening-days">Annual leave allocation (working days)</label>
<input id="opening-days" name="opening_days" type="number" min="0" max="366" required value="{{ old('opening_days', $profile->leave_allocation) }}">
<label><input class="inline-check" type="checkbox" name="hr_approver" value="1" @checked($profile->hr_approver)> HR leave approver</label>
<p>Approval access requires the Human Resources or HR department.</p><button>Save leave settings</button>
</form></section>
<section class="leave-settings entry-panel"><h2>Close leave year</h2>
<p>Unused days expire when you close the year. Enter a carry-forward limit to retain up to that many unused days.</p>
<form method="post" action="{{ route('leave.rollover',$profile->id) }}">@csrf
<label>Closing calendar year<input type="number" name="year" max="{{ now('Asia/Kuala_Lumpur')->year-1 }}" required></label>
<label>Maximum days to carry forward<input type="number" name="carry_limit" min="0" max="366" value="0" required></label>
<label>Next year's allocation (excluding carried days)<input type="number" name="allocation" min="0" max="366" value="{{ $profile->leave_allocation }}" required></label>
<button>Close year and apply balance</button></form>
@foreach($rollovers as $row)<p>{{ $row->closing_year }} · {{ $row->carried_days }} carried · {{ $row->closing_days - $row->carried_days }} expired · {{ $row->new_allocation }} newly allocated</p>@endforeach
</section>
@endif
@endsection
