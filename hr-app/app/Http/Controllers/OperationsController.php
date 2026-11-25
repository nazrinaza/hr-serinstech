<?php

namespace App\Http\Controllers;

use App\Support\WorkspaceAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationsController extends Controller
{
    public function dashboard(Request $request): View
    {
        $access = WorkspaceAccess::for($request);
        $posts = DB::table('company_posts')->where('company_id', $access->company->id)->latest('id')->limit(3)->get();
        $pendingLeave = $access->records('leave_requests')->where('r.status', 'pending')->count();
        $pendingClaims = $access->records('expense_claims')->where('r.status', 'pending')->count();

        if (! $access->employee) {
            $pendingTimesheets = $access->records('attendance_entries')->where('r.status', 'pending')->count();
            $headcount = $access->roster()->count();
            $period = DB::table('pay_periods')->where('company_id', $access->company->id)
                ->orderByRaw('CASE WHEN released_at IS NULL THEN 0 ELSE 1 END')
                ->latest('starts_on')->first();

            return view('operations.admin-dashboard', [...$access->navigation(), ...compact(
                'posts', 'pendingLeave', 'pendingClaims', 'pendingTimesheets', 'headcount', 'period'
            )]);
        }

        return view('operations.dashboard', [...$access->navigation(), ...compact('posts', 'pendingLeave', 'pendingClaims')]);
    }

    public function posts(Request $request): View
    {
        $access = WorkspaceAccess::for($request);
        $posts = DB::table('company_posts')->where('company_id', $access->company->id)->latest('id')->simplePaginate(15);

        return view('operations.posts', [...$access->navigation(), 'posts' => $posts]);
    }

    public function publish(Request $request): RedirectResponse
    {
        $access = WorkspaceAccess::for($request);
        $access->owner();
        $data = $request->validate(['title' => 'required|string|max:160', 'body' => 'required|string|max:8000']);
        DB::table('company_posts')->insert([...$data, 'company_id' => $access->company->id, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', 'Announcement published.');
    }

    public function claims(Request $request): View
    {
        $access = WorkspaceAccess::for($request);
        $claims = $access->records('expense_claims')->select('r.*', 'e.name')->latest('r.id')->simplePaginate(20);
        $queue = $access->reviewsDepartment('claims') ? $access->reviewQueue('claims')->orderBy('r.id')->simplePaginate(20, ['*'], 'queue') : null;

        return view('operations.claims', [...$access->navigation(), ...compact('claims', 'queue')]);
    }

    public function submitClaim(Request $request): RedirectResponse
    {
        $employee = WorkspaceAccess::for($request)->staff();
        $data = $request->validate(['spent_on' => 'required|date_format:Y-m-d|before_or_equal:today', 'description' => 'required|string|max:255',
            'amount' => 'required|numeric|decimal:0,2|min:0.01|max:9999999999.99', 'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120']);
        $path = $request->file('receipt')?->store('receipts', 'local');
        abort_if($path === false, 503);
        try {
            DB::table('expense_claims')->insert([...$data, 'receipt' => $path, 'employee_id' => $employee->id, 'created_at' => now(), 'updated_at' => now()]);
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return back()->with('status', 'Claim submitted.');
    }

    public function receipt(Request $request, int $claim): StreamedResponse
    {
        $access = WorkspaceAccess::for($request);
        $record = $access->records('expense_claims')->where('r.id', $claim)->select('r.receipt')->first();
        if (! $record) {
            abort_unless($access->reviewsDepartment('claims'), 404);
            $record = $access->reviewQueue('claims')->where('r.id', $claim)->firstOrFail();
        }
        abort_unless($record->receipt, 404);

        return Storage::disk('local')->download($record->receipt);
    }

    public function attendance(Request $request): View
    {
        $access = WorkspaceAccess::for($request);
        $entries = $access->records('attendance_entries')->select('r.*', 'e.name')->latest('r.id')->simplePaginate(25);
        $open = $access->employee ? DB::table('attendance_entries')->where('employee_id', $access->employee->id)->whereNull('ended_at')->value('id') : null;
        $queue = $access->reviewsDepartment('attendance') ? $access->reviewQueue('attendance')->orderBy('r.id')->simplePaginate(20, ['*'], 'queue') : null;

        $filters = $request->validate(['week' => 'nullable|date_format:Y-m-d', 'search' => 'nullable|string|max:100']);
        $week = CarbonImmutable::parse($filters['week'] ?? 'today', 'Asia/Kuala_Lumpur')->startOfWeek(CarbonImmutable::MONDAY);
        $roster = $access->roster();
        if ($access->employee) {
            $roster->where('id', $access->employee->id);
        }
        $search = trim($filters['search'] ?? '');
        if ($search !== '') {
            $roster->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%");
            });
        }
        $people = $roster->orderBy('name')->orderBy('id')->simplePaginate(15, ['id', 'code', 'name'], 'roster')->withQueryString();
        $shifts = DB::table('work_shifts')->whereIn('employee_id', $people->pluck('id'))
            ->where('starts_at', '<', $week->addWeek()->toDateTimeString())
            ->where('ends_at', '>', $week->toDateTimeString())
            ->orderBy('starts_at')->get()->groupBy('employee_id');
        $days = array_map(fn ($offset) => $week->addDays($offset), range(0, 6));

        return view('operations.attendance', [...$access->navigation(), ...compact('entries', 'open', 'queue', 'people', 'shifts', 'week', 'days', 'search')]);
    }

    public function clock(Request $request): RedirectResponse
    {
        $staff = WorkspaceAccess::for($request)->staff();
        $data = $request->validate(['action' => 'required|in:in,out', 'entry_id' => 'required_if:action,out|nullable|integer']);
        DB::transaction(function () use ($staff, $data) {
            DB::table('employees')->where('id', $staff->id)->lockForUpdate()->firstOrFail();
            $query = DB::table('attendance_entries')->where('employee_id', $staff->id)->whereNull('ended_at');
            if ($data['action'] === 'out') {
                $query->where('id', $data['entry_id'])->update(['ended_at' => now('UTC'), 'status' => 'pending']);
            } elseif (! $query->exists()) {
                DB::table('attendance_entries')->insert(['employee_id' => $staff->id, 'started_at' => now('UTC')]);
            }
        });

        return redirect()->route('attendance');
    }

    public function periods(Request $request): View
    {
        $access = WorkspaceAccess::for($request);
        $access->owner();
        $periods = DB::table('pay_periods')->where('company_id', $access->company->id)->latest('starts_on')->simplePaginate(20);

        return view('operations.periods', [...$access->navigation(), 'periods' => $periods]);
    }

    public function decision(Request $request, string $module, int $record): RedirectResponse
    {
        $data = $request->validate(['status' => 'required|in:approved,declined']);
        DB::transaction(function () use ($request, $module, $record, $data) {
            DB::table('employees')->where('user_id', $request->user()->id)->lockForUpdate()->firstOrFail();
            $access = WorkspaceAccess::for($request);
            $access->reviewQueue($module)->where('r.id', $record)->lockForUpdate()->firstOrFail();
            DB::table($module === 'claims' ? 'expense_claims' : 'attendance_entries')->where('id', $record)
                ->update(['status' => $data['status'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        }, 3);

        return back()->with('status', 'Decision recorded.');
    }

    public function createPeriod(Request $request): RedirectResponse
    {
        $access = WorkspaceAccess::for($request);
        $access->owner();
        $data = $request->validate(['starts_on' => 'required|date_format:Y-m-d', 'ends_on' => 'required|date_format:Y-m-d|after_or_equal:starts_on']);
        DB::transaction(function () use ($access, $data) {
            DB::table('companies')->where('id', $access->company->id)->lockForUpdate()->firstOrFail();
            if (DB::table('pay_periods')->where('company_id', $access->company->id)->where('starts_on', '<=', $data['ends_on'])->where('ends_on', '>=', $data['starts_on'])->exists()) {
                throw ValidationException::withMessages(['starts_on' => 'This period overlaps an existing period.']);
            }
            DB::table('pay_periods')->insert([...$data, 'company_id' => $access->company->id, 'created_at' => now(), 'updated_at' => now()]);
        });

        return back()->with('status', 'Pay period created.');
    }
}
