{{-- The running list of notes: who wrote what, when, and who may read it. --}}
@unless ($canViewNotes)
    <p class="text-[13px] text-sub">{{ __('clients.module.workspace.notes.hidden') }}</p>
@else
    <div class="flex flex-wrap items-center gap-3">
        <p class="text-[13px] text-sub min-w-0 flex-1">
            {{ trans_choice('clients.module.workspace.notes.count', $notes->count()) }}
        </p>

        @if ($canAddNotes)
            <button type="button"
                    class="shrink-0 inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors"
                    data-note-compose>
                <x-icon name="plus" size="14" />
                {{ __('clients.module.workspace.notes.add') }}
            </button>
        @endif
    </div>

    <p class="text-[13px] text-sub mt-5 @if ($notes->isNotEmpty()) hidden @endif" data-notes-empty>
        {{ __('clients.module.workspace.notes.none') }}
    </p>

    {{-- Newest first, which is the order the query returns. --}}
    <ul class="mt-4 space-y-2.5" data-notes-list>
        @foreach ($notes as $note)
            @include('clients.partials._note-card')
        @endforeach
    </ul>
@endunless

@if ($canViewNotes && $canAddNotes)
    {{-- The composer.

         A floating panel rather than a form in the page: writing a note is
         something you do *about* what you are looking at, so the profile
         stays visible behind it. It is fixed to the bottom-right on a desktop
         and takes the whole screen on a phone, where a floating window would
         be a smaller screen inside a small screen. --}}
    <div id="noteComposer" class="styledesk_composer" hidden
         data-image-endpoint="{{ route('clients.notes.images', $client) }}"
         data-placeholder="{{ __('clients.module.workspace.notes.placeholder') }}"
         data-strings="{{ json_encode([
             'discardTitle' => __('clients.module.workspace.notes.discard_title'),
             'discard' => __('clients.module.workspace.notes.discard_body'),
             'discardLabel' => __('clients.module.workspace.notes.discard'),
             'keepEditing' => __('clients.module.workspace.notes.keep_editing'),
             'added' => __('clients.module.workspace.notes.added_success'),
             'expand' => __('clients.module.workspace.notes.expand'),
             'restore' => __('clients.module.workspace.notes.restore'),
             'remove' => __('common.remove'),
             'editorLabel' => __('clients.module.workspace.notes.editor_label'),
             'imageUploading' => __('clients.module.workspace.notes.image_uploading'),
             'imageFailed' => __('clients.module.workspace.notes.image_failed'),
             'accessNone' => __('clients.module.workspace.notes.access_none'),
             'accessSummary' => trans_choice('clients.module.workspace.notes.access_summary', 2, ['count' => ':count']),
             'selected' => trans_choice('clients.module.workspace.notes.selected', 2, ['count' => ':count']),
             'removeAccessTitle' => __('clients.module.workspace.notes.remove_access_title'),
             'removeAccess' => __('clients.module.workspace.notes.remove_access'),
         ]) }}">
        <form method="POST" action="{{ route('clients.notes.store', $client) }}" data-note-form>
            @csrf

            <div class="styledesk_composer__head">
                <h2 class="text-[14px] font-semibold" id="noteComposerTitle">{{ __('clients.module.workspace.notes.add') }}</h2>

                {{-- Expand and Restore are one button: it is the same window
                     either way, and two buttons where one has to be hidden is
                     two things that can disagree about which state it is
                     in. --}}
                <button type="button" class="styledesk_composer__icon sd-tip" data-note-expand
                        data-tip="{{ __('clients.module.workspace.notes.expand') }}"
                        data-restore-tip="{{ __('clients.module.workspace.notes.restore') }}"
                        aria-label="{{ __('clients.module.workspace.notes.expand') }}"
                        aria-pressed="false">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true" data-expand-icon>
                        <path d="M14 4h6v6M20 4l-7 7M10 20H4v-6M4 20l7-7" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true" data-restore-icon hidden>
                        <path d="M20 4l-7 7M14 11h6M13 11V5M4 20l7-7M10 13H4M11 13v6" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>

                <button type="button" class="styledesk_composer__icon sd-tip" data-note-close
                        data-tip="{{ __('common.close') }}"
                        aria-label="{{ __('common.close') }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
                </button>
            </div>

            <div class="styledesk_composer__body">
                {{-- The editor writes into this field, so the form posts a
                     body the ordinary way and the server sees the same input
                     whether or not the editor loaded. --}}
                <textarea id="noteBody" name="body" class="sr-only" data-note-input
                          aria-hidden="true" tabindex="-1"></textarea>

                <div class="styledesk_editor">
                    {{-- Stays put while the note scrolls: formatting the
                         bottom of a long note should not mean scrolling back
                         up to find Bold. --}}
                    <div class="styledesk_editor__toolbar" role="toolbar"
                         aria-label="{{ __('clients.module.workspace.notes.toolbar') }}"
                         data-editor-toolbar>
                        @foreach ([
                            ['bold', 'notes.bold', '<path d="M7 5h6a3.5 3.5 0 010 7H7zM7 12h7a3.5 3.5 0 010 7H7z" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/>'],
                            ['italic', 'notes.italic', '<path d="M15 5h-4M13 19H9M14 5l-4 14" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>'],
                            ['underline', 'notes.underline', '<path d="M7 4v7a5 5 0 0010 0V4M5 20h14" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>'],
                            ['heading', 'notes.heading', '<path d="M6 5v14M18 5v14M6 12h12" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>'],
                            ['bulletList', 'notes.bullet_list', '<path d="M9 6h11M9 12h11M9 18h11" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/><circle cx="4.5" cy="6" r="1.4" fill="currentColor"/><circle cx="4.5" cy="12" r="1.4" fill="currentColor"/><circle cx="4.5" cy="18" r="1.4" fill="currentColor"/>'],
                            ['orderedList', 'notes.ordered_list', '<path d="M9 6h11M9 12h11M9 18h11" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/><path d="M4 4.5h1V8M3.5 12.5h2L3.5 15.5h2M3.5 17.5h2v1.5h-2v1.5h2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>'],
                            ['link', 'notes.link', '<path d="M10 13.5a3.5 3.5 0 005 0l3-3a3.5 3.5 0 00-5-5l-1 1M14 10.5a3.5 3.5 0 00-5 0l-3 3a3.5 3.5 0 005 5l1-1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>'],
                            ['image', 'notes.image', '<rect x="4" y="5.5" width="16" height="13" rx="2" stroke="currentColor" stroke-width="1.8"/><circle cx="9" cy="10" r="1.5" stroke="currentColor" stroke-width="1.6"/><path d="M5 16l4-4 3.5 3.5L15 13l4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>'],
                            ['clear', 'notes.clear_formatting', '<path d="M8 6h11M13 6l-3 12M6 18h7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M16 14l5 5M21 14l-5 5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>'],
                            ['undo', 'notes.undo', '<path d="M9 8H5V4M5.5 8a7 7 0 113 10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>'],
                            ['redo', 'notes.redo', '<path d="M15 8h4V4M18.5 8a7 7 0 10-3 10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>'],
                        ] as [$command, $key, $path])
                            <button type="button" class="styledesk_editor__button sd-tip" data-command="{{ $command }}"
                                    data-tip="{{ __('clients.module.workspace.'.$key) }}"
                                    aria-label="{{ __('clients.module.workspace.'.$key) }}">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true">{!! $path !!}</svg>
                            </button>
                        @endforeach
                    </div>

                    <div class="styledesk_editor__surface" data-editor></div>

                    {{-- The link field lives in the composer rather than in a
                         browser prompt, which cannot be styled and cannot be
                         reached by the keyboard the way the rest of this can. --}}
                    <div class="styledesk_editor__link" data-link-panel hidden>
                        <label for="noteLink" class="sr-only">{{ __('clients.module.workspace.notes.link_url') }}</label>
                        <input type="url" id="noteLink" class="sd-input !h-9" data-link-input
                               placeholder="https://">
                        <button type="button" class="styledesk_action styledesk_action--sm" data-link-apply>{{ __('common.save') }}</button>
                        <button type="button" class="styledesk_action styledesk_action--sm" data-link-cancel>{{ __('common.cancel') }}</button>
                    </div>

                    <p class="styledesk_editor__status" data-editor-status hidden></p>

                    <button type="button" class="styledesk_action styledesk_action--sm mt-2" data-editor-retry hidden>
                        {{ __('common.retry') }}
                    </button>

                    <input type="file" class="sr-only" data-editor-file
                           accept="image/jpeg,image/png,image/webp" tabindex="-1" aria-hidden="true">
                </div>

                <p class="text-[12px] text-danger px-4 pt-2" data-note-error hidden></p>

                <div class="styledesk_composer__settings">
                {{-- Switches rather than choice cards: each is a setting that
                     takes effect as itself, not one option being picked from
                     several. --}}
                <div class="grid sm:grid-cols-2 gap-2">
                    <x-toggle name="is_important" :label="__('clients.module.workspace.notes.important')"
                              :hint="__('clients.module.workspace.notes.important_hint')" />

                    <x-toggle name="is_private" :label="__('clients.module.workspace.notes.private')"
                              :hint="__('clients.module.workspace.notes.private_hint')" />
                </div>

                {{-- Only a private note has an audience, so the field only
                     exists once there is one to choose. --}}
                <section class="styledesk_accessbox mt-3" data-note-access hidden>
                    <p class="styledesk_label">{{ __('clients.module.workspace.notes.access_for') }}</p>
                    <p class="text-[12px] text-sub mt-1 leading-relaxed">{{ __('clients.module.workspace.notes.access_hint') }}</p>

                    {{-- One way in, and no search box sitting in the composer
                         for a list that lives somewhere else. --}}
                    <button type="button" class="styledesk_action styledesk_action--sm mt-2.5" data-note-picker-open
                            aria-haspopup="dialog">
                        <x-icon name="user" size="14" />
                        {{ __('clients.module.workspace.notes.manage_access') }}
                    </button>

                    {{-- The choices stay in sight after the modal closes: the
                         answer to "who can read this" is the chips, not a
                         window the reader has to reopen to check. --}}
                    <div class="flex flex-wrap items-center gap-1.5 mt-2.5" data-note-chips></div>

                    <p class="text-[12px] text-faint mt-2" data-note-summary></p>
                </section>

                {{-- Every colleague who could be named, as checkboxes the form
                     posts. They live in the modal, but they belong to this
                     form — the modal is where they are chosen, not a separate
                     thing that has to report its answer back. --}}
                <div id="notePeople" class="styledesk_picker" hidden>
                    <div class="styledesk_picker__scrim" data-note-picker-cancel></div>

                    <div class="styledesk_picker__panel" role="dialog" aria-modal="true"
                         aria-labelledby="notePeopleTitle">
                        <div class="styledesk_picker__head">
                            <h2 id="notePeopleTitle" class="text-[15px] font-semibold text-head flex-1 min-w-0">
                                {{ __('clients.module.workspace.notes.picker_title') }}
                            </h2>

                            <button type="button" class="styledesk_modal__close" data-note-picker-cancel
                                    aria-label="{{ __('common.close') }}">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                            </button>
                        </div>

                        {{-- Fixed above the list, so searching a long team
                             does not mean scrolling back up to type. --}}
                        <div class="styledesk_picker__search">
                            <div class="relative">
                                <span class="styledesk_input__prefix pointer-events-none" aria-hidden="true">
                                    <x-icon name="magnifying-glass" size="14" />
                                </span>
                                <input type="search" class="sd-input styledesk_input--prefixed !h-9" data-note-search
                                       placeholder="{{ __('clients.module.workspace.notes.access_search') }}"
                                       aria-label="{{ __('clients.module.workspace.notes.access_search') }}"
                                       autocomplete="off">
                            </div>

                            <p class="text-[12px] text-sub mt-2 leading-relaxed">
                                {{ __('clients.module.workspace.notes.access_admins') }}
                            </p>
                        </div>

                        {{-- A table rather than a stack of cards: three
                             facts per person, in the same place on every
                             row, so a team of thirty can be read down a
                             column instead of one card at a time. --}}
                        <div class="styledesk_picker__list styledesk_scroll" data-note-panel role="table">
                            <div class="styledesk_peoplerow styledesk_peoplerow--head" role="row" aria-hidden="true">
                                <span>{{ __('clients.module.workspace.notes.column_user') }}</span>
                                <span>{{ __('clients.module.workspace.notes.column_role') }}</span>
                                <span>{{ __('clients.module.workspace.notes.column_access') }}</span>
                            </div>

                            @foreach ($noteAudience as $member)
                                @php $reader = $member->user; @endphp

                                {{-- The whole row is the control, because it
                                     is a <label> around a real checkbox. --}}
                                <label class="styledesk_peoplerow" data-note-option role="row"
                                       data-label="{{ Str::lower($member->name.' '.$member->roleName()) }}"
                                       data-name="{{ $member->name }}"
                                       data-initials="{{ $member->initials() }}"
                                       data-avatar="{{ $reader->avatarUrl() }}">
                                    <span class="flex items-center gap-2.5 min-w-0">
                                        <span class="sd-avatar sd-avatar--sm shrink-0" aria-hidden="true">
                                            @if ($reader->avatarUrl())
                                                <img src="{{ $reader->avatarUrl() }}" alt="">
                                            @else
                                                {{ $member->initials() }}
                                            @endif
                                        </span>

                                        <span class="truncate text-[13px] font-semibold text-head">{{ $member->name }}</span>
                                    </span>

                                    <span class="truncate text-[12.5px] text-sub">{{ $member->roleName() }}</span>

                                    <span class="flex justify-end">
                                        <input type="checkbox" name="access[]" value="{{ $reader->id }}" class="sd-check">
                                    </span>
                                </label>
                            @endforeach

                            <p class="text-[12px] text-sub px-2 py-2" data-note-no-matches hidden>{{ __('clients.module.workspace.notes.access_no_matches') }}</p>
                        </div>

                        <div class="styledesk_modalfoot">
                            <button type="button" data-note-picker-assign
                                    class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                                {{ __('clients.module.workspace.notes.assign') }}
                            </button>

                            <button type="button" class="styledesk_action styledesk_action--sm" data-note-picker-cancel>{{ __('common.cancel') }}</button>

                            <p class="styledesk_modalfoot__note" data-note-count aria-live="polite"></p>
                        </div>

                    </div>
                </div>
                </div>
            </div>

            {{-- The action first, then the way out of it: the reader came
                 here to add a note, and the primary button is what they are
                 looking for. --}}
            <div class="styledesk_composer__foot">
                <button type="submit"
                        class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
                    {{ __('clients.module.workspace.notes.add') }}
                </button>

                <button type="button" class="styledesk_action styledesk_action--sm" data-note-close>{{ __('common.cancel') }}</button>
            </div>
        </form>
    </div>
@endif
