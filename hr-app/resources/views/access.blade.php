@extends('workspace')
@section('title', 'Create workspace')
@section('page-class', 'login-page')
@section('header', ' ')
@section('body')
<section class="login-card" aria-labelledby="register-title">
    @include('auth-brand')
    <h1 id="register-title">Create your<br><span>workspace</span></h1>
    <p>Already have an account? <a href="{{ route('login') }}">Log in</a></p>
    <form method="post" action="{{ route('workspace.store') }}">
        @csrf
        <label for="company">Company name</label>
        <input id="company" name="company" value="{{ old('company') }}" required maxlength="255" autocomplete="organization">
        <label for="name">Your name</label>
        <input id="name" name="name" value="{{ old('name') }}" required maxlength="255" autocomplete="name">
        <label for="email">Email address</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="username">
        <label for="password">Password (12 characters minimum)</label>
        <input id="password" type="password" name="password" required minlength="12" autocomplete="new-password">
        <label for="confirm">Confirm password</label>
        <input id="confirm" type="password" name="password_confirmation" required autocomplete="new-password">
        <button>Create workspace</button>
    </form>
</section>
@endsection
