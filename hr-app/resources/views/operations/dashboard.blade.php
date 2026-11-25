@extends('operations.layout')
@section('title','Dashboard')
@section('panels')
<section><h2>Pending work</h2><p>{{ $pendingLeave }} pending leave requests · {{ $pendingClaims }} pending expense claims</p></section>
<section><h2>Latest company posts</h2>@forelse($posts as $post)<h3>{{ $post->title }}</h3><p>{{ $post->body }}</p>@empty<p>No announcements yet.</p>@endforelse<a href="{{ route('posts') }}">All posts</a></section>
@endsection
