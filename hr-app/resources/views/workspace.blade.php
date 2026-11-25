<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>@yield('title') · HR SeriNSTech</title><link rel="stylesheet" href="{{ asset('workspace.css') }}"></head>
<body class="@yield('page-class')">@section('header')<header>
<div class="brand-group">
    <span class="brand-badge">HR</span>
    <img class="brand" src="{{ asset(auth()->check() ? 'images/serinstech-light.png' : 'images/serinstech-header.png') }}" width="903" height="323" alt="SeriNSTech">
</div>
@auth
@php($headerAccess = \App\Support\WorkspaceAccess::for(request()))
<div class="header-actions">
@if(!$headerAccess->employee)<a class="company-chip" href="{{ route('companies') }}" aria-label="Manage companies, current company {{ $headerAccess->company->name }}">{{ $headerAccess->company->name }} <span aria-hidden="true">⌄</span></a>
@else<span class="company-chip">{{ $headerAccess->company->name }}</span>@endif
<form method="post" action="{{ route('logout') }}">@csrf<button class="quiet-action">Sign out</button></form></div>@endauth</header>@show
@auth
<div class="workspace-shell">
<aside class="workspace-sidebar">
    <nav class="desktop-menu" aria-label="Workspace navigation">@yield('menu')</nav>
    <details class="mobile-menu"><summary>Workspace menu</summary><nav aria-label="Workspace navigation">@yield('menu')</nav></details>
</aside>
@endauth
<main>@if($errors->any())<div role="alert"><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@yield('body')</main>@auth</div>@endauth</body></html>
