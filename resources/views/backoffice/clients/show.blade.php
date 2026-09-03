{{--
    One business, in full.

    Read-only. Suspending, editing and impersonating are `clients.manage` and
    arrive with that phase — so there is nothing on this page that can change
    a customer's account by being clicked.
--}}
@extends('layouts.backoffice')

@section('title', $client->name)

@section('content')

@php
    $admin = auth('backoffice')->user();
    $canManage = (bool) $admin?->can('clients.manage');

    /* Locations carry their own status vocabulary, which has no enum behind it
       either. A readable word beats a printed lang key. */
    $label = fn (?string $value): string => ($value === null || $value === '')
        ? __('backoffice.clients.none')
        : \Illuminate\Support\Str::headline($value);

    /* A dash rather than an empty cell: blank reads as "the page failed to
       load this", a dash reads as "there is nothing here". */
    $or = fn (?string $value) => ($value === null || $value === '') ? __('backoffice.clients.none') : $value;
@endphp

    <nav class="text-[12.5px] text-sub" aria-label="{{ __('backoffice.clients.breadcrumb') }}">
        <a href="{{ route('backoffice.clients.index') }}" class="font-semibold text-link hover:underline">
            {{ __('backoffice.clients.title') }}
        </a>
        <span class="mx-1.5 text-faint">/</span>
        <span>{{ $client->name }}</span>
    </nav>

    <header class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="text-[22px] font-bold text-head tracking-tight">{{ $client->name }}</h1>

                <x-backoffice.client-status :status="$client->displayStatus()" />
            </div>

            <p class="text-[13px] text-sub mt-1.5">
                {{ $client->slug }}
                @if ($client->legal_name) · {{ $client->legal_name }} @endif
                · {{ __('backoffice.clients.joined_on', ['date' => $client->created_at?->translatedFormat('j M Y')]) }}
            </p>
        </div>

        {{-- Re-enabling is one press: it opens a door rather than closing one,
             so it needs no reason and no confirmation. Disabling is the panel
             below, which does. --}}
        @if ($canManage && $client->isDisabled())
            <button type="button" data-modal-open="enable-client"
                    class="shrink-0 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
                {{ __('backoffice.clients.enable_action') }}
            </button>
        @endif
    </header>

    {{-- Why the business is off, above everything else on the page: it is the
         answer to the question that brought the reader here. --}}
    @if ($client->isDisabled())
        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3" role="status">
            <p class="text-[13px] font-semibold text-amber-900">
                {{ __('backoffice.clients.disabled_banner') }}
            </p>
            <p class="text-[12.5px] text-amber-800 mt-1">
                {{ __('backoffice.clients.disabled_detail', [
                    'who' => $client->disabledBy?->name ?? __('backoffice.audit.unknown_actor'),
                    'when' => \App\Support\TimeFormat::dateTime($client->disabled_at) ?? __('backoffice.clients.none'),
                ]) }}
            </p>
            @if ($client->disabled_reason)
                <p class="text-[12.5px] text-amber-800 mt-1">
                    {{ __('backoffice.clients.disable_reason') }}:
                    {{ __('backoffice.clients.reasons.'.$client->disabled_reason) }}
                </p>
            @endif

            @if ($client->disabled_note)
                <p class="text-[12.5px] text-amber-800 mt-1">
                    {{ __('backoffice.clients.note') }}: {{ $client->disabled_note }}
                </p>
            @endif
        </div>
    @endif

    {{-- The five figures this console is asked for first. --}}
    @php
        $counts = [
            ['label' => __('backoffice.clients.col.locations'), 'value' => $client->locations_count],
            ['label' => __('backoffice.clients.col.staff'), 'value' => $client->staff_count],
            ['label' => __('backoffice.clients.stats.services'), 'value' => $client->services_count],
            ['label' => __('backoffice.clients.col.clients'), 'value' => $client->clients_count],
            ['label' => __('backoffice.clients.stats.users'), 'value' => $client->users_count],
        ];
    @endphp

    <div class="mt-5 grid grid-cols-2 lg:grid-cols-5 gap-3">
        @foreach ($counts as $count)
            <div class="sd-card px-4 py-3.5">
                <p class="text-[12px] text-sub">{{ $count['label'] }}</p>
                <p class="text-[22px] font-bold text-head mt-1 tabular-nums">{{ number_format($count['value']) }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-4 grid lg:grid-cols-2 gap-4">

        {{-- ------------------------------------------------------- the plan --}}
        <section class="sd-card p-5">
            <h2 class="text-[15px] font-semibold text-head">{{ __('backoffice.clients.subscription') }}</h2>

            @php
                $plan = [
                    __('backoffice.clients.col.plan') => $client->plan_id ?? __('backoffice.clients.no_plan'),
                    __('backoffice.clients.subscription') => __('backoffice.clients.statuses.'.$client->displayStatus()),
                    __('backoffice.clients.trial_started') => $client->trial_started_at?->translatedFormat('j M Y') ?? __('backoffice.clients.none'),
                    __('backoffice.clients.trial_ends') => $client->trial_ends_at
                        ? $client->trial_ends_at->translatedFormat('j M Y')
                            .($client->trialDaysRemaining() > 0
                                ? ' · '.__('backoffice.clients.trial_days', ['days' => $client->trialDaysRemaining()])
                                : '')
                        : __('backoffice.clients.none'),
                ];
            @endphp

            <dl class="mt-3 divide-y divide-line text-[13px]">
                @foreach ($plan as $term => $value)
                    <div class="py-2.5 flex items-baseline justify-between gap-4">
                        <dt class="text-sub shrink-0">{{ $term }}</dt>
                        <dd class="text-head font-medium text-right">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        {{-- ------------------------------------------------------ the owner --}}
        <section class="sd-card p-5">
            <h2 class="text-[15px] font-semibold text-head">{{ __('backoffice.clients.col.owner') }}</h2>

            @if ($client->owner)
                <dl class="mt-3 divide-y divide-line text-[13px]">
                    @php
                        $owner = [
                            __('backoffice.clients.owner_name') => $client->owner->name,
                            __('backoffice.clients.owner_email') => $client->owner->email,
                            __('backoffice.clients.owner_phone') => $or($client->owner->phone),
                        ];
                    @endphp

                    @foreach ($owner as $term => $value)
                        <div class="py-2.5 flex items-baseline justify-between gap-4">
                            <dt class="text-sub shrink-0">{{ $term }}</dt>
                            <dd class="text-head font-medium text-right break-all">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            @else
                <p class="text-[13px] text-sub mt-3">{{ __('backoffice.clients.no_owner') }}</p>
            @endif
        </section>

        {{-- ---------------------------------------------------- the contact --}}
        <section class="sd-card p-5">
            <h2 class="text-[15px] font-semibold text-head">{{ __('backoffice.clients.contact') }}</h2>

            @php
                $contact = [
                    __('backoffice.clients.business_email') => $or($client->business_email),
                    __('backoffice.clients.business_phone') => $or($client->business_phone),
                    __('backoffice.clients.website') => $or($client->website),
                    __('backoffice.clients.country') => $or($client->country_code),
                ];
            @endphp

            <dl class="mt-3 divide-y divide-line text-[13px]">
                @foreach ($contact as $term => $value)
                    <div class="py-2.5 flex items-baseline justify-between gap-4">
                        <dt class="text-sub shrink-0">{{ $term }}</dt>
                        <dd class="text-head font-medium text-right break-all">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        {{-- ---------------------------------------------------- the regional --}}
        <section class="sd-card p-5">
            <h2 class="text-[15px] font-semibold text-head">{{ __('backoffice.clients.regional') }}</h2>

            @php
                $regional = [
                    __('backoffice.clients.currency') => $client->currency_code,
                    __('backoffice.clients.timezone') => $client->timezone,
                    __('backoffice.clients.language') => $client->default_language,
                    __('backoffice.clients.identifier') => $client->getTenantKey(),
                ];
            @endphp

            <dl class="mt-3 divide-y divide-line text-[13px]">
                @foreach ($regional as $term => $value)
                    <div class="py-2.5 flex items-baseline justify-between gap-4">
                        <dt class="text-sub shrink-0">{{ $term }}</dt>
                        <dd class="text-head font-medium text-right break-all">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    </div>

    {{-- --------------------------------------------------------- locations --}}
    <section class="sd-card mt-4 overflow-hidden">
        <h2 class="text-[15px] font-semibold text-head px-5 pt-5 pb-3">
            {{ __('backoffice.clients.col.locations') }}
        </h2>

        <div class="overflow-x-auto">
            <table class="w-full text-[13px]">
                <thead class="bg-[#fbfbfc]">
                    <tr class="border-y border-line text-left text-[11.5px] uppercase tracking-wide text-sub">
                        <th scope="col" class="px-5 py-2.5 font-semibold">{{ __('backoffice.clients.location_name') }}</th>
                        <th scope="col" class="px-5 py-2.5 font-semibold">{{ __('backoffice.clients.location_where') }}</th>
                        <th scope="col" class="px-5 py-2.5 font-semibold">{{ __('backoffice.clients.status') }}</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-line">
                    @forelse ($client->locations as $location)
                        <tr class="odd:bg-white even:bg-[#fcfcfd]">
                            <td class="px-5 py-2.5">
                                <span class="font-medium text-head">{{ $location->name }}</span>
                                @if ($location->is_primary)
                                    <span class="ml-1.5 text-[11px] font-semibold text-brand">
                                        {{ __('backoffice.clients.primary') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-2.5 text-sub">
                                {{ collect([$location->city, $location->country])->filter()->implode(', ') ?: __('backoffice.clients.none') }}
                            </td>
                            <td class="px-5 py-2.5 text-sub">{{ $label($location->status) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-5 py-8 text-center text-[13px] text-sub">
                                {{ __('backoffice.clients.no_locations') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- ------------------------------------------------------------- users --}}
    <section class="sd-card mt-4 overflow-hidden">
        <h2 class="text-[15px] font-semibold text-head px-5 pt-5 pb-3">
            {{ __('backoffice.clients.stats.users') }}
        </h2>

        <div class="overflow-x-auto">
            <table class="w-full text-[13px]">
                <thead class="bg-[#fbfbfc]">
                    <tr class="border-y border-line text-left text-[11.5px] uppercase tracking-wide text-sub">
                        <th scope="col" class="px-5 py-2.5 font-semibold">{{ __('backoffice.clients.owner_name') }}</th>
                        <th scope="col" class="px-5 py-2.5 font-semibold">{{ __('backoffice.clients.owner_email') }}</th>
                        <th scope="col" class="px-5 py-2.5 font-semibold text-right">{{ __('backoffice.clients.last_seen') }}</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-line">
                    @forelse ($client->users as $user)
                        <tr class="odd:bg-white even:bg-[#fcfcfd]">
                            <td class="px-5 py-2.5 font-medium text-head">{{ $user->name }}</td>
                            <td class="px-5 py-2.5 text-sub break-all">{{ $user->email }}</td>
                            <td class="px-5 py-2.5 text-right text-sub whitespace-nowrap">
                                {{ \App\Support\TimeFormat::dateTime($user->last_login_at) ?? __('backoffice.clients.never') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-5 py-8 text-center text-[13px] text-sub">
                                {{ __('backoffice.clients.no_users') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- ----------------------------------------------------- the history --}}
    <section class="sd-card mt-4 p-5">
        <h2 class="text-[15px] font-semibold text-head">{{ __('backoffice.clients.activity') }}</h2>

        @if ($activity->isEmpty())
            <p class="text-[13px] text-sub mt-3">{{ __('backoffice.clients.no_activity') }}</p>
        @else
            <ul class="mt-3 divide-y divide-line">
                @foreach ($activity as $entry)
                    <li class="py-3">
                        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                            <span class="text-[13px] font-semibold text-head">{{ $entry->label() }}</span>
                            <span class="text-[12px] text-faint shrink-0">
                                {{ \App\Support\TimeFormat::dateTime($entry->created_at) }}
                            </span>
                        </div>

                        {{-- The reason as its label, not its key: the log is
                             read by people, and 'non_payment' is not a word. --}}
                        @if (data_get($entry->after, 'reason'))
                            <p class="text-[12.5px] text-sub mt-1">
                                {{ __('backoffice.clients.disable_reason') }}:
                                {{ __('backoffice.clients.reasons.'.data_get($entry->after, 'reason')) }}
                            </p>
                        @endif

                        @if (data_get($entry->after, 'note'))
                            <p class="text-[12.5px] text-sub mt-0.5">
                                {{ __('backoffice.clients.note') }}: {{ data_get($entry->after, 'note') }}
                            </p>
                        @endif

                        <p class="text-[12.5px] text-sub mt-0.5">
                            {{ __('backoffice.clients.by') }}:
                            {{ $entry->admin_name ?? $entry->admin_email ?? __('backoffice.audit.unknown_actor') }}
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- ------------------------------------------------------ switching off --}}
    @if ($canManage && $client->isActive())
        <div class="mt-4 flex justify-end">
            <button type="button" data-modal-open="disable-client"
                    class="h-10 px-4 rounded-lg bg-danger hover:opacity-90 text-white text-[13px] font-semibold transition-opacity">
                {{ __('backoffice.clients.disable_action') }}
            </button>
        </div>

        {{-- A real <dialog>: it traps focus, closes on Escape and is inert to
             the page behind it without any of that being written here. Never
             confirm() — a dialog the browser can suppress is not a safeguard,
             and the rules it enforces are on the server anyway. --}}
        <dialog id="disable-client"
                class="w-[min(92vw,32rem)] rounded-xl border border-line bg-white p-0 backdrop:bg-black/40">
            <form method="POST" action="{{ route('backoffice.clients.disable', $client) }}">
                @csrf

                <div class="px-5 pt-5">
                    <h2 class="text-[16px] font-bold text-head">{{ __('backoffice.clients.disable_confirm_title') }}</h2>
                    <p class="text-[13px] text-sub mt-1.5">
                        {{ __('backoffice.clients.disable_confirm_body', ['name' => $client->name]) }}
                    </p>
                </div>

                <div class="px-5 pt-4 space-y-4">
                    <div>
                        <label for="reason" class="block text-[13px] font-medium text-ink mb-1.5">
                            {{ __('backoffice.clients.disable_reason') }}
                        </label>
                        <select id="reason" name="reason" class="sd-input" required
                                data-requires-note="{{ $reasonRequiringNote }}">
                            <option value="">{{ __('backoffice.clients.choose_reason') }}</option>
                            @foreach ($reasons as $reason)
                                <option value="{{ $reason }}" @selected(old('reason') === $reason)>
                                    {{ __('backoffice.clients.reasons.'.$reason) }}
                                </option>
                            @endforeach
                        </select>
                        @error('reason')
                            <p class="mt-1.5 text-[12px] text-danger" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="note" class="block text-[13px] font-medium text-ink mb-1.5">
                            {{ __('backoffice.clients.note') }}
                            <span class="font-normal text-sub" data-note-optional>{{ __('backoffice.clients.optional') }}</span>
                        </label>
                        <textarea id="note" name="note" rows="3" class="sd-input" maxlength="1000"
                                  placeholder="{{ __('backoffice.clients.note_placeholder') }}">{{ old('note') }}</textarea>
                        <p class="mt-1.5 text-[12px] text-sub">{{ __('backoffice.clients.note_internal') }}</p>
                        @error('note')
                            <p class="mt-1.5 text-[12px] text-danger" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2.5 px-5 py-4 mt-4 border-t border-line bg-[#fbfbfc]">
                    <button type="button" data-modal-close
                            class="h-10 px-4 rounded-lg border border-line bg-white hover:bg-black/[0.03] text-[13px] font-semibold text-head transition-colors">
                        {{ __('backoffice.clients.cancel') }}
                    </button>
                    <button type="submit"
                            class="h-10 px-4 rounded-lg bg-danger hover:opacity-90 text-white text-[13px] font-semibold transition-opacity">
                        {{ __('backoffice.clients.disable_action') }}
                    </button>
                </div>
            </form>
        </dialog>
    @endif

    {{-- ------------------------------------------------------- switching on --}}
    @if ($canManage && $client->isDisabled())
        <dialog id="enable-client"
                class="w-[min(92vw,32rem)] rounded-xl border border-line bg-white p-0 backdrop:bg-black/40">
            <form method="POST" action="{{ route('backoffice.clients.enable', $client) }}">
                @csrf

                <div class="px-5 pt-5">
                    <h2 class="text-[16px] font-bold text-head">{{ __('backoffice.clients.enable_confirm_title') }}</h2>
                    <p class="text-[13px] text-sub mt-1.5">
                        {{ __('backoffice.clients.enable_confirm_body', ['name' => $client->name]) }}
                    </p>
                </div>

                <div class="px-5 pt-4">
                    <label for="enable_note" class="block text-[13px] font-medium text-ink mb-1.5">
                        {{ __('backoffice.clients.note') }}
                        <span class="font-normal text-sub">{{ __('backoffice.clients.optional') }}</span>
                    </label>
                    <textarea id="enable_note" name="note" rows="3" class="sd-input" maxlength="1000"
                              placeholder="{{ __('backoffice.clients.enable_note_placeholder') }}">{{ old('note') }}</textarea>
                    <p class="mt-1.5 text-[12px] text-sub">{{ __('backoffice.clients.note_internal') }}</p>
                    @error('note')
                        <p class="mt-1.5 text-[12px] text-danger" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-end gap-2.5 px-5 py-4 mt-4 border-t border-line bg-[#fbfbfc]">
                    <button type="button" data-modal-close
                            class="h-10 px-4 rounded-lg border border-line bg-white hover:bg-black/[0.03] text-[13px] font-semibold text-head transition-colors">
                        {{ __('backoffice.clients.cancel') }}
                    </button>
                    <button type="submit"
                            class="h-10 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
                        {{ __('backoffice.clients.enable_action') }}
                    </button>
                </div>
            </form>
        </dialog>
    @endif

    @if ($canManage)
        <script>
            /* Small enough to live beside the markup it drives, and it drives
               nothing the server does not check again. */
            document.querySelectorAll('[data-modal-open]').forEach((button) => {
                button.addEventListener('click', () => {
                    document.getElementById(button.dataset.modalOpen)?.showModal();
                });
            });

            document.querySelectorAll('[data-modal-close]').forEach((button) => {
                button.addEventListener('click', () => button.closest('dialog')?.close());
            });

            /* "Other" explains nothing by itself, so the note becomes required
               the moment it is chosen. The server enforces the same rule. */
            const reason = document.getElementById('reason');
            const note = document.getElementById('note');

            reason?.addEventListener('change', () => {
                const required = reason.value === reason.dataset.requiresNote;

                note.required = required;
                note.closest('div').querySelector('[data-note-optional]')?.toggleAttribute('hidden', required);
            });

            /* Re-opened with errors on it: show the reader what failed rather
               than a closed dialog and a page that looks unchanged. */
            @if ($errors->any())
                document.getElementById('disable-client')?.showModal();
            @endif
        </script>
    @endif
@endsection
