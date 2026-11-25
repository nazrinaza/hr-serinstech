<?php

namespace App\Http\Controllers;

use App\Support\HrDepartment;
use App\Support\WorkspaceAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LeaveController extends Controller
{
    private function canReview(object $employee): bool
    {
        return $employee->hr_approver && HrDepartment::matches($employee->department);
    }

    public function settings(Request $request, int $employee): RedirectResponse
    {
        $company = WorkspaceAccess::ownedCompany($request);
        $data = $request->validate(['opening_days' => 'required|integer|min:0|max:366', 'hr_approver' => 'nullable|boolean']);
        $grant = $request->boolean('hr_approver');
        $query = DB::table('employees')->where('company_id', $company->id)->where('id', $employee);
        DB::transaction(function () use ($query, $data, $grant) {
            $current = $query->lockForUpdate()->firstOrFail();
            if ($grant && ! HrDepartment::matches($current->department)) {
                throw ValidationException::withMessages(['hr_approver' => 'Assign the employee to Human Resources or HR before granting approval access.']);
            }
            $remaining = (int) $data['opening_days'] - ($current->leave_allocation - $current->leave_balance);
            if ($remaining < 0) {
                throw ValidationException::withMessages(['opening_days' => 'The allocation must cover leave already approved.']);
            }
            $query->update(['leave_allocation' => $data['opening_days'], 'leave_balance' => $remaining, 'hr_approver' => $grant]);
        });

        return back()->with('status', 'Leave settings saved.');
    }

    public function index(Request $request): View
    {
        $employee = DB::table('employees')->where('user_id', $request->user()->id)->first();
        abort_unless($employee, 403);
        $history = DB::table('leave_requests')->where('employee_id', $employee->id)->latest('id')->simplePaginate(20, ['*'], 'history');
        $queue = null;
        if ($this->canReview($employee)) {
            $queue = DB::table('leave_requests as l')->join('employees as e', 'e.id', '=', 'l.employee_id')
                ->where('e.company_id', $employee->company_id)->where('e.id', '!=', $employee->id)
                ->where('l.status', 'pending')->orderBy('l.id')->simplePaginate(20, ['l.*', 'e.name'], 'queue');
        }

        return view('leave', compact('employee', 'history', 'queue'));
    }

    public function rollover(Request $request, int $employee): RedirectResponse
    {
        $access = WorkspaceAccess::for($request);
        $access->owner();
        $data = $request->validate(['year' => 'required|integer|min:2000|max:'.(now('Asia/Kuala_Lumpur')->year - 1),
            'carry_limit' => 'required|integer|min:0|max:366', 'allocation' => 'required|integer|min:0|max:366']);
        DB::transaction(function () use ($access, $employee, $data, $request) {
            $person = $access->roster()->where('id', $employee)->lockForUpdate()->firstOrFail();
            $year = (int) $data['year'];
            $last = DB::table('leave_rollovers')->where('employee_id', $employee)->max('closing_year');
            if ($last !== null && $year !== (int) $last + 1) {
                throw ValidationException::withMessages(['year' => 'Close each leave year once, in sequence.']);
            }
            $end = $year.'-12-31';
            $requests = DB::table('leave_requests')->where('employee_id', $employee);
            if ((clone $requests)->where('start_date', '<=', $end)->where('status', 'pending')->exists() ||
                (clone $requests)->where('status', 'approved')->where('start_date', '<=', $end)->where('end_date', '>', $end)->exists()) {
                throw ValidationException::withMessages(['year' => 'Resolve pending requests or reconcile an approved request spanning year-end before closing.']);
            }
            $future = (int) (clone $requests)->where('status', 'approved')->where('start_date', '>', $end)->sum('days');
            $closing = $person->leave_balance + $future;
            $carry = min($closing, (int) $data['carry_limit']);
            $balance = $data['allocation'] + $carry - $future;
            if ($balance < 0) {
                throw ValidationException::withMessages(['allocation' => 'The new allocation must cover already approved future leave.']);
            }
            DB::table('leave_rollovers')->insert(['employee_id' => $employee, 'closing_year' => $year, 'closing_days' => $closing,
                'carried_days' => $carry, 'new_allocation' => $data['allocation'], 'processed_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('employees')->where('id', $employee)->update(['leave_allocation' => $data['allocation'], 'leave_balance' => $balance]);
        }, 3);

        return back()->with('status', 'Leave year closed.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['start_date' => 'required|date_format:Y-m-d', 'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date']);
        $start = CarbonImmutable::parse($data['start_date']);
        $end = CarbonImmutable::parse($data['end_date']);
        if ($end->gt($start->addYear())) {
            throw ValidationException::withMessages(['end_date' => 'Select a period of one year or less.']);
        }
        $days = (int) $start->diffInWeekdays($end->addDay());
        DB::transaction(function () use ($data, $days, $request) {
            $employee = DB::table('employees')->where('user_id', $request->user()->id)->lockForUpdate()->first();
            abort_unless($employee, 403);
            $closedYear = DB::table('leave_rollovers')->where('employee_id', $employee->id)->max('closing_year');
            if ($closedYear && $data['start_date'] <= $closedYear.'-12-31') {
                throw ValidationException::withMessages(['start_date' => 'This leave year is already closed.']);
            }
            if ($days < 1 || $days > $employee->leave_balance) {
                throw ValidationException::withMessages(['start_date' => 'Select at least one working day within your available balance.']);
            }
            $overlap = DB::table('leave_requests')->where('employee_id', $employee->id)->whereIn('status', ['pending', 'approved'])
                ->where('start_date', '<=', $data['end_date'])->where('end_date', '>=', $data['start_date'])->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['start_date' => 'These dates overlap an existing request.']);
            }
            DB::table('leave_requests')->insert([...$data, 'employee_id' => $employee->id, 'days' => $days, 'created_at' => now(), 'updated_at' => now()]);
        });

        return back()->with('status', 'Leave submitted for HR review.');
    }

    public function withdraw(Request $request, int $leave): RedirectResponse
    {
        DB::transaction(function () use ($request, $leave) {
            $query = DB::table('leave_requests')->where('id', $leave)
                ->whereIn('employee_id', DB::table('employees')->select('id')->where('user_id', $request->user()->id));
            $record = $query->lockForUpdate()->firstOrFail();
            if ($record->status === 'withdrawn') {
                return;
            }
            if ($record->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'HR has already reviewed this request.']);
            }
            $query->update(['status' => 'withdrawn', 'updated_at' => now()]);
        }, 3);

        return redirect()->route('leave')->with('status', 'Leave request withdrawn.');
    }

    public function decide(Request $request, int $leave): RedirectResponse
    {
        $status = $request->validate(['status' => ['required', Rule::in(['approved', 'declined'])]])['status'];
        DB::transaction(function () use ($request, $leave, $status) {
            $reviewer = DB::table('employees')->where('user_id', $request->user()->id)->lockForUpdate()->first();
            abort_unless($reviewer && $this->canReview($reviewer), 403);
            $record = DB::table('leave_requests')->where('id', $leave)->whereIn('employee_id', DB::table('employees')->select('id')->where('company_id', $reviewer->company_id))->lockForUpdate()->firstOrFail();
            abort_if($record->employee_id === $reviewer->id, 403);
            if ($record->status !== 'pending') {
                if ($record->status !== $status) {
                    throw ValidationException::withMessages(['status' => 'This request is already '.$record->status.'.']);
                }

                return;
            }
            if ($status === 'approved') {
                $updated = DB::table('employees')->where('id', $record->employee_id)->where('leave_balance', '>=', $record->days)->decrement('leave_balance', $record->days);
                if (! $updated) {
                    throw ValidationException::withMessages(['status' => 'The employee has insufficient leave remaining.']);
                }
            }
            DB::table('leave_requests')->where('id', $leave)->update(['status' => $status, 'reviewed_by' => $reviewer->user_id, 'reviewed_at' => now(), 'updated_at' => now()]);
        }, 3);

        return redirect()->route('leave')->with('status', 'Review complete.');
    }
}
