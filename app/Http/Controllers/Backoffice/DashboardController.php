<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\BackofficeAuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * The console's front page.
 *
 * Phase 1 holds the shell and the one thing that is already real: what
 * administrators have been doing. The platform figures — clients, revenue,
 * renewals — arrive with their own phase, because a KPI card invented from
 * data that does not exist yet is a number somebody will act on.
 */
class DashboardController extends Controller
{
    public function index(): View
    {
        return view('backoffice.dashboard', [
            'admin' => Auth::guard('backoffice')->user(),
            /* The console's own recent history, which is the only thing this
               page can honestly report until the platform tables exist. */
            'recent' => BackofficeAuditLog::query()->with('admin')->newest()->limit(10)->get(),
        ]);
    }
}
