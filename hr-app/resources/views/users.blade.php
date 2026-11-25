@extends('workspace')
@section('title', 'User management')
@section('page-class', 'directory-page')
@section('menu')
@include('workspace-menu', ['owner' => true])
@endsection
@section('body')
<div class="directory-title"><p>{{ $company->name }}</p><h1>User <span>management</span></h1></div>
<section class="login-card"><h2>Employee logins</h2>
@forelse($employees as $person)
<article class="login-row"><div><strong>{{ $person->name }}</strong>
@if($person->user_id)
<div>{{ $person->email }} · {{ $person->login_enabled ? 'Active' : 'Suspended' }}</div></div>
<form class="decision-actions" method="post" action="{{ route('users.access', $person->id) }}">@csrf @method('PATCH')
<input type="hidden" name="enabled" value="{{ $person->login_enabled ? '0' : '1' }}">
<button>{{ $person->login_enabled ? 'Suspend login' : 'Enable login' }}</button></form>
@else
<div>{{ $person->invite_expires_at ? (now()->lt($person->invite_expires_at) ? 'Invited' : 'Invitation expired') : 'No login yet' }}</div></div>
<a href="{{ route('employees.edit', $person->id) }}">Manage invitation</a>
@endif
</article>
@empty<p>Add an employee to create their first invitation.</p>@endforelse
@include('leave-pagination', ['items' => $employees])
</section>
@endsection
