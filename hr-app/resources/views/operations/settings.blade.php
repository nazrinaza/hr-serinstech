@extends('operations.layout')
@section('title','Company settings')
@section('panels')
<section><h2>Company details</h2><form method="post" action="{{ route('company.save') }}">@csrf @method('PATCH')<label>Company name<input name="name" value="{{ old('name',$company->name) }}" required maxlength="255"></label><button>Save company details</button></form></section>
@endsection
