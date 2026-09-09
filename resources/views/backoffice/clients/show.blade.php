{{--
    One business, in full.

    Six tabs over one record. The header, the status banner, the quick-action
    control and both confirmations are on every tab — an administrator who
    decides to disable a client while reading its email log should not have to
    find their way back to a different page to do it.

    The tabs are links, so each is a URL that can be bookmarked and reloaded
    with the reader's search and page intact. Only the open tab's rows are
    read; the tab partial is the only part of this file that changes.
--}}
@extends('layouts.backoffice')

@section('title', $client->name)

{{-- Three of the tabs are wide tables. A reading width would put half of
     each under a horizontal scrollbar on a desk monitor. --}}
@section('container', 'max-w-none')

@section('content')

@php
    $admin = auth('backoffice')->user();
    $canManage = (bool) $admin?->can('clients.manage');
@endphp

    <nav class="text-[12.5px] text-sub" aria-label="{{ __('backoffice.clients.breadcrumb') }}">
        <a href="{{ route('backoffice.clients.index') }}" class="font-semibold text-link hover:underline">
            {{ __('backoffice.clients.title') }}
        </a>
        <span class="mx-1.5 text-faint">/</span>
        <span>{{ $client->name }}</span>
    </nav>

    {{-- ------------------------------------------------------------- header --}}
    <header class="mt-3 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0 flex-1 basis-80">
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="text-[22px] font-bold text-head tracking-tight">{{ $client->name }}</h1>

                <x-backoffice.client-status :status="$client->displayStatus()" />
            </div>

            <p class="text-[13px] text-sub mt-1.5">
                {{ $client->slug }}
                @if ($client->legal_name) · {{ $client->legal_name }} @endif
                · {{ __('backoffice.clients.joined_on', ['date' => $client->created_at?->translatedFormat('j M Y')]) }}
            </p>

            {{-- Who to ring. Named in the header rather than only in the
                 Overview's table, because it is the first thing wanted on a
                 support call and the reader may be on any tab. --}}
            @if ($client->owner)
                <p class="text-[12.5px] text-sub mt-1">
                    {{ __('backoffice.clients.primary_contact') }}:
                    <span class="text-head font-medium">{{ $client->owner->name }}</span>
                    <span class="text-faint">·</span>
                    <a href="mailto:{{ $client->owner->email }}" class="text-link hover:underline break-all">
                        {{ $client->owner->email }}
                    </a>
                </p>
            @endif
        </div>

        <x-backoffice.quick-actions :actions="$quickActions" class="shrink-0" />
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

    <x-backoffice.tabs :tabs="$tabs" :current="$tab" />

    <div class="mt-5">
        @include('backoffice.clients.tabs.'.$tab)
    </div>

    {{-- ------------------------------------------------------ switching off --}}
    {{-- A real dialog element: it traps focus, closes on Escape and is inert
         to the page behind it without any of that being written here. Never
         confirm() — a box the browser can suppress is not a safeguard, and the
         rules it enforces are on the server anyway. --}}
    @if ($canManage && $client->isActive())
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

    {{-- Where a client-side action reports itself. Anything that reaches the
         server says so through the console's own status banner instead; only
         copying to the clipboard has nothing to redirect to. --}}
    <div id="qa-toast" role="status" aria-live="polite"
         class="fixed bottom-5 right-5 z-50 rounded-lg bg-[#15161c] px-4 py-2.5 text-[13px] font-semibold text-white shadow-lg transition-opacity duration-200 opacity-0 pointer-events-none"
         hidden></div>

    <script>
        /* Small enough to live beside the markup it drives, and it drives
           nothing the server does not check again. */
        document.addEventListener('click', (event) => {
            const opener = event.target.closest('[data-modal-open]');

            if (opener) {
                document.getElementById(opener.dataset.modalOpen)?.showModal();
                opener.closest('[data-qa-root]')?.removeAttribute('open');
                return;
            }

            const closer = event.target.closest('[data-modal-close]');

            if (closer) {
                closer.closest('dialog')?.close();
            }
        });

        /* "Other" explains nothing by itself, so the note becomes required the
           moment it is chosen. The server enforces the same rule. */
        const reason = document.getElementById('reason');
        const note = document.getElementById('note');

        reason?.addEventListener('change', () => {
            const required = reason.value === reason.dataset.requiresNote;

            note.required = required;
            note.closest('div').querySelector('[data-note-optional]')?.toggleAttribute('hidden', required);
        });

        /* Re-opened with errors on it: show the reader what failed rather than
           a closed dialog and a page that looks unchanged. */
        @if ($errors->any())
            document.getElementById('disable-client')?.showModal();
        @endif

        /* ------------------------------------------------- quick actions --*/
        (() => {
            const root = document.querySelector('[data-quick-actions] [data-qa-root]');

            if (!root) {
                return;
            }

            const search = root.querySelector('[data-qa-search]');
            const none = root.querySelector('[data-qa-none]');
            const toast = document.getElementById('qa-toast');

            /* Every row, paired with the heading above it, so filtering can
               hide a heading whose whole group has gone. */
            const rows = [];
            let heading = null;

            root.querySelectorAll('[data-qa-group], [data-qa-item]').forEach((element) => {
                if (element.hasAttribute('data-qa-group')) {
                    heading = element;
                    return;
                }

                /* A posting action is wrapped in its own form, which is the
                    element that has to be hidden rather than the button. */
                rows.push({ heading, node: element.closest('form') ?? element, text: element.textContent.toLowerCase() });
            });

            function filter() {
                const term = search.value.trim().toLowerCase();
                const shown = new Set();
                let matches = 0;

                rows.forEach((row) => {
                    const hit = term === '' || row.text.includes(term);

                    row.node.hidden = !hit;

                    if (hit) {
                        matches += 1;
                        shown.add(row.heading);
                    }
                });

                root.querySelectorAll('[data-qa-group]').forEach((group) => {
                    group.hidden = !shown.has(group);
                });

                none.hidden = matches > 0;
            }

            search?.addEventListener('input', filter);

            /* Opened fresh every time. The control's own label never changes
               from "Select Quick Action", so closing it is the whole of
               resetting it — but a stale search term would hide most of the
               list from whoever opens it next. */
            root.addEventListener('toggle', () => {
                if (root.open) {
                    search.value = '';
                    filter();
                    search.focus();
                }
            });

            /* A disclosure stays open when the page behind it is clicked, so
               this is the part the element does not do for us. */
            document.addEventListener('click', (event) => {
                if (root.open && !root.contains(event.target)) {
                    root.removeAttribute('open');
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    root.removeAttribute('open');
                }
            });

            function say(message) {
                toast.textContent = message;
                toast.hidden = false;
                requestAnimationFrame(() => toast.classList.replace('opacity-0', 'opacity-100'));

                clearTimeout(toast.dataset.timer);
                toast.dataset.timer = setTimeout(() => {
                    toast.classList.replace('opacity-100', 'opacity-0');
                    setTimeout(() => { toast.hidden = true; }, 200);
                }, 2600);
            }

            root.querySelectorAll('[data-qa-copy]').forEach((button) => {
                button.addEventListener('click', async () => {
                    root.removeAttribute('open');

                    try {
                        await navigator.clipboard.writeText(button.dataset.qaCopy);
                        say(button.dataset.qaCopied);
                    } catch (error) {
                        /* Denied permission, or an insecure origin. Said
                           plainly rather than silently doing nothing, or the
                           reader pastes whatever was on the clipboard before. */
                        say(@json(__('backoffice.clients.copy_failed')));
                    }
                });
            });

            root.querySelectorAll('form[data-qa-confirm]').forEach((form) => {
                form.addEventListener('submit', (event) => {
                    if (!window.confirm(form.dataset.qaConfirm)) {
                        event.preventDefault();
                    }
                });
            });
        })();
    </script>
@endsection
