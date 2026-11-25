<?php

namespace App\Http\Controllers;

use App\Support\WorkspaceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function index(Request $request): View
    {
        $access = WorkspaceAccess::for($request);
        $access->owner();
        $companies = DB::table('companies')->where('owner_id', $request->user()->id)->orderBy('name')->orderBy('id')->get();

        return view('operations.companies', [...$access->navigation(), 'companies' => $companies]);
    }

    public function store(Request $request): RedirectResponse
    {
        WorkspaceAccess::ownedCompany($request);
        $data = $request->validate(['name' => 'required|string|max:255']);
        $company = DB::table('companies')->insertGetId([...$data, 'owner_id' => $request->user()->id]);

        return $this->activate($request, $company);
    }

    public function select(Request $request, int $company): RedirectResponse
    {
        DB::table('companies')->where('owner_id', $request->user()->id)->where('id', $company)->firstOrFail();

        return $this->activate($request, $company);
    }

    private function activate(Request $request, int $company): RedirectResponse
    {
        $request->session()->put('active_company_id', $company);
        $request->session()->regenerateToken();

        return redirect()->route('dashboard');
    }
}
