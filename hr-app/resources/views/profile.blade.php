@extends('workspace')
@section('title', 'My profile')
@section('menu')
@include('workspace-menu', ['owner' => false, 'reviewer' => $canReview])
@endsection
@section('page-class', 'directory-page')
@section('body')
<div class="directory-title"><p>{{ $profile->company_name }}</p><h1>My <span>profile</span></h1></div>
<p>You can update your personal, contact and emergency contact details here. For changes to your employment or payroll records, contact your workspace administrator.</p>
@include('employee-profile-tabs', ['selfService' => true, 'editable' => $tabs[$tab]['employee_editable'] ?? false])
@endsection
