<?php

namespace App\Http\Controllers;

use App\Support\WorkspaceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PayrollInputController extends Controller
{
    public function schedule(Request $request): View
    {
        $access = WorkspaceAccess::for($request);
        $shifts = $access->records('work_shifts')->select('r.*', 'e.name')->orderByDesc('starts_at')->simplePaginate(25);

        return view('operations.schedule', [...$access->navigation(), 'shifts' => $shifts, 'staff' => $access->employee ? [] : $access->roster()->orderBy('name')->get(['id', 'code', 'name'])]);
    }

    public function assignShift(Request $request): RedirectResponse
    {
        $access = WorkspaceAccess::for($request);
        $access->owner();
        $data = $request->validate(['employee_id' => 'required|integer', 'starts_at' => 'required|date_format:Y-m-d\TH:i', 'ends_at' => 'required|date_format:Y-m-d\TH:i|after:starts_at']);
        $data['starts_at'] = str_replace('T', ' ', $data['starts_at']).':00';
        $data['ends_at'] = str_replace('T', ' ', $data['ends_at']).':00';
        DB::transaction(function () use ($access, $data) {
            $access->roster()->where('id', $data['employee_id'])->lockForUpdate()->firstOrFail();
            if (DB::table('work_shifts')->where('employee_id', $data['employee_id'])->where('starts_at', '<', $data['ends_at'])->where('ends_at', '>', $data['starts_at'])->exists()) {
                throw ValidationException::withMessages(['starts_at' => 'This employee already has a shift during these hours.']);
            }
            DB::table('work_shifts')->insert($data);
        });

        return back()->with('status', 'Shift scheduled.');
    }

    private static function cents(string $amount): int
    {
        [$whole,$fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    public function example(Request $request): View
    {
        $access = WorkspaceAccess::for($request);
        $access->owner();
        $values = ['base' => '3000.00', 'overtime' => '150.00', 'allowances' => '100.00', 'service_charge' => '50.00', 'deductions' => '200.00'];
        if ($request->isMethod('post')) {
            $values = $request->validate(array_fill_keys(array_keys($values), ['required', 'regex:/^\d{1,8}(\.\d{1,2})?$/']));
        }
        $parts = array_map(self::cents(...), $values);
        $gross = $parts['base'] + $parts['overtime'] + $parts['allowances'] + $parts['service_charge'];

        return view('operations.example', [...$access->navigation(), 'values' => $values, 'gross' => $gross, 'net' => $gross - $parts['deductions']]);
    }

    public function incomes(Request $request): View
    {
        $access = WorkspaceAccess::for($request);
        $access->owner();
        $rows = $access->records('prior_incomes')->select('r.*', 'e.code', 'e.name')->latest('month')->orderBy('e.code')->simplePaginate(25);

        return view('operations.incomes', [...$access->navigation(), 'rows' => $rows]);
    }

    public function import(Request $request): RedirectResponse
    {
        $access = WorkspaceAccess::for($request);
        $access->owner();
        $request->validate(['csv' => 'required|file|max:1024']);
        $file = fopen($request->file('csv')->getRealPath(), 'rb');
        try {
            $header = fgetcsv($file, 0, ',', '"', '');
            if ($header !== ['employee_code', 'month', 'amount_myr']) {
                throw ValidationException::withMessages(['csv' => 'Use employee_code,month,amount_myr as the header.']);
            }
            $rows = [];
            $roster = $access->roster()->pluck('id', 'code');
            for ($line = 2; ($row = fgetcsv($file, 0, ',', '"', '')) !== false; $line++) {
                if ($row === [null]) {
                    continue;
                }
                if (count($row) !== 3 || ! $roster->has($row[0]) || Validator::make(['month' => $row[1]], ['month' => 'date_format:Y-m'])->fails() || ! preg_match('/^\d{1,8}(\.\d{1,2})?$/D', $row[2])) {
                    throw ValidationException::withMessages(['csv' => "Invalid employee code, month or amount on row $line."]);
                }
                $key = $roster[$row[0]].':'.$row[1];
                if (isset($rows[$key])) {
                    throw ValidationException::withMessages(['csv' => "Duplicate employee and month on row $line."]);
                }
                $rows[$key] = ['employee_id' => $roster[$row[0]], 'month' => $row[1].'-01', 'amount_cents' => self::cents($row[2]), 'created_at' => now(), 'updated_at' => now()];
            }
        } finally {
            fclose($file);
        }
        if (! $rows) {
            throw ValidationException::withMessages(['csv' => 'Include at least one income row.']);
        }
        DB::transaction(function () use ($rows) {
            foreach (array_chunk(array_values($rows), 100) as $chunk) {
                DB::table('prior_incomes')->upsert($chunk, ['employee_id', 'month'], ['amount_cents', 'updated_at']);
            }
        });

        return back()->with('status', count($rows).' income records imported.');
    }
}
