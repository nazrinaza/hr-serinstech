<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\EmployeeProfile;
use App\Support\HrDepartment;
use App\Support\WorkspaceAccess;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:12|max:72|confirmed',
        ]);
        $user = DB::transaction(function () use ($data) {
            $user = User::create(collect($data)->only('email', 'password', 'name')->all());
            DB::table('companies')->insert(['name' => $data['company'], 'owner_id' => $user->id]);

            return $user;
        });
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('employees');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt([...$credentials, 'login_enabled' => true])) {
            throw ValidationException::withMessages(['email' => 'Check your email and password.']);
        }
        $request->session()->regenerate();

        $request->session()->put('login_version', $request->user()->login_version);

        return redirect()->intended(route('employees'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function company(Request $request): object
    {
        $company = WorkspaceAccess::ownedCompany($request);

        return $company;
    }

    public function directory(Request $request): View
    {
        $profile = DB::table('employees')->join('companies', 'companies.id', '=', 'employees.company_id')
            ->where('employees.user_id', $request->user()->id)
            ->select('employees.id', 'employees.name', 'employees.code', 'employees.department', 'employees.hr_approver', 'companies.name as company_name')->first();
        if ($profile) {
            return view('profile', [...EmployeeProfile::viewData($request, $profile->id), 'profile' => $profile, 'canReview' => $profile->hr_approver && HrDepartment::matches($profile->department)]);
        }
        $company = $this->company($request);
        $employees = DB::table('employees')->where('company_id', $company->id)
            ->orderBy('name')->orderBy('id')->simplePaginate(25);

        return view('directory', compact('company', 'employees'));
    }

    public function addEmployee(Request $request): RedirectResponse
    {
        $company = $this->company($request);
        $data = $this->employeeData($request);
        try {
            DB::table('employees')->insert([...$data, 'company_id' => $company->id]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'This employee code is already in use.']);
        }

        return redirect()->route('employees')->with('status', 'Employee added.');
    }

    private function employeeData(Request $request): array
    {
        return $request->validate([
            'code' => 'required|string|max:40',
            'name' => 'required|string|max:255',
            'department' => 'nullable|string|max:255',
        ]);
    }

    private function ownedEmployee(Request $request, int $employee): Builder
    {
        return DB::table('employees')->where('company_id', $this->company($request)->id)->where('id', $employee);
    }

    public function edit(Request $request, int $employee): View
    {
        $profile = $this->ownedEmployee($request, $employee)->firstOrFail();

        $rollovers = DB::table('leave_rollovers')->where('employee_id', $employee)->orderByDesc('closing_year')->get();

        return view('edit-employee', [...compact('profile', 'rollovers'), ...EmployeeProfile::viewData($request, $employee)]);
    }

    public function saveSection(Request $request, int $employee, string $section): RedirectResponse
    {
        $access = WorkspaceAccess::for($request);
        $definition = config('employee-profile')[$section] ?? null;
        abort_unless($definition, 404);
        $employeeQuery = $access->roster()->where('id', $employee);
        if ($access->employee) {
            abort_unless($definition['employee_editable'] ?? false, 403);
            $employeeQuery->where('user_id', $request->user()->id);
        }
        $employeeQuery->firstOrFail();
        $sequence = $request->validate(['sequence' => 'required|integer|min:0'])['sequence'];
        $data = $request->validate(EmployeeProfile::rules($definition['fields']));
        $hasValue = count(array_filter($data, fn ($value) => $value !== null)) > 0;
        if (! $hasValue && ! $sequence) {
            throw ValidationException::withMessages(['profile' => 'Enter at least one profile detail.']);
        }
        DB::transaction(function () use ($employeeQuery, $employee, $section, $definition, $sequence, $data) {
            $employeeQuery->lockForUpdate()->firstOrFail();
            $query = DB::table('employee_profile_records')->where('employee_id', $employee)->where('section', $section);
            if ($sequence) {
                $query->where('sequence', $sequence)->firstOrFail();
            } else {
                $sequence = $definition['multiple'] ? (int) $query->max('sequence') + 1 : 1;
            }
            DB::table('employee_profile_records')->updateOrInsert(
                ['employee_id' => $employee, 'section' => $section, 'sequence' => $sequence],
                ['payload' => Crypt::encryptString(json_encode($data, JSON_THROW_ON_ERROR))]
            );
        });

        $destination = $access->employee
            ? route('employees', ['tab' => $section])
            : route('employees.edit', [$employee, 'tab' => $section]);

        return redirect($destination)->with('status', 'Profile section saved.');
    }

    public function update(Request $request, int $employee): RedirectResponse
    {
        $query = $this->ownedEmployee($request, $employee);
        $data = $this->employeeData($request);
        try {
            DB::transaction(function () use ($query, $data) {
                $current = $query->lockForUpdate()->firstOrFail();
                $department = $data['department'] ?? (array_key_exists('department', $data) ? null : $current->department);
                if (! HrDepartment::matches($current->department) || ! HrDepartment::matches($department)) {
                    $data['hr_approver'] = false;
                }
                $query->update($data);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'This employee code is already in use.']);
        }

        return redirect()->route('employees')->with('status', 'Employee updated.');
    }

    public function invite(Request $request, int $employee): RedirectResponse
    {
        $query = $this->ownedEmployee($request, $employee);
        $query->firstOrFail();
        $code = Str::random(40);
        $updated = $query->whereNull('user_id')->update([
            'invite_hash' => hash('sha256', $code),
            'invite_expires_at' => now()->addDay(),
        ]);
        abort_unless($updated, 409);

        return redirect()->route('employees.edit', $employee)->with('invite_code', $code);
    }

    public function join(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'invite_code' => 'required|string|size:40',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:12|max:72|confirmed',
        ]);
        $user = DB::transaction(function () use ($data) {
            $query = DB::table('employees')->where('invite_hash', hash('sha256', $data['invite_code']))
                ->whereNull('user_id')->where('invite_expires_at', '>', now());
            $employee = $query->lockForUpdate()->first();
            if (! $employee) {
                throw ValidationException::withMessages(['invite_code' => 'Request a new invitation from your workspace owner.']);
            }
            $user = User::create(['name' => $employee->name, 'email' => $data['email'], 'password' => $data['password']]);
            $query->update(['user_id' => $user->id, 'invite_hash' => null, 'invite_expires_at' => null]);

            return $user;
        });
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('employees');
    }
}
