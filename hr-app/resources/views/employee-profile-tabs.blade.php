<nav class="profile-tabs" aria-label="Employee profile sections">
@foreach($tabs as $key => $section)
    <a href="{{ empty($selfService) ? route('employees.edit', [$profile->id, 'tab' => $key]) : route('employees', ['tab' => $key]) }}" @if($key === $tab) aria-current="page" @endif>{{ $section['label'] }}</a>
@endforeach
</nav>
<section class="profile-panel">
<h2>{{ $tabs[$tab]['label'] }}</h2>
@if($tab === 'personal')
    <p>{{ $profile->name }} · {{ $profile->code }} · {{ $profile->department ?: 'Department unassigned' }}</p>
@endif
@foreach($records as $sequence => $values)
    <details class="profile-record" @if($records->count() === 1 || (string) old('sequence') === (string) $sequence) open @endif>
        <summary>{{ collect($values)->first(fn($value) => filled($value)) ?? 'Profile details' }} @if($tabs[$tab]['multiple']) · Record {{ $sequence }} @endif</summary>
        @include('employee-profile-fields')
    </details>
@endforeach
@if($editable && ($tabs[$tab]['multiple'] || $records->isEmpty()))
    <details class="profile-record" @if($records->isEmpty() || old('sequence') === '0') open @endif>
        <summary>{{ $tabs[$tab]['multiple'] ? 'Add record' : 'Enter details' }}</summary>
        @include('employee-profile-fields', ['sequence' => 0, 'values' => []])
    </details>
@elseif(!$editable && $records->isEmpty())
    <p>No additional details recorded.</p>
@endif
@if($tab === 'previous' && count($incomes))
    <h3>Imported monthly income</h3>
    @foreach($incomes as $income)<p>{{ substr($income->month,0,7) }} · MYR {{ number_format($income->amount_cents / 100, 2) }}</p>@endforeach
@endif
</section>
