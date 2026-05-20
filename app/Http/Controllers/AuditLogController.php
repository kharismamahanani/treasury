<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ExportLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $query = ActivityLog::with('user:id,name')
            ->orderByDesc('created_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('model_type')) {
            $query->where('model_type', $request->model_type);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate(50)->withQueryString();

        $users = User::orderBy('name')->get(['id', 'name']);

        $modelTypes = ActivityLog::select('model_type')
            ->distinct()
            ->orderBy('model_type')
            ->pluck('model_type');

        // ── Export log tab ──────────────────────────────────────────────────
        $exportQuery = ExportLog::with('user:id,name')->orderByDesc('created_at');

        if ($request->filled('export_user_id')) {
            $exportQuery->where('user_id', $request->export_user_id);
        }
        if ($request->filled('export_type_filter')) {
            $exportQuery->where('export_type', $request->export_type_filter);
        }
        if ($request->filled('export_date_from')) {
            $exportQuery->whereDate('created_at', '>=', $request->export_date_from);
        }
        if ($request->filled('export_date_to')) {
            $exportQuery->whereDate('created_at', '<=', $request->export_date_to);
        }

        $exportLogs = $exportQuery->paginate(50, ['*'], 'export_page')->withQueryString();

        $exportTypes = ExportLog::select('export_type')
            ->distinct()
            ->orderBy('export_type')
            ->pluck('export_type');

        $activeTab = $request->get('tab', 'activity');

        return view('admin.audit-log', compact(
            'logs', 'users', 'modelTypes',
            'exportLogs', 'exportTypes', 'activeTab'
        ));
    }
}
