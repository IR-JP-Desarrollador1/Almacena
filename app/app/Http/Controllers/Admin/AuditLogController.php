<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(): View
    {
        return view('admin.audit.index', [
            'logs' => AuditLog::query()
                ->with('user')
                ->latest('created_at')
                ->paginate(30),
        ]);
    }
}
