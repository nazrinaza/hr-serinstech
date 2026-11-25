@if($editable)
<form method="post" action="{{ route('employees.section', [$profile->id, $tab]) }}">
    @csrf @method('PATCH')
    <input type="hidden" name="sequence" value="{{ $sequence }}">
    <div class="profile-inputs">
    @foreach($tabs[$tab]['fields'] as $key => [$label, $type])
        @php($value = (string)old('sequence') === (string)$sequence ? old($key) : ($values[$key] ?? ''))
        <label>{{ $label }}
        @if(str_contains($type,'/'))
            <select name="{{ $key }}"><option value="">Select</option>
            @foreach(explode('/',$type) as $option)<option @selected($value === $option)>{{ $option }}</option>@endforeach</select>
        @elseif(in_array($type,['year','count','amount','percent']))
            <input type="number" name="{{ $key }}" value="{{ $value }}" step="{{ in_array($type,['amount','percent']) ? '0.01' : '1' }}" min="{{ $type === 'year' ? 1900 : 0 }}">
        @else
            <input type="{{ in_array($type,['email','tel','date']) ? $type : 'text' }}" name="{{ $key }}" value="{{ $value }}" maxlength="{{ $type === 'currency' ? 3 : 255 }}" @if($type === 'currency') placeholder="MYR" pattern="[A-Z]{3}" @endif>
        @endif
        </label>
    @endforeach
    </div>
    <button>Save {{ $tabs[$tab]['label'] }}</button>
</form>
@else
<dl class="profile-inputs">@foreach($tabs[$tab]['fields'] as $key => [$label,$type])
    <div><dt>{{ $label }}</dt><dd>{{ $values[$key] ?? '—' }}</dd></div>
@endforeach</dl>
@endif
