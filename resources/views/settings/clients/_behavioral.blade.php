{{--
    Behavioural tags: the ones StyleDesk works out for itself.

    Kept apart from the manual tags above, and read-only apart from the
    switch. "VIP" is a judgement someone made; "Frequent booker" is a count,
    and a screen that let a business rename the second would break every rule
    and report that referred to it.
--}}
<div class="space-y-5">
    {{-- Said once, at the top. These tags are counted from bookings,
         attendance and spending, none of which exist yet — a business is
         entitled to know that before it spends ten minutes choosing which
         ones to apply. --}}
    <div class="sd-alert sd-alert--info" role="status">
        <div class="flex items-start gap-2.5">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" class="shrink-0 mt-px" aria-hidden="true"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/><path d="M12 11v5.5M12 8v.4" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
            <p class="min-w-0">{{ __('clients.behavioral.pending') }}</p>
        </div>
    </div>

    @foreach ($behavioralTags as $category => $tags)
        <div @if (! $loop->first) class="pt-4 border-t border-line" @endif>
            <h3 class="text-[13px] font-medium text-ink">
                {{ config('behavioral_tags.categories.'.$category, __('clients.behavioral.other')) }}
            </h3>

            <ul class="mt-2 rounded-lg border border-line divide-y divide-line">
                @foreach ($tags as $tag)
                    <li class="flex flex-wrap items-center gap-3 px-3 py-2.5">
                        <span class="min-w-0 flex-1">
                            <span class="block text-[13px] {{ $tag->is_active ? 'text-ink' : 'text-faint line-through' }}">
                                {{ $tag->label() }}
                            </span>

                            {{-- The rule, in the open rather than behind a
                                 dialog: a threshold nobody can see is a
                                 threshold nobody can trust. --}}
                            <span class="block text-[12px] text-sub mt-0.5">{{ $tag->rule() }}</span>
                        </span>

                        <span class="styledesk_badge {{ $tag->is_active ? 'styledesk_badge--active' : 'styledesk_badge--soon' }} shrink-0">
                            {{ $tag->is_active ? __('common.active') : __('common.inactive') }}
                        </span>

                        <form method="POST" action="{{ route('settings.clients.behavioral.toggle', $tag) }}" class="shrink-0">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="sd-iconbtn grid sd-tip"
                                    data-tip="{{ $tag->is_active ? __('clients.deactivate') : __('clients.activate') }}"
                                    aria-label="{{ $tag->is_active ? __('clients.deactivate') : __('clients.activate') }} — {{ $tag->label() }}">
                                @if ($tag->is_active)
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/><path d="M8.5 12h7" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
                                @else
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/><path d="M8.5 12.2l2.4 2.4 4.6-5.2" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                @endif
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach

    <p class="text-[12px] text-sub leading-relaxed">{{ __('clients.behavioral.note') }}</p>
</div>
