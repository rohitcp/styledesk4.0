{{--
    The header's More actions menu.

    Everything a reader might do to this client that is not the primary
    action, in one place rather than scattered down the page. Destructive
    entries sit last, behind their own separator: a menu where Archive is one
    row above Edit is a menu that will eventually be misread.
--}}
<span class="styledesk_rowmenu" data-rowmenu>
    <button type="button" class="styledesk_action styledesk_action--icon" data-rowmenu-button
            aria-haspopup="true" aria-expanded="false"
            data-tip="{{ __('clients.module.workspace.identity.more_actions') }}"
            aria-label="{{ __('clients.module.workspace.identity.more_actions') }}">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <circle cx="5" cy="12" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="19" cy="12" r="1.7"/>
        </svg>
    </button>

    <span class="styledesk_rowmenu__pop" data-rowmenu-pop hidden role="menu">
        @if ($canEdit)
            <a href="{{ route('clients.edit', $client) }}" class="styledesk_rowmenu__item" role="menuitem">
                <x-icon name="pen-to-square" size="14" />
                {{ __('clients.module.workspace.identity.edit_client') }}
            </a>
        @endif

        @if ($canBook)
            <a href="{{ route('bookings.create', ['client' => $client->id]) }}"
               class="styledesk_rowmenu__item" role="menuitem">
                <x-icon name="calendar-check" size="14" />
                {{ __('clients.module.workspace.quick.create_booking') }}
            </a>
        @endif

        @if ($canAddNotes)
            <button type="button" class="styledesk_rowmenu__item w-full" role="menuitem"
                    data-open-tab="notes" data-focus="#noteBody">
                <x-icon name="plus" size="14" />
                {{ __('clients.module.workspace.quick.add_note') }}
            </button>
        @endif

        @if ($canEdit && ! $client->isArchived())
            {{-- Its own route, taking the status alone: the update route
                 builds its rules from the whole field configuration, and a
                 menu item posting there would rewrite the rest of the
                 record. --}}
            <form method="POST" action="{{ route('clients.status', $client) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status"
                       value="{{ $client->status === App\Models\Client::STATUS_ACTIVE ? App\Models\Client::STATUS_INACTIVE : App\Models\Client::STATUS_ACTIVE }}">

                <button type="submit" class="styledesk_rowmenu__item w-full" role="menuitem">
                    <x-icon name="user" size="14" />
                    {{ $client->status === App\Models\Client::STATUS_ACTIVE
                        ? __('clients.module.workspace.identity.mark_inactive')
                        : __('clients.module.workspace.identity.mark_active') }}
                </button>
            </form>
        @endif

        @if ($canArchive)
            <span class="styledesk_rowmenu__rule" role="separator"></span>

            <form method="POST" action="{{ route('clients.archive', $client) }}">
                @csrf
                @method('PATCH')
                <button type="submit" role="menuitem"
                        class="styledesk_rowmenu__item w-full {{ $client->isArchived() ? '' : 'styledesk_rowmenu__item--danger' }}"
                        data-confirm="{{ $client->isArchived()
                            ? __('clients.module.restore_confirm', ['name' => $name])
                            : __('clients.module.archive_confirm', ['name' => $name]) }}"
                        data-confirm-title="{{ $client->isArchived() ? __('clients.module.restore') : __('clients.module.archive') }}"
                        data-confirm-label="{{ $client->isArchived() ? __('clients.module.restore') : __('clients.module.archive') }}"
                        data-confirm-tone="{{ $client->isArchived() ? 'brand' : 'danger' }}">
                    <x-icon name="{{ $client->isArchived() ? 'calendar-check' : 'box' }}" size="14" />
                    {{ $client->isArchived() ? __('clients.module.restore') : __('clients.module.archive') }}
                </button>
            </form>
        @endif

        {{-- Deleting a client is deliberately absent rather than merely
             permission-gated: §11 keeps an archived client in every
             appointment and report that names them, and removing the row
             would take that history with it. --}}
    </span>
</span>
