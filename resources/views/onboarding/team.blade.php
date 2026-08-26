@extends('layouts.onboarding')

@section('title', 'Team')
@section('heading', 'Who works with you?')
@section('subheading', 'You are already set up as the owner. Invite anyone else who takes bookings.')

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
                    <label class="flex items-center gap-2.5 cursor-pointer">
                        <input type="radio" name="provides_services" value="1" class="sd-check" @checked((bool) $providesServices)>
                        <span class="text-[13px] text-ink">Yes</span>
                    </label>
                    <label class="flex items-center gap-2.5 cursor-pointer">
                        <input type="radio" name="provides_services" value="0" class="sd-check" @checked(! $providesServices)>
                        <span class="text-[13px] text-ink">No</span>
                    </label>
                </div>
                @error('provides_services')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
            </fieldset>

            @if ($services->isNotEmpty())
                <fieldset class="mt-4 pt-4 border-t border-line">
                    <legend class="text-[13px] font-medium text-ink mb-2">Services you provide</legend>
                    <div class="grid sm:grid-cols-2 gap-x-4 gap-y-2">
                        @foreach ($services as $service)
                            <label class="flex items-center gap-2.5 cursor-pointer">
                                <input type="checkbox" name="owner_services[]" value="{{ $service->id }}" class="sd-check"
                                       @checked(in_array($service->id, old('owner_services', $staff->firstWhere('user_id', $owner->id)?->services->pluck('id')->all() ?? []), true))>
                                <span class="text-[13px] text-ink">{{ $service->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endif
        </div>

        <div data-vue-component="TeamRepeater" data-props='@json(["initial" => []])'></div>

    </form>

    {{-- Sibling form, see services.blade.php. --}}
    <div class="flex flex-wrap items-center gap-3 pt-6">
        <button type="submit" form="stepForm"
                class="h-11 px-6 rounded-lg bg-brand hover:bg-brand-dark text-white text-[14px] font-semibold transition-colors">
            Continue
        </button>

        <form method="POST" action="{{ route('onboarding.skip', 'team') }}">
            @csrf
            <button type="submit" class="h-11 px-4 rounded-lg text-[13px] font-semibold text-sub hover:text-ink hover:bg-hover transition-colors">
                I'll do this later
            </button>
        </form>
    </div>
@endsection

@section('rail')
    <h2 class="text-[15px] font-semibold text-head">Why we ask</h2>
    <p class="text-[13px] text-sub leading-relaxed mt-3">
        Clients pick who they book with, so each person needs to exist before
        they can appear on the booking page. Invitations can wait.
    </p>
@endsection
