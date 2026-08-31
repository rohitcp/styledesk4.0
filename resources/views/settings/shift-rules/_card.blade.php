{{--
    One shift rule, as a card.

    Compact and scannable: what it is called, whether it is in use, where it
    applies, the week it runs and the limits that matter — then the three
    things you can do to it. Everything else is a detail of the form.
--}}
<article class="bg-white border border-line rounded-card p-4 flex flex-col">
    <div class="flex items-start gap-3">
        <h3 class="text-[14px] font-semibold text-head min-w-0 flex-1 truncate">{{ $card->name }}</h3>

        <span class="styledesk_badge {{ $card->statusClass() }} shrink-0">{{ $card->statusLabel() }}</span>
    </div>

    @if ($card->description)
        <p class="text-[12px] text-sub mt-1 leading-relaxed line-clamp-2">{{ $card->description }}</p>
    @endif

    <dl class="mt-3 space-y-1.5 text-[12px]">
        <div class="flex gap-2">
            <dt class="text-faint w-[86px] shrink-0">{{ __('shift_rules.columns.location') }}</dt>
            <dd class="text-ink min-w-0">
                {{ $card->location_scope === 'all'
                    ? __('shift_rules.all_locations')
                    : $card->locations->pluck('name')->implode(', ') }}
            </dd>
        </div>

        {{-- The business's own week, which is what the rule works within —
             not a copy the rule keeps. --}}
        <div class="flex gap-2">
            <dt class="text-faint w-[86px] shrink-0">{{ __('shift_rules.columns.days') }}</dt>
            <dd class="text-ink min-w-0">{{ $card->workingDaysLabel() }}</dd>
        </div>

        <div class="flex gap-2">
            <dt class="text-faint w-[86px] shrink-0">{{ __('shift_rules.columns.hours') }}</dt>
            <dd class="text-ink min-w-0">{{ $card->defaultHoursLabel() }}</dd>
        </div>

        @if ($card->max_hours_per_week)
            <div class="flex gap-2">
                <dt class="text-faint w-[86px] shrink-0">{{ __('shift_rules.columns.weekly') }}</dt>
                <dd class="text-ink min-w-0">
                    {{ $card->max_hours_per_week }} {{ __('shift_rules.fields.hours_per_week') }}
                </dd>
            </div>
        @endif

        {{-- The periods the day is divided into, when the rule divides it. --}}
        @if ($card->allow_split_shift && $card->shiftPeriods->isNotEmpty())
            <div class="flex gap-2">
                <dt class="text-faint w-[86px] shrink-0">{{ __('shift_rules.sections.split') }}</dt>
                <dd class="text-ink min-w-0">
                    {{ $card->shiftPeriods->map(fn ($period) => $period->name)->implode(', ') }}
                </dd>
            </div>
        @endif

        {{-- Only when the rule is dated: "always in force" is the ordinary
             case and saying so on every card would be noise. --}}
        @if ($card->effective_from || $card->effective_until)
            <div class="flex gap-2">
                <dt class="text-faint w-[86px] shrink-0">{{ __('shift_rules.sections.dates') }}</dt>
                <dd class="text-ink min-w-0">
                    {{ $card->effective_from?->translatedFormat('j M Y') ?? '—' }}
                    –
                    {{ $card->effective_until?->translatedFormat('j M Y') ?? '—' }}
                </dd>
            </div>
        @endif
    </dl>

    <div class="flex flex-wrap items-center gap-2 mt-4 pt-3 border-t border-line">
        <a href="{{ route('settings.shift-rules.index', ['edit' => $card->id]) }}"
           class="styledesk_action styledesk_action--sm">{{ __('common.edit') }}</a>

        <form method="POST" action="{{ route('settings.shift-rules.status', $card) }}">
            @csrf
            @method('PATCH')
            <button type="submit" class="styledesk_action styledesk_action--sm">
                {{ $card->isActive() ? __('shift_rules.deactivate') : __('shift_rules.activate') }}
            </button>
        </form>

        <form method="POST" action="{{ route('settings.shift-rules.duplicate', $card) }}">
            @csrf
            <button type="submit" class="styledesk_action styledesk_action--sm">
                {{ __('shift_rules.duplicate') }}
            </button>
        </form>

        {{-- Delete is offered only on a rule nobody is on.

             Hidden rather than shown and refused: an action that is always
             there and sometimes fails teaches the reader to expect failure.
             The schedules that used a rule need it to stay readable, so the
             answer for a rule in use is Deactivate — which is two buttons to
             the left. The server refuses it either way; this only decides
             what is drawn. --}}
        @if ($card->isInUse())
            <span class="ml-auto text-[11px] text-faint">
                {{ trans_choice('shift_rules.assigned_count', $card->assignedStaffCount(), ['count' => $card->assignedStaffCount()]) }}
            </span>
        @else
            {{-- Asks first, through the shared confirmation dialog. --}}
            <form method="POST" action="{{ route('settings.shift-rules.destroy', $card) }}" class="ml-auto">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="styledesk_action styledesk_action--sm styledesk_action--remove"
                        data-confirm="{{ __('shift_rules.delete_confirm', ['name' => $card->name]) }}"
                        data-confirm-title="{{ __('shift_rules.delete_title') }}"
                        data-confirm-label="{{ __('common.delete') }}"
                        data-confirm-tone="danger">
                    {{ __('common.delete') }}
                </button>
            </form>
        @endif
    </div>
</article>
