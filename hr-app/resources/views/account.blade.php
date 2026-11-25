@extends('workspace')
@section('title', 'My account')
@section('page-class', 'directory-page')
@section('menu')
@include('workspace-menu', compact('owner', 'reviewer'))
@endsection
@section('body')
<h1>My <span>account</span></h1>
<section class="entry-panel access"><h2>Login profile</h2>
<form method="post" action="{{ route('account.update') }}">@csrf @method('PATCH')
<label for="account-name">Display name</label><input id="account-name" name="name" required maxlength="255" autocomplete="name" value="{{ old('name', auth()->user()->name) }}">
<label for="account-email">Login email</label><input id="account-email" type="email" name="email" required maxlength="255" autocomplete="username" value="{{ old('email', auth()->user()->email) }}">
<label for="current">Current password</label><input id="current" name="current_password" type="password" required autocomplete="current-password">
<label for="new">New password (optional, at least 12 characters)</label><input id="new" name="password" type="password" minlength="12" autocomplete="new-password">
<label for="confirm">Confirm new password</label><input id="confirm" name="password_confirmation" type="password" autocomplete="new-password">
<button>Save account</button></form></section>
@endsection
