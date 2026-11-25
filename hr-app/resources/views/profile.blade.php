@extends('workspace')
@section('title', 'My profile')
@section('menu')
@include('workspace-menu', ['owner' => false, 'reviewer' => $canReview])
@endsection
@section('page-class', 'directory-page')
@section('body')
<div class="directory-title"><p>{{ $profile->company_name }}</p><h1>My <span>profile</span></h1></div>
@include('employee-profile-tabs', ['editable' => false])
@endsection
