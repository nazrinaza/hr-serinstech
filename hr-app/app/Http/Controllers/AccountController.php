<?php

namespace App\Http\Controllers;

use App\Support\HrDepartment;
use App\Support\WorkspaceAccess;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function show(Request $request): View
    {
        $id = $request->user()->id;
        $owner = DB::table('companies')->where('owner_id', $id)->exists();
        $profile = DB::table('employees')->where('user_id', $id)->first();
        abort_unless($owner || $profile, 403);
        $reviewer = $profile && $profile->hr_approver && HrDepartment::matches($profile->department);

        return view('account', compact('owner', 'reviewer'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'current_password' => 'required|current_password',
            'password' => 'nullable|string|min:12|max:72|confirmed',
        ]);
        $user->fill(['name' => $data['name'], 'email' => $data['email']]);
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        if ($user->isDirty(['email', 'password'])) {
            $user->login_version++;
        }
        try {
            $user->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'This email address is already in use.']);
        }
        $request->session()->regenerate();
        $request->session()->put('login_version', $user->login_version);

        return redirect()->route('account')->with('status', 'Account saved.');
    }

    public function users(Request $request): View
    {
        $company = WorkspaceAccess::ownedCompany($request);
        $employees = DB::table('employees as e')->leftJoin('users as u', 'u.id', '=', 'e.user_id')
            ->where('e.company_id', $company->id)->orderBy('e.name')->orderBy('e.id')
            ->simplePaginate(25, ['e.id', 'e.name', 'e.user_id', 'e.invite_expires_at', 'u.email', 'u.login_enabled']);

        return view('users', compact('employees', 'company'));
    }

    public function access(Request $request, int $employee): RedirectResponse
    {
        $company = WorkspaceAccess::ownedCompany($request);
        $data = $request->validate(['enabled' => 'required|boolean']);
        $account = DB::table('employees')->where('company_id', $company->id)->where('id', $employee)->firstOrFail();
        abort_unless($account->user_id && $account->user_id !== $request->user()->id, 403);
        $enabled = (bool) $data['enabled'];
        DB::table('users')->where('id', $account->user_id)->where('login_enabled', ! $enabled)
            ->increment('login_version', 1, ['login_enabled' => $enabled]);

        return redirect()->route('users')->with('status', $enabled ? 'Login enabled.' : 'Login suspended.');
    }
}
