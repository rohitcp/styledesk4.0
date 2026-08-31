{{--
    The staff member's header and tabs, shared by all four tabs.

    One copy, because the header has to be the same on every one of them: a
    summary that shifted as the reader moved between Overview and Schedule
    would read as a different person's page. `$tab` says which tab is being
    drawn; everything else comes from the record.

    The same header the client workspace uses — styledesk_identity, one photo,
    the name and a row of chips, the actions hard right — rather than a second
    arrangement of the same facts. Two record pages in one application that
    lay their headers out differently read as two applications, and the chips
    say more in one line than the labelled strip they replaced said in two.
--}}
@php
    use App\Support\StaffSection;

    $opts = config('staff');
    $avatarUrl = $staff->avatar_path ? Storage::disk('brand')->url($staff->avatar_path) : null;

    /* Only when it differs, or someone whose preferred name is their first
       name reads their own name twice. */
    $legalName = trim($staff->first_name.' '.$staff->last_name);
    $lastLogin = $staff->user?->last_login_at;
@endphp

      {{-- One markup, two layouts, decided at 1024px by styledesk_identity.

           At and above it: the photo beside the name, the actions hard right
           on the name's own line. Below it: the actions lead as a centred row
           of their own, then the photo, then the name, then the chips.

           The actions are a sibling of the photo rather than a child of the
           name row, because that is the only arrangement `order` can
           rearrange into both. --}}
      <header class="styledesk_identity mt-3">
        {{-- First in the source, and first on the small layout. On the wide
             one `order` puts it back at the end of the row. --}}
        <div class="styledesk_actionrow styledesk_identity__actions">
          {{-- Labelled where there is room, a bare arrow where there is not.
               The accessible name says where it goes either way. --}}
          <a href="{{ StaffSection::route('index') }}" data-tip="{{ __('staff.title') }}"
             aria-label="{{ __('staff.title') }}"
             class="styledesk_action styledesk_action--shrinklabel shrink-0">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <span class="styledesk_action__label">{{ __('common.back') }}</span>
          </a>

          @can('update', $staff)
            <a href="{{ StaffSection::route('edit', $staff) }}"
               class="h-9 px-4 inline-flex items-center rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors min-w-0 truncate">
              {{ __('staff.edit_staff') }}
            </a>
          @endcan

          {{-- More actions. The ones that change what somebody is rather than
               what is recorded about them — and the one that removes them —
               live behind a menu, so the button beside Edit is not a row of
               five equally-loud choices. --}}
          @canany(['update', 'delete'], $staff)
            <span class="styledesk_rowmenu" data-rowmenu>
              <button type="button" class="styledesk_action styledesk_action--icon" data-rowmenu-button
                      aria-haspopup="true" aria-expanded="false"
                      data-tip="{{ __('staff.more_actions') }}" aria-label="{{ __('staff.more_actions') }}">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                  <circle cx="5" cy="12" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="19" cy="12" r="1.7"/>
                </svg>
              </button>

              <span class="styledesk_rowmenu__pop" data-rowmenu-pop hidden role="menu">
                @can('update', $staff)
                  <form method="POST" action="{{ StaffSection::route('status', $staff) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="styledesk_rowmenu__item w-full" role="menuitem">
                      <x-icon name="user" size="14" />
                      {{ $staff->is_active ? __('staff.deactivate') : __('staff.activate') }}
                    </button>
                  </form>

                  {{-- Away, not gone: kept apart from Inactive because a rota
                       has to tell somebody coming back from somebody who has
                       left. --}}
                  @unless ($staff->status() === 'on-leave')
                    <form method="POST" action="{{ StaffSection::route('status', $staff) }}">
                      @csrf
                      @method('PATCH')
                      <input type="hidden" name="to" value="on-leave">
                      <button type="submit" class="styledesk_rowmenu__item w-full" role="menuitem">
                        <x-icon name="calendar-xmark" size="14" />
                        {{ __('staff.set_on_leave') }}
                      </button>
                    </form>
                  @endunless
                @endcan

                @can('delete', $staff)
                  <span class="styledesk_rowmenu__rule" role="separator"></span>

                  <form method="POST" action="{{ StaffSection::route('destroy', $staff) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="styledesk_rowmenu__item styledesk_rowmenu__item--danger w-full"
                            role="menuitem"
                            data-confirm="{{ __('staff.delete_confirm', ['name' => $staff->displayName()]) }}"
                            data-confirm-title="{{ __('common.delete') }}"
                            data-confirm-label="{{ __('common.delete') }}"
                            data-confirm-tone="danger">
                      <x-icon name="trash-can" size="14" />
                      {{ __('common.delete') }}
                    </button>
                  </form>
                @endcan
              </span>
            </span>
          @endcanany
        </div>

        <span class="sd-avatar styledesk_avatar--identity styledesk_identity__avatar" aria-hidden="true">
          @if ($avatarUrl)
            <img src="{{ $avatarUrl }}" alt="">
          @else
            {{ $staff->initials() }}
          @endif
        </span>

        <div class="styledesk_identity__body">
          {{-- Name and staff ID together: the ID is what tells two people of
               the same name apart, and it is quoted with the name. --}}
          <div class="flex flex-wrap items-center gap-2.5">
            <h1 class="text-[22px] sm:text-[24px] font-bold text-head tracking-tight leading-tight min-w-0 truncate">
              {{ $staff->displayName() }}
            </h1>
            @if ($staff->employee_ref)
              <span class="styledesk_metachip styledesk_metachip--ref font-mono">{{ $staff->employee_ref }}</span>
            @endif
          </div>

          {{-- The facts that identify them, as chips of one shape. Each
               carries its value rather than its label — "Owner" says what it
               is, where "Role" makes the reader open something to find out.
               Only the ones that do something are links. --}}
          <div class="flex flex-wrap justify-start items-center gap-2 mt-2">
            {{-- A dot before the word: status is the one chip here that is a
                 state rather than a value, and the dot is what says so at a
                 glance among half a dozen that all look alike. --}}
            <span class="styledesk_metachip {{ $staff->statusClass() }}">
              <span class="styledesk_statusdot" aria-hidden="true"></span>
              {{ $staff->statusLabel() }}
            </span>

            @if ($staff->provides_services)
              <span class="styledesk_metachip">{{ __('staff.profile.bookable') }}</span>
            @endif

            @if (! $staff->login_enabled)
              <span class="styledesk_metachip">{{ __('staff.profile.no_login') }}</span>
            @endif

            @if ($staff->job_title)
              <span class="styledesk_metachip">{{ $staff->job_title }}</span>
            @endif

            @if ($staff->roleRecord)
              <span class="styledesk_metachip styledesk_metachip--since">{{ $staff->roleRecord->name }}</span>
            @endif

            @if ($staff->pronouns)
              <span class="styledesk_metachip">{{ $opts['pronouns'][$staff->pronouns] ?? $staff->pronouns }}</span>
            @endif

            @if ($legalName !== '' && $legalName !== $staff->displayName())
              <span class="styledesk_metachip">{{ $legalName }}</span>
            @endif

            @if ($staff->phone)
              <a href="tel:{{ $staff->phone }}" class="styledesk_metachip styledesk_metachip--phone"
                 data-tip="{{ __('staff.profile.summary_phone') }}">{{ $staff->phone }}</a>
            @endif

            @if ($staff->email)
              <a href="mailto:{{ $staff->email }}" class="styledesk_metachip styledesk_metachip--email"
                 data-tip="{{ __('staff.profile.summary_email') }}">{{ $staff->email }}</a>
            @endif

            <span class="styledesk_metachip styledesk_metachip--place">
              {{ $staff->location?->name ?? __('staff.all_locations') }}
            </span>

            @if ($lastLogin)
              <span class="styledesk_metachip styledesk_metachip--dob">
                {{ __('staff.profile.last_login') }} {{ $lastLogin->diffForHumans() }}
              </span>
            @endif
          </div>
        </div>
      </header>

      {{-- A line, not a gap. It separates the header from the tabs at the cost
           of one pixel, where the equivalent whitespace would be 40. --}}
      <hr class="mt-5 border-line">

      {{-- The four tabs. Links rather than panels: each is its own address, so
           a reader can bookmark somebody's schedule and the back button means
           what it says. The header above stays put across all four, which is
           what makes this one workspace rather than four pages that happen to
           be about the same person. --}}
      <nav class="mt-4 flex flex-wrap items-center gap-1" aria-label="{{ $staff->displayName() }}">
        @foreach ([
            'show' => __('staff.tabs.overview'),
            'schedule' => __('staff.tabs.schedule'),
            'services' => __('staff.tabs.services'),
            'notes' => __('staff.tabs.notes'),
        ] as $tabRoute => $tabLabel)
          @php $current = ($tab ?? 'show') === $tabRoute; @endphp

          <a href="{{ StaffSection::route($tabRoute, $staff) }}"
             class="styledesk_tab @if ($current) is-active @endif"
             @if ($current) aria-current="page" @endif>{{ $tabLabel }}</a>
        @endforeach
      </nav>
