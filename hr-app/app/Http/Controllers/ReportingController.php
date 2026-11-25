<?php

namespace App\Http\Controllers;

use App\Support\WorkspaceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportingController extends Controller
{
    public function settings(Request $request): View
    {
        $access = WorkspaceAccess::for($request);
        $access->owner();

        return view('operations.settings', $access->navigation());
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $access = WorkspaceAccess::for($request);
        $access->owner();
        $data = $request->validate(['name' => 'required|string|max:255']);
        DB::table('companies')->where('id', $access->company->id)->update($data);

        return back()->with('status', 'Company settings saved.');
    }

    public function index(Request $request): View
    {
        $access = WorkspaceAccess::for($request);
        $access->owner();

        return view('operations.reports', $access->navigation());
    }

    public function export(Request $request): StreamedResponse
    {
        $access = WorkspaceAccess::for($request);
        $access->owner();
        $type = $request->validate(['report' => 'required|in:employees,claims,attendance,incomes'])['report'];
        [$query,$headers] = match ($type) {
            'incomes' => [$access->records('prior_incomes')->select('e.code', 'r.month', 'r.amount_cents')->orderBy('r.month')->orderBy('e.code'), ['employee_code', 'month', 'amount_myr']],
            'employees' => [$access->roster()->select('code', 'name', 'department')->orderBy('code'), ['Employee code', 'Name', 'Department']],
            'claims' => [$access->records('expense_claims')->select('e.code', 'r.spent_on', 'r.description', 'r.amount', 'r.status')->orderBy('r.id'), ['Employee code', 'Date', 'Description', 'MYR', 'Status']],
            'attendance' => [$access->records('attendance_entries')->select('e.code', 'r.started_at', 'r.ended_at', 'r.status')->orderBy('r.id'), ['Employee code', 'Clock in UTC', 'Clock out UTC', 'Review status']],
        };

        return response()->streamDownload(function () use ($query, $headers, $type) {
            $file = fopen('php://output', 'wb');
            fputcsv($file, $headers, ',', '"', '');
            foreach ($query->cursor() as $row) {
                if ($type === 'incomes') {
                    $row->month = substr($row->month, 0, 7);
                    $row->amount_cents = number_format($row->amount_cents / 100, 2, '.', '');
                }
                $cells = array_map(static function ($cell) {
                    $text = (string) $cell;

                    return preg_match('/^\s*[=+@\x2d]|^[\t\r\n]/u', $text) ? "'".$text : $text;
                }, (array) $row);
                fputcsv($file, $cells, ',', '"', '');
            }
            fclose($file);
        }, $type.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
