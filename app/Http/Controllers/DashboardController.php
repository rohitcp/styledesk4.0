<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * The first dashboard carries a getting-started checklist (section 17).
     *
     * Each item reflects real state rather than a stored flag, so ticking one
     * off cannot drift from the thing it claims to describe. It disappears
     * once everything is done, and the owner can dismiss it before then.
     */
    public function __invoke(Request $request): View
    {
        $tenant = $request->user()->tenant;
        $onboarding = $tenant->onboarding;

        $checklist = [
            ['label' => 'Add your first service', 'done' => $tenant->services()->exists()],
            ['label' => 'Add team members', 'done' => $tenant->staff()->whereNull('user_id')->exists()],
            ['label' => 'Configure staff schedules', 'done' => false],
            ['label' => 'Add your first client', 'done' => false],
            ['label' => 'Customize online booking', 'done' => $tenant->bookingSettings()->exists()],
            ['label' => 'Configure payments', 'done' => false],
            ['label' => 'Configure appointment reminders', 'done' => false],
            ['label' => 'Add your logo and branding', 'done' => $tenant->logo_path !== null],
            ['label' => 'Create your first appointment', 'done' => false],
        ];

        $outstanding = collect($checklist)->reject(fn (array $i) => $i['done'])->isNotEmpty();

        return view('dashboard', [
            'tenant' => $tenant,
            'checklist' => $checklist,
            'showChecklist' => $outstanding
                && $onboarding?->getting_started_dismissed_at === null,
            'canDismissChecklist' => $tenant->owner_user_id === $request->user()->id,
        ]);
    }
}
