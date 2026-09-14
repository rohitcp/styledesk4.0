{{--
    Whose record this is.

    The name, the reference, the status and the handful of facts that identify
    somebody — shared by the client profile and the Loyalty module's page for
    the same person, so the two cannot drift into introducing them differently.

    `identityActions` names the view that fills the action row. It is the only
    part that differs between the two: where "back" goes, and what the primary
    action is.

    One markup, two layouts, decided at 1024px by styledesk_identity. At and
    above it: the photo beside the name, the actions hard right on the name's
    own line. Below it: the actions lead as a centred row of their own, then
    the photo, then the name, then the chips. The actions are a sibling of the
    photo rather than a child of the name row, because that is the only
    arrangement `order` can rearrange into both.
--}}
@php
    /* The format the business writes names in. Passed where the page already
       has the settings to hand; read for itself where it does not, rather
       than making every caller fetch them. */
    $identityName = $client->displayName($clientSettings->name_format ?? null);
@endphp

    <header class="styledesk_identity mt-3 xl:shrink-0">
      {{-- First in the source, and first on the small layout. On the wide one
           `order` puts it back at the end of the row, which costs a reader
           using the keyboard three stops before the name and buys every
           reader on a phone the actions without a scroll. --}}
      <div class="styledesk_actionrow styledesk_identity__actions">
        @include($identityActions)
      </div>

      <span class="sd-avatar styledesk_avatar--identity styledesk_identity__avatar" aria-hidden="true">{{ $client->initials() }}</span>

      <div class="styledesk_identity__body">
        {{-- Name and reference together: they are quoted together on the
             phone and printed together on a receipt. --}}
        <div class="flex flex-wrap items-center gap-2.5">
          <h1 class="text-[22px] sm:text-[24px] font-bold text-head tracking-tight leading-tight min-w-0 truncate">{{ $identityName }}</h1>
          <span class="styledesk_metachip styledesk_metachip--ref font-mono">{{ $client->client_ref }}</span>
        </div>

        {{-- The facts that identify them, as chips of one shape. Each carries
             its value rather than its label — "+1 202-555-1043" says what it
             is, where "Primary phone" makes the reader open something to find
             out. Only the ones that do something are links.

             justify-start rather than the default: they flow from the left
             and wrap onto a second line, never spreading to fill the row. --}}
        <div class="flex flex-wrap justify-start items-center gap-2 mt-2">
          {{-- A dot before the word: status is the one chip here that is a
               state rather than a value, and the dot is what says so at a
               glance among five that all look alike. --}}
          <span class="styledesk_metachip {{ $client->statusClass() }}">
            <span class="styledesk_statusdot" aria-hidden="true"></span>
            {{ $client->statusLabel() }}
          </span>

          <span class="styledesk_metachip styledesk_metachip--since">
            {{ __('clients.module.workspace.client_since', ['date' => $client->created_at->isoFormat('MMM Y')]) }}
          </span>

          {{-- Where the record came from, for the records that know.
               Null on everything added before it was written down, and shown
               as nothing rather than as a guess. --}}
          @if ($client->source && ($sourceLabel = App\Support\ClientOptions::creationSources()[$client->source] ?? null))
            <span class="styledesk_metachip styledesk_metachip--since">
              {{ $sourceLabel }}
            </span>
          @endif

          @if ($client->date_of_birth)
            <span class="styledesk_metachip styledesk_metachip--dob">
              {{ __('clients.module.workspace.dob', ['date' => $client->date_of_birth->isoFormat('D MMM Y')]) }}
            </span>
          @endif

          {{-- The phone and the email used to sit here.

               They are the two chips nobody reads off a header: both are in
               the Contact card a column away, under labels, beside the copy
               buttons and the "view all numbers" link that make them usable.
               Repeated here they only crowded the facts that have nowhere
               else to be — the birthday, and which branch they are seen at. --}}

          @if ($client->preferredLocation)
            <a href="{{ route('settings.locations.show', $client->preferredLocation) }}"
               class="styledesk_metachip styledesk_metachip--place">
              {{ $client->preferredLocation->name }}
            </a>
          @endif

          @if (filled($client->preferred_name) && $client->preferred_name !== $client->first_name)
            <span class="styledesk_metachip">
              {{ __('clients.module.workspace.identity.preferred_name', ['name' => $client->preferred_name]) }}
            </span>
          @endif
        </div>
      </div>
    </header>
