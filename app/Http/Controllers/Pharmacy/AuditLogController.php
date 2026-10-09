<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\PerPage;
use App\Support\Sort;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $action = (string) $request->string('action');

        $query = AuditLog::query()->select('audit_logs.*')->leftJoin('users', 'users.id', '=', 'audit_logs.user_id');
        $sort = Sort::apply($query, ['created_at' => 'audit_logs.created_at', 'user' => 'users.name', 'action' => 'audit_logs.action'], 'created_at', 'desc', 'audit_logs.id');

        return Inertia::render('audit/Index', [
            'sort' => $sort,
            'logs' => $query
                ->where('audit_logs.branch_id', $request->user()?->branch_id)
                ->when($action, fn ($q) => $q->where('audit_logs.action', 'like', "$action%"))
                ->with('user:id,name')
                ->paginate(PerPage::get())
                ->withQueryString(),
            'action' => $action,
            'actions' => AuditLog::query()->where('branch_id', $request->user()?->branch_id)->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
