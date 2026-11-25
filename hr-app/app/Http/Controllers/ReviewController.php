<?php

namespace App\Http\Controllers;

use App\Support\WorkspaceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        $access = WorkspaceAccess::for($request);
        $reviews = $access->records('appraisals')->join('appraisal_templates as t', 't.id', '=', 'r.template_id')
            ->select('r.*', 'e.name', 't.title', 't.prompt')->latest('r.id')->simplePaginate(20);
        $templates = $access->employee ? [] : DB::table('appraisal_templates')->where('company_id', $access->company->id)->orderBy('title')->get();

        return view('operations.reviews', [...$access->navigation(), ...compact('reviews', 'templates'),
            'staff' => $access->employee ? [] : $access->roster()->orderBy('name')->get(['id', 'name', 'code'])]);
    }

    public function template(Request $request): RedirectResponse
    {
        $access = WorkspaceAccess::for($request);
        $access->owner();
        $data = $request->validate(['title' => 'required|string|max:160', 'prompt' => 'required|string|max:4000']);
        DB::table('appraisal_templates')->insert([...$data, 'company_id' => $access->company->id, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', 'Template created.');
    }

    public function assign(Request $request): RedirectResponse
    {
        $access = WorkspaceAccess::for($request);
        $access->owner();
        $data = $request->validate(['employee_id' => 'required|integer', 'template_id' => 'required|integer', 'due_on' => 'required|date_format:Y-m-d']);
        $access->roster()->where('id', $data['employee_id'])->firstOrFail();
        DB::table('appraisal_templates')->where('company_id', $access->company->id)->where('id', $data['template_id'])->firstOrFail();
        DB::table('appraisals')->insert([...$data, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', 'Review assigned.');
    }

    public function respond(Request $request, int $review): RedirectResponse
    {
        $access = WorkspaceAccess::for($request);
        $record = $access->records('appraisals')->where('r.id', $review)->select('r.*')->firstOrFail();
        $field = $access->employee ? 'response' : 'feedback';
        $data = $request->validate([$field => 'required|string|max:8000']);
        $query = DB::table('appraisals')->where('id', $record->id)->whereNull($field);
        if (! $access->employee) {
            $query->whereNotNull('response');
        }
        abort_unless($query->update([...$data, 'updated_at' => now()]), 409);

        return back()->with('status', 'Review saved.');
    }
}
