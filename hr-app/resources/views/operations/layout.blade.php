@extends('workspace')
@section('page-class','directory-page')
@section('menu')
@include('workspace-menu',compact('owner','reviewer'))
@endsection
@section('body')
<div class="directory-title"><p>{{ $company->name }}</p><h1>@yield('title')</h1></div>
<div class="operations-panels">@yield('panels')</div>
@endsection
