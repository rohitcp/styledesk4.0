{{--
    What the next person must read before touching this client.

    The one place a coloured block is still worth its weight: a warning about
    a sensitive scalp is worth nothing if it reads like every other field.
--}}
@if ($canViewNotes && ($importantNotes->isNotEmpty() || filled($client->notes)))
    <div class="rounded-lg border border-amber-300 bg-amber-50 p-3 lg:mb-1">
        <div class="flex items-center gap-2">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" class="text-amber-600 shrink-0" aria-hidden="true">
                <path d="M12 4.5l8 14.5H4l8-14.5z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                <path d="M12 10v4M12 16.5v.4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <h2 class="text-[12px] font-semibold uppercase tracking-wide text-amber-800">{{ __('clients.module.workspace.important.title') }}</h2>
        </div>

        @foreach ($importantNotes as $note)
            <p class="text-[13px] text-amber-900 mt-2 leading-relaxed whitespace-pre-line">{{ $note->body }}</p>
            <p class="text-[11px] text-amber-700 mt-0.5">{{ $note->authorName() }} · {{ $note->created_at->isoFormat('D MMM Y') }}</p>
        @endforeach

        {{-- The client's own notes field, which the business configured and
             the edit form collects. Labelled, so the two kinds of note are
             not read as one thing said twice. --}}
        @if (filled($client->notes))
            <div class="@if ($importantNotes->isNotEmpty()) mt-3 pt-3 border-t border-amber-200 @else mt-2 @endif">
                <p class="text-[11px] uppercase tracking-wide text-amber-700 font-semibold">{{ __('clients.module.workspace.profile_note') }}</p>
                <p class="text-[13px] text-amber-900 mt-1 leading-relaxed whitespace-pre-line">{{ $client->notes }}</p>
            </div>
        @endif
    </div>
@endif
