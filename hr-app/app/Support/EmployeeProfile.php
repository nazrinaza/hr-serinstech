<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class EmployeeProfile
{
    public static function viewData(Request $request, int $employee): array
    {
        $tabs = config('employee-profile');
        $tab = $request->query('tab', 'personal');
        abort_unless(is_string($tab) && isset($tabs[$tab]), 404);
        $records = DB::table('employee_profile_records')->where('employee_id', $employee)->where('section', $tab)
            ->orderByDesc('sequence')->pluck('payload', 'sequence')->map(fn ($value) => json_decode(Crypt::decryptString($value), true, flags: JSON_THROW_ON_ERROR));
        $incomes = $tab === 'previous' ? DB::table('prior_incomes')->where('employee_id', $employee)->orderByDesc('month')->get() : [];

        return compact('tabs', 'tab', 'records', 'incomes');
    }

    public static function rules(array $fields): array
    {
        $rules = [];
        foreach ($fields as $key => [$label, $type]) {
            $rule = match ($type) {
                'email' => ['email', 'max:255'],
                'date' => ['date_format:Y-m-d'],
                'year' => ['integer', 'between:1900,2200'],
                'count' => ['integer', 'between:0,100'],
                'amount' => ['decimal:0,2', 'between:0,9999999999.99'],
                'percent' => ['numeric', 'between:0,100'],
                'currency' => ['regex:/^[A-Z]{3}$/'],
                default => str_contains($type, '/') ? [Rule::in(explode('/', $type))] : ['string', 'max:255'],
            };
            $rules[$key] = ['nullable', ...$rule];
        }
        if (isset($rules['commencement_to'])) {
            $rules['commencement_to'][] = 'after_or_equal:commencement_from';
        }

        return $rules;
    }
}
