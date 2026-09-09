@extends('layouts.onboarding')

@section('title', 'Team')
@section('heading', 'Who works with you?')
@section('subheading', 'You are already set up as the owner. Invite anyone else who takes bookings — they are emailed straight away.')

@section('form')
    <form id="stepForm" method="POST" action="{{ route('onboarding.team.store') }}" class="mt-6 space-y-6">
        @csrf

        <div class="rounded-card border border-line bg-white p-4">
            <div class="flex items-center gap-3">
                <span class="sd-avatar sd-avatar--md" aria-hidden="true">{{ strtoupper(substr($owner->first_name, 0, 1).substr($owner->last_name, 0, 1)) }}</span>
                <span class="min-w-0">
                    <span class="block text-[13px] font-semibold text-head truncate">{{ $owner->name }}</span>
                    <span class="block text-[12px] text-sub truncate">{{ $owner->email }} &middot; Owner</span>
                </span>
            </div>

            {{-- Section 14. Asked explicitly so an owner who also takes
                 bookings does not have to add themselves a second time as an
                 employee. --}}
            <fieldset class="mt-4 pt-4 border-t border-line">
                <legend class="text-[13px] font-medium text-ink mb-2">Do you provide services to clients?</legend>
                @php
                    $providesServices = old('provides_services', $staff->firstWhere('user_id', $owner->id)?->provides_services ?? true);
                @endphp
                <div class="flex items-center gap-5">
                    <label class="styledesk_choice">
                        <input type="radio" name="provides_services" value="1" class="sd-check" @checked((bool) $providesServices)>
                        <span class="styledesk_choice__label">Yes</span>
                    </label>
                    <label class="styledesk_choice">
                        <input type="radio" name="provides_services" value="0" class="sd-check" @checked(! $providesServices)>
                        <span class="styledesk_choice__label">No</span>
                    </label>
                </div>
                @error('provides_services')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
            </fieldset>

            @if ($services->isNotEmpty())
                <fieldset class="mt-4 pt-4 border-t border-line">
                    <legend class="text-[13px] font-medium text-ink mb-2">Services you provide</legend>
                    <div class="grid sm:grid-cols-2 gap-x-4 gap-y-2">
                        @foreach ($services as $service)
                            <label class="styledesk_choice">
                                <input type="checkbox" name="owner_services[]" value="{{ $service->id }}" class="sd-check"
                                       @checked(in_array($service->id, old('owner_services', $staff->firstWhere('user_id', $owner->id)?->services->pluck('id')->all() ?? []), true))>
                                <span class="styledesk_choice__label">{{ $service->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endif
        </div>

    </form>

    {{-- Outside the step form on purpose.
         Inviting is its own request that fires on Send, not something the
         Continue button submits — and a nested <form> would be dropped by the
         browser anyway. --}}
    @php
        $teamInviteProps = [
            'invitations' => $invitations->map(fn ($i) => [
                'id' => $i->id,
                'first_name' => $i->first_name,
                'last_name' => $i->last_name,
                'name' => $i->name,
                'email' => $i->email,
                'role' => $i->role,
                'role_label' => \App\Mail\TeamInvitationMail::roleLabel($i->role),
                'status' => $i->effectiveStatus(),
                'status_label' => $i->statusLabel(),
                'can_resend' => $i->isResendable(),
            ])->values(),
            'locations' => $locations,
            'services' => $services->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values(),
            'tenantId' => $tenant?->getTenantKey(),
            'endpoint' => url('/team/invitations'),
            'csrf' => csrf_token(),
        ];
    @endphp

    <div class="mt-6" data-vue-component="TeamInvites" data-props='@json($teamInviteProps)'></div>

    {{-- Sibling form, see services.blade.php. --}}
    <div class="flex flex-wrap items-center gap-3 pt-6">
        {{-- Fires once, like every other step. A slow POST shows the reader
             nothing, so they click again — and a second submit repeats the
             step. --}}
        <button type="submit" form="stepForm" data-submit-once data-busy-label="{{ __('common.saving') }}"
                class="h-11 px-6 rounded-lg bg-brand hover:bg-brand-dark text-white text-[14px] font-semibold transition-colors disabled:opacity-60 disabled:pointer-events-none">
            Continue
        </button>

        <form method="POST" action="{{ route('onboarding.skip', 'team') }}">
            @csrf
            {{-- The skip posts too, so it gets the same guard. --}}
            <button type="submit" data-submit-once
                    class="h-11 px-4 rounded-lg text-[13px] font-semibold text-sub hover:text-ink hover:bg-hover transition-colors disabled:opacity-60 disabled:pointer-events-none">
                I'll do this later
            </button>
        </form>

        {{-- A link, not a form post: going back only re-reads an earlier step,
             so it must not submit anything or move current_step. --}}
        @if ($previousStep)
            {{-- ml-auto: Back sits on the opposite side from Continue, so
                 the button that moves forward stays where the eye lands. --}}
            <a href="{{ route('onboarding.'.$previousStep) }}"
               class="styledesk_action ml-auto">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Back
            </a>
        @endif
    </div>
@endsection

@section('rail')
    <h2 class="text-[15px] font-semibold text-head">Who will be in your workspace</h2>
    <p class="text-[13px] text-sub mt-1.5 leading-relaxed">Everyone you invite is emailed straight away.</p>

    @php
        $ownerInitials = strtoupper(mb_substr($owner->first_name, 0, 1).mb_substr($owner->last_name, 0, 1));
        $teamPreviewProps = ['ownerName' => $owner->name, 'ownerInitials' => $ownerInitials];
    @endphp

    <div class="mt-5" data-vue-component="TeamPreview" data-props='@json($teamPreviewProps)'></div>

    <ul class="mt-8 space-y-4">
        @foreach ([
            'Role decides what someone can see and change. Only the Owner can bill or delete the workspace.',
            'Anyone assigned services becomes bookable, so clients can pick them by name.',
            'Invitations are emailed the moment you send them, and expire after seven days. You can resend or cancel any that are still pending.',
        ] as $point)
            <li class="flex items-start gap-3">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" class="text-brand mt-0.5 shrink-0" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span class="text-[13px] text-ink leading-relaxed">{{ $point }}</span>
            </li>
        @endforeach
    </ul>
@endsection
