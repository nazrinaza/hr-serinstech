@extends('workspace')
@section('title', 'Log in')
@section('page-class', 'login-page')
@section('header', ' ')
@section('body')
<section class="login-card" aria-labelledby="login-title">
    @include('auth-brand')
    <h1 id="login-title">Log in to<br>your <span>account</span></h1>
    <p>Don’t have an account? <a href="{{ route('register') }}">Sign up</a></p>
    <form method="post" action="{{ route('session.store') }}">
        @csrf
        <label for="email">Email address</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required>
        <label for="password">Password</label>
        <input id="password" type="password" name="password" autocomplete="current-password" required>
        <button type="submit">Log in</button>
    </form>
    <a class="join-link" href="{{ route('join') }}">Use an employee invitation</a>
</section>
@endsection
