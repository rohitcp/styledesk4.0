{{--
    Everything an administrator does to one client, in one control.

    A combo box rather than a row of buttons: the list is thirteen long
    already and the brief says it grows. Buttons would push the page's own
    content below the fold to make room for actions most readers never press.

    Built on a disclosure element, so it opens, closes on Escape and closes on
    a click outside without any of that being written here — and it still
    opens with scripting switched off. The search box is the one part that
    needs a script; the actions underneath it do not, which is the right way
    round for a console read during an incident.

    Nothing here decides what may be done. Every destructive entry opens the
    same confirmation the page already carries, every posting entry goes
    through a route with its own permission gate, and the server checks again.
--}}
@props(['actions', 'labelledBy' => null])

@php
    /* Grouped in the order a reader reaches for them: go somewhere, tell
       somebody, change something. The headings come from the caller so the
       component holds no copy of its own. */
    $groups = collect($actions)->groupBy('group');
@endphp

<div class="relative w-full sm:w-[300px]" data-quick-actions>
    <span id="qa-label" class="block text-[12px] font-medium text-ink mb-1.5">
        {{ __('backoffice.clients.quick_action') }}
    </span>

    <details class="group" data-qa-root>
        <summary
            class="list-none cursor-pointer sd-input flex items-center justify-between gap-2 select-none
                   marker:hidden [&::-webkit-details-marker]:hidden"
            role="button" aria-haspopup="listbox" aria-describedby="qa-label">
            <span class="text-sub truncate">{{ __('backoffice.clients.quick_action_placeholder') }}</span>
            <span class="text-faint shrink-0 transition-transform group-open:rotate-180" aria-hidden="true">▾</span>
        </summary>

        <div class="absolute z-30 mt-1.5 w-full rounded-xl border border-line bg-white shadow-lg overflow-hidden">
            <div class="p-2 border-b border-line">
                <input type="search" class="sd-input" data-qa-search
                       placeholder="{{ __('backoffice.clients.quick_action_search') }}"
                       aria-label="{{ __('backoffice.clients.quick_action_search') }}">
            </div>

            <div class="max-h-[19rem] overflow-y-auto py-1" role="listbox">
                @foreach ($groups as $heading => $items)
                    <p class="px-3 pt-2 pb-1 text-[11px] font-semibold uppercase tracking-wide text-faint" data-qa-group>
                        {{ $heading }}
                    </p>

                    @foreach ($items as $action)
                        @php
                            $row = 'w-full text-left px-3 py-2 text-[13px] flex items-center justify-between gap-3 transition-colors';
                            $live = $row.' text-head hover:bg-brand/[0.06] hover:text-brand cursor-pointer';
                            $dead = $row.' text-faint cursor-not-allowed';
                        @endphp

                        @if ($action['disabled'] ?? false)
                            {{-- Listed and refused rather than hidden: a reader
                                 who came looking for it learns it is coming,
                                 instead of wondering whether they missed it. --}}
                            <span class="{{ $dead }}" data-qa-item aria-disabled="true">
                                <span>{{ $action['label'] }}</span>
                                <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[10.5px] font-semibold text-slate-500">
                                    {{ __('backoffice.tabs.coming_soon') }}
                                </span>
                            </span>

                        @elseif ($action['type'] === 'link')
                            <a href="{{ $action['url'] }}" class="{{ $live }}" data-qa-item
                               @if ($action['external'] ?? false) target="_blank" rel="noopener" @endif>
                                <span>{{ $action['label'] }}</span>
                                @if ($action['external'] ?? false)
                                    <span class="text-faint text-[11px] shrink-0" aria-hidden="true">↗</span>
                                @endif
                            </a>

                        @elseif ($action['type'] === 'copy')
                            <button type="button" class="{{ $live }}" data-qa-item
                                    data-qa-copy="{{ $action['value'] }}"
                                    data-qa-copied="{{ $action['copied'] }}">
                                <span>{{ $action['label'] }}</span>
                            </button>

                        @elseif ($action['type'] === 'modal')
                            <button type="button" class="{{ $live }}" data-qa-item
                                    data-modal-open="{{ $action['modal'] }}">
                                <span>{{ $action['label'] }}</span>
                            </button>

                        @else
                            {{-- A state change with nothing to fill in. Still a
                                 form and still a POST, so it carries a token and
                                 cannot be triggered by a link somebody was sent. --}}
                            <form method="POST" action="{{ $action['url'] }}"
                                  @if ($action['confirm'] ?? null) data-qa-confirm="{{ $action['confirm'] }}" @endif>
                                @csrf
                                <button type="submit" class="{{ $live }}" data-qa-item>
                                    <span>{{ $action['label'] }}</span>
                                </button>
                            </form>
                        @endif
                    @endforeach
                @endforeach

                <p class="px-3 py-6 text-center text-[12.5px] text-sub" data-qa-none hidden>
                    {{ __('backoffice.clients.quick_action_none') }}
                </p>
            </div>
        </div>
    </details>
</div>
