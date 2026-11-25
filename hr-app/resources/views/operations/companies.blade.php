@extends('operations.layout')
@section('title','Companies')
@section('panels')
<section><h2>Your companies</h2>
@foreach($companies as $item)
<div class="login-row"><strong>{{ $item->name }}</strong>
@if($item->id === $company->id)<span>Current company</span>
@else<form method="post" action="{{ route('companies.select',$item->id) }}">@csrf<button>Switch company</button></form>@endif
</div>
@endforeach
</section>
<section class="entry-panel"><h2>Add company</h2>
<form method="post" action="{{ route('companies.store') }}">@csrf<label>Company name<input name="name" maxlength="255" value="{{ old('name') }}" required></label><button>Create and switch</button></form>
</section>
@endsection
