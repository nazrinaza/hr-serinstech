@extends('operations.layout')
@section('title','Company posts')
@section('panels')
@if($owner)<section class="entry-panel"><h2>Publish announcement</h2><form method="post" action="{{ route('posts.store') }}">@csrf<label>Title<input name="title" maxlength="160" required></label><label>Message<textarea name="body" maxlength="8000" required></textarea></label><button>Publish to company</button></form></section>@endif
<section><h2>Announcements</h2>@forelse($posts as $post)<article><h3>{{ $post->title }}</h3><p class="multiline">{{ $post->body }}</p><small>{{ $post->created_at }}</small></article>@empty<p>No announcements yet.</p>@endforelse @include('leave-pagination',['items'=>$posts])</section>
@endsection
