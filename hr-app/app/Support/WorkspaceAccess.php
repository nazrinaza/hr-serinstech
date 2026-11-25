<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class WorkspaceAccess
{
    public function __construct(public readonly object $company, public readonly ?object $employee) {}

    public static function for(Request $request): self
    {
        $id = $request->user()->id;
        $owned = DB::table('companies')->where('owner_id', $id);
        $selected = $request->session()->get('active_company_id');
        $company = $selected === null ? $owned->orderBy('id')->first() : $owned->where('id', $selected)->firstOrFail();
        if ($company) {
            return new self($company, null);
        }
        $employee = DB::table('employees')->where('user_id', $id)->first();
        abort_unless($employee, 403);

        return new self(DB::table('companies')->where('id', $employee->company_id)->firstOrFail(), $employee);
    }

    public static function ownedCompany(Request $request): object
    {
        $access = self::for($request);
        $access->owner();

        return $access->company;
    }

    public function owner(): void
    {
        abort_if($this->employee, 403);
    }

    public function staff(): object
    {
        abort_unless($this->employee, 403);

        return $this->employee;
    }

    public function roster(): Builder
    {
        return DB::table('employees')->where('company_id', $this->company->id);
    }

    public function records(string $table): Builder
    {
        $query = DB::table("$table as r")->join('employees as e', 'e.id', '=', 'r.employee_id')
            ->where('e.company_id', $this->company->id);
        if ($this->employee) {
            $query->where('e.id', $this->employee->id);
        }

        return $query;
    }

    public function reviewsDepartment(string $module): bool
    {
        if (! $this->employee) {
            return false;
        }
        $department = strtolower(trim($this->employee->department ?? ''));

        return match ($module) {
            'attendance' => HrDepartment::matches($department),
            'claims' => in_array($department, ['account', 'accounts', 'account department', 'accounts department', 'accounting', 'accounting department'], true),
        };
    }

    public function reviewQueue(string $module): Builder
    {
        abort_unless($this->reviewsDepartment($module), 403);
        $table = $module === 'claims' ? 'expense_claims' : 'attendance_entries';

        return DB::table("$table as r")->join('employees as e', 'e.id', '=', 'r.employee_id')
            ->where('e.company_id', $this->company->id)->where('e.id', '!=', $this->employee->id)
            ->where('r.status', 'pending')->select('r.*', 'e.name');
    }

    public function navigation(): array
    {
        return ['company' => $this->company, 'owner' => ! $this->employee,
            'reviewer' => $this->employee && $this->employee->hr_approver && HrDepartment::matches($this->employee->department)];
    }
}
