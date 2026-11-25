<?php

namespace App\Http\Controllers;

use App\Support\WorkspaceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PayrollRegisterController extends Controller
{
    public const COMPONENTS = ['base' => 'Base salary', 'overtime' => 'Overtime', 'allowances' => 'Allowances', 'service_charge' => 'Service charge', 'deductions' => 'Deductions entered'];

    public function register(Request $request, int $period): View
    {
        $access = WorkspaceAccess::for($request);
        $access->owner();
        $period = DB::table('pay_periods')->where('company_id', $access->company->id)->where('id', $period)->firstOrFail();
        $lines = DB::table('payroll_lines')->where('period_id', $period->id)->orderBy('employee_code')->get();

        return view('operations.register', [...$access->navigation(), ...compact('period', 'lines'),
            'staff' => $access->roster()->orderBy('name')->get(), 'fields' => self::COMPONENTS]);
    }

    public function save(Request $request, int $period): RedirectResponse
    {
        $access = WorkspaceAccess::for($request);
        $access->owner();
        $data = $request->validate(['employee_id' => 'required|integer', ...array_fill_keys(array_keys(self::COMPONENTS), ['required', 'regex:/^\d{1,8}(\.\d{1,2})?$/D'])]);
        $amounts = [];
        foreach (self::COMPONENTS as $key => $label) {
            [$whole,$fraction] = array_pad(explode('.', (string) $data[$key], 2), 2, '');
            $amounts[$key.'_cents'] = (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
        }
        $amounts['gross_cents'] = array_sum($amounts) - $amounts['deductions_cents'];
        $amounts['net_cents'] = $amounts['gross_cents'] - $amounts['deductions_cents'];
        if ($amounts['net_cents'] < 0) {
            throw ValidationException::withMessages(['deductions' => 'Deductions exceed gross earnings.']);
        }
        DB::transaction(function () use ($access, $period, $data, $amounts) {
            $pay = DB::table('pay_periods')->where('company_id', $access->company->id)->where('id', $period)->lockForUpdate()->firstOrFail();
            abort_if($pay->released_at, 409, 'The register is locked.');
            $person = $access->roster()->where('id', $data['employee_id'])->firstOrFail();
            $values = [...$amounts, 'employee_name' => $person->name, 'employee_code' => $person->code, 'company_name' => $access->company->name, 'updated_at' => now()];
            DB::table('payroll_lines')->upsert([[...$values, 'period_id' => $period, 'employee_id' => $person->id, 'created_at' => now()]], ['period_id', 'employee_id'], array_keys($values));
        });

        return back()->with('status', 'Register entry saved.');
    }

    public function release(Request $request, int $period): RedirectResponse
    {
        $access = WorkspaceAccess::for($request);
        $access->owner();
        DB::transaction(function () use ($access, $period) {
            $query = DB::table('pay_periods')->where('company_id', $access->company->id)->where('id', $period);
            $pay = $query->lockForUpdate()->firstOrFail();
            if ($pay->released_at) {
                return;
            }
            if (! DB::table('payroll_lines')->where('period_id', $period)->exists()) {
                throw ValidationException::withMessages(['period' => 'Add an employee entry first.']);
            }
            $query->update(['released_at' => now()]);
        });

        return back()->with('status', 'Register locked. Employees can view their validation copies.');
    }

    public function slips(Request $request): View
    {
        $access = WorkspaceAccess::for($request);
        $employee = $access->staff();
        $slips = DB::table('payroll_lines as l')->join('pay_periods as p', 'p.id', '=', 'l.period_id')
            ->where('l.employee_id', $employee->id)->where('p.company_id', $employee->company_id)->whereNotNull('released_at')
            ->select('l.id', 'p.starts_on', 'p.ends_on')->orderByDesc('p.starts_on')->simplePaginate(20);

        return view('operations.slips', [...$access->navigation(), 'slips' => $slips]);
    }

    public function slip(Request $request, int $line): View
    {
        $access = WorkspaceAccess::for($request);
        $query = DB::table('payroll_lines as l')->join('pay_periods as p', 'p.id', '=', 'l.period_id')
            ->where('p.company_id', $access->company->id)->where('l.id', $line);
        if ($access->employee) {
            $query->where('l.employee_id', $access->employee->id)->whereNotNull('p.released_at');
        }
        $row = $query->select('l.*', 'p.starts_on', 'p.ends_on', 'p.released_at')->firstOrFail();

        return view('operations.slip',[...$access->navigation(), 'row' => $row, 'fields' => self::COMPONENTS]);
    }
}
