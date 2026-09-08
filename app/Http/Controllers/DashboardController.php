<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Support\DashboardData;
use App\Support\DashboardLayout;
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
        $user = $request->user();
        $tenant = $user->tenant;
        $onboarding = $tenant->onboarding;

        /* Which panels this person gets, and in what order. The role decides
           the running order; the permissions decide what is in it. */
        $widgets = DashboardLayout::for($user);
        $selectable = DashboardLayout::selectableLocations($user);

        /* The branch being read. A reader with one branch has no choice to
           make, and a chosen branch is only honoured where it is one of
           theirs — a location id in a query string is not permission. */
        $scope = DashboardLayout::locationScope($user);
        $chosen = (int) $request->query('location', 0);

        if ($chosen > 0 && $selectable->contains('id', $chosen)) {
            $scope = [$chosen];
        }

        $data = DashboardData::forUser($user, $scope);

        /**
         * The getting-started checklist.
         *
         * Only the keys and the "is it done" question live here; the wording
         * is in each language's dashboard file. It used to be nine English
         * literals in this array, which is how a Chinese dashboard came to
         * draw a translated card around a checklist that was entirely in
         * English.
         *
         * @var array<int, array{label: string, done: bool}>
         */
        $checklist = [
            ['label' => __('dashboard.getting_started.items.service'), 'done' => $tenant->services()->exists()],
            ['label' => __('dashboard.getting_started.items.team'), 'done' => $this->hasTeam($tenant)],
            ['label' => __('dashboard.getting_started.items.schedules'), 'done' => false],
            ['label' => __('dashboard.getting_started.items.client'), 'done' => $tenant->clients()->exists()],
            ['label' => __('dashboard.getting_started.items.online_booking'), 'done' => $tenant->bookingSettings()->exists()],
            ['label' => __('dashboard.getting_started.items.payments'), 'done' => false],
            ['label' => __('dashboard.getting_started.items.reminders'), 'done' => false],
            ['label' => __('dashboard.getting_started.items.branding'), 'done' => $tenant->logo_path !== null],
            ['label' => __('dashboard.getting_started.items.appointment'), 'done' => false],
        ];

        $outstanding = collect($checklist)->reject(fn (array $i) => $i['done'])->isNotEmpty();

        return view('dashboard', [
            'tenant' => $tenant,
            'widgets' => $widgets,
            'data' => $data,
            'staff' => DashboardLayout::staffFor($user),
            'locations' => $selectable,
            'chosenLocation' => $chosen > 0 && $selectable->contains('id', $chosen) ? $chosen : null,
            /* What the reader is called on their own dashboard. The greeting
               is the one place a role name belongs — everything else is
               decided by permissions. */
            'roleLabel' => $user->role()?->label(),
            'checklist' => $checklist,
            'showChecklist' => $outstanding
                && $onboarding?->getting_started_dismissed_at === null,
            'canDismissChecklist' => $tenant->owner_user_id === $request->user()->id,
        ]);
    }

    /**
     * Whether this business has anybody on it besides the person who made it.
     *
     * The owner is seeded as staff by onboarding, so "are there any staff" is
     * true for every business the moment it exists and would tick this off
     * before anyone had been added. What the item is asking about is a second
     * person, so the owner's own row is excluded.
     *
     * An invitation that has been sent counts. The action the checklist is
     * asking for is done — the reader cannot make somebody accept, and an
     * item that stays open until they do would nag about someone else's
     * inbox.
     */
    private function hasTeam(Tenant $tenant): bool
    {
        $others = $tenant->staff()
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', '!=', $tenant->owner_user_id))
            ->exists();

        return $others || $tenant->teamInvitations()->pending()->exists();
    }
}
