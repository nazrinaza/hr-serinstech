@extends('workspace')
@section('title', 'Join your workspace')
@section('page-class', 'login-page')
@section('header', ' ')
@section('body')
<section class="login-card">
    <h1>Join your <span>workspace</span></h1>
    <p>Use the invitation code provided by your workspace owner.</p>
    <form method="post" action="{{ route('join.store') }}">
        @csrf
        <label for="code">Invitation code</label><input id="code" name="invite_code" required autocomplete="off">
        <label for="email">Email address</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username">
        <label for="password">Password (12 characters minimum)</label><input id="password" type="password" name="password" required autocomplete="new-password">
        <label for="confirm">Confirm password</label><input id="confirm" type="password" name="password_confirmation" required autocomplete="new-password">
        <button>Join workspace</button>
    </form>
    <a class="join-link" href="{{ route('login') }}">Back to login</a>
</section>
@endsection
