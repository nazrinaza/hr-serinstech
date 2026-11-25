@php
    $groups = [
        'People' => [
            'icon' => 'M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2 M14 7a4 4 0 1 1-8 0 4 4 0 1 1 8 0 M17 4a4 4 0 0 1 0 7 M20 21v-2a4 4 0 0 0-3-4',
            'links' => $owner ? ['employees'=>'Employee directory','employees#add-employee'=>'Add employee','reviews'=>'Performance reviews'] : ['employees'=>'My profile','reviews'=>'Performance reviews'],
        ],
        'Time and leave' => [
            'icon' => 'M21 12a9 9 0 1 1-18 0 9 9 0 1 1 18 0 M12 7v5l3 2',
            'links' => ['schedule'=>'Shift schedule','attendance'=>'Time management'] + ($owner ? [] : ['leave'=>'Annual leave']) + (($reviewer ?? false) ? ['leave#hr-approvals'=>'HR approvals'] : []),
        ],
        'Payroll and claims' => [
            'icon' => 'M3 6h18v13H3z M3 10h18 M7 15h3',
            'links' => ($owner ? ['periods'=>'Pay periods','payroll.example'=>'Payroll example','incomes'=>'Previous incomes'] : ['pay.slips'=>'My payslips']) + ['claims'=>'Expense claims'],
        ],
        'Workspace' => [
            'icon' => 'M4 21V5l10-2v18 M14 9h6v12 M2 21h20 M7 8h3 M7 12h3 M7 16h3',
            'links' => ($owner ? ['companies'=>'Manage companies','company.settings'=>'Company settings','users'=>'User management','reports'=>'Reports'] : []) + ['posts'=>'Company posts','account'=>'My account'],
        ],
    ];
    $related = ['employees'=>'employees.*','periods'=>'pay.register','pay.slips'=>'pay.slip'];
@endphp
<a class="menu-home" href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif>
    <svg aria-hidden="true" viewBox="0 0 24 24"><path d="m3 10 9-7 9 7v11h-7v-7h-4v7H3z"/></svg>Dashboard
</a>
@foreach($groups as $label => $group)
    @php($expanded = collect(array_keys($group['links']))->contains(fn ($key) => request()->routeIs($key, $related[$key] ?? $key)))
    <details class="menu-group" @if($expanded) open @endif>
        <summary><svg aria-hidden="true" viewBox="0 0 24 24"><path d="{{ $group['icon'] }}"/></svg>{{ $label }}</summary>
        <div class="menu-children">
        @foreach($group['links'] as $key => $text)
            @php([$destination,$anchor] = array_pad(explode('#',$key,2),2,null))
            <a href="{{ route($destination) }}{{ $anchor ? '#'.$anchor : '' }}" @if(!$anchor && request()->routeIs($destination, $related[$destination] ?? $destination)) aria-current="page" @endif>{{ $text }}</a>
        @endforeach
        </div>
    </details>
@endforeach
