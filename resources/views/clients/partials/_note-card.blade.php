{{--
    One note in the list.

    Rendered here rather than built in JavaScript when a note is added, so
    there is one description of what a note looks like and the card the
    composer inserts cannot drift from the ones the page arrived with.

    The body is printed only when $note->readable. That is the whole privacy
    rule as far as this file is concerned — there is no "hide it with CSS"
    branch, because content that reached the page was never private.

    It is printed unescaped, which is safe for exactly one reason: bodyHtml()
    escapes a plain-text note and returns a rich one that NoteHtml already
    cleaned against an allowlist. Nothing else may put markup in that column.
--}}
@php
    $author = $note->author;
    $mine = $note->created_by === auth()->id();
@endphp

<li @class([
        'styledesk_note',
        'styledesk_note--important' => $note->is_important,
        'styledesk_note--private' => $note->is_private,
    ])
    data-note="{{ $note->id }}">

    {{-- What kind of note this is, said in words as well as in colour. The
         right padding keeps a long pair of badges clear of the Delete icon
         in the corner. --}}
    @if ($note->is_important || $note->is_private)
        <div class="flex flex-wrap items-center gap-1.5 pr-8 mb-2.5">
            @if ($note->is_important)
                <span class="styledesk_notebadge styledesk_notebadge--important">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 4.5l8 14.5H4l8-14.5z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M12 10v3.5M12 16v.4" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
                    {{ __('clients.module.workspace.notes.important_badge') }}
                </span>
            @endif

            @if ($note->is_private)
                <span class="styledesk_notebadge styledesk_notebadge--private">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="5" y="10.5" width="14" height="9" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M8.5 10.5V8a3.5 3.5 0 017 0v2.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    {{ __('clients.module.workspace.notes.private_badge') }}
                </span>
            @endif
        </div>
    @endif

    @if ($canDelete)
        {{-- In the card's own corner, so it lines up with the badges when
             there are any and with the byline when there is not. The icon
             alone: a bordered button in a card's header reads as a second
             card. --}}
        <form method="POST" action="{{ route('clients.notes.destroy', [$client, $note]) }}"
              class="absolute top-2 right-2" data-note-delete>
            @csrf
            @method('DELETE')

            <button type="submit"
                    class="styledesk_iconaction styledesk_iconaction--danger sd-tip"
                    data-confirm="{{ __('clients.module.workspace.notes.delete_confirm') }}"
                    data-confirm-title="{{ __('clients.module.workspace.notes.delete_title') }}"
                    data-confirm-label="{{ __('clients.module.workspace.notes.delete') }}"
                    data-tip="{{ __('clients.module.workspace.notes.delete_tip') }}"
                    aria-label="{{ __('clients.module.workspace.notes.delete_tip') }}">
                {{-- Drawn like the lock on the badge beside it: same
                     20-unit grid, same 1.8 stroke, no fill. Two icons on one
                     card in two different languages read as two different
                     kinds of thing. --}}
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M5 7h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    <path d="M9.5 7V5.5a1 1 0 011-1h3a1 1 0 011 1V7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    <path d="M6.5 7l.8 12.1a1.5 1.5 0 001.5 1.4h6.4a1.5 1.5 0 001.5-1.4L17.5 7" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                    <path d="M10.5 10.5v6.5M13.5 10.5v6.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </button>
        </form>
    @endif

    {{-- Who wrote it: a face, a name and a time, never a name alone. --}}
    <div class="flex items-center gap-2.5 pr-8">
        <span class="sd-avatar sd-avatar--sm shrink-0" aria-hidden="true">
            @if ($author?->avatarUrl())
                <img src="{{ $author->avatarUrl() }}" alt="">
            @else
                {{ $author?->initials() ?? '?' }}
            @endif
        </span>

        <div class="min-w-0 flex-1">
            <p class="text-[13px] font-semibold text-head truncate">
                {{ $mine ? __('clients.module.workspace.notes.yours') : $note->authorName() }}
            </p>
            <p class="text-[12px] text-sub">{{ $note->created_at->isoFormat('D MMM Y · h:mm A') }}</p>
        </div>

    </div>

    @if ($note->is_private && $note->accessUsers->isNotEmpty())
        {{-- Who may read it. Shown to everyone who can see the note exists,
             including readers who cannot open it: knowing who to ask is the
             useful half of being told no. --}}
        <div class="flex items-center gap-2 mt-2.5">
            <span class="text-[12px] text-sub shrink-0">{{ __('clients.module.workspace.notes.access') }}</span>

            <span class="styledesk_avatarstack">
                @foreach ($note->accessUsers->take(4) as $reader)
                    <span class="sd-avatar sd-avatar--sm styledesk_avatarstack__item sd-tip"
                          data-tip="{{ $reader->name }}" aria-label="{{ $reader->name }}">
                        @if ($reader->avatarUrl())
                            <img src="{{ $reader->avatarUrl() }}" alt="">
                        @else
                            {{ $reader->initials() }}
                        @endif
                    </span>
                @endforeach

                @if ($note->accessUsers->count() > 4)
                    <span class="sd-avatar sd-avatar--sm styledesk_avatarstack__item styledesk_avatarstack__more sd-tip"
                          data-tip="{{ $note->accessUsers->skip(4)->pluck('name')->join(', ') }}">
                        +{{ $note->accessUsers->count() - 4 }}
                    </span>
                @endif
            </span>
        </div>
    @endif

    @if ($note->readable)
        {{-- Private notes open on request rather than on arrival: a profile
             left on a screen should not be reading someone's restricted note
             out to the room. --}}
        @if ($note->is_private)
            <div data-note-reveal>
                <button type="button" class="styledesk_action styledesk_action--sm mt-2.5" data-note-view
                        data-show="{{ __('clients.module.workspace.notes.view') }}"
                        data-hide="{{ __('clients.module.workspace.notes.hide') }}"
                        aria-expanded="false" aria-controls="note-body-{{ $note->id }}">
                    {{ __('clients.module.workspace.notes.view') }}
                </button>

                <div id="note-body-{{ $note->id }}" class="styledesk_note__body styledesk_richtext mt-2.5" data-note-body hidden>{!! $note->bodyHtml() !!}</div>
            </div>
        @else
            <div class="styledesk_note__body styledesk_richtext mt-2.5">{!! $note->bodyHtml() !!}</div>
        @endif
    @else
        {{-- No body, and no hint of one. --}}
        <p class="text-[12.5px] text-sub mt-2.5 leading-relaxed">
            {{ __('clients.module.workspace.notes.no_access') }}
        </p>
    @endif
</li>
