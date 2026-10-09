<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $action = (string) $request->string('action');

        return Inertia::render('audit/Index', [
            'logs' => AuditLog::query()
                ->where('branch_id', $request->user()?->branch_id)
                ->when($action, fn ($q) => $q->where('action', 'like', "$action%"))
                ->with('user:id,name')
                ->latest('id')
                ->paginate(50)
                ->withQueryString(),
            'action' => $action,
            'actions' => AuditLog::query()->where('branch_id', $request->user()?->branch_id)->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
