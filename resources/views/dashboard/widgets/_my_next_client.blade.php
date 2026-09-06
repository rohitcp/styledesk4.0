{{-- The most prominent thing on a provider's screen.

     They open the page to answer one question — who am I seeing next, and
     is there anything I should know before they sit down. --}}
@php $next = $data->myNextClient(); @endphp

<section class="sd-card p-5">
  <h2 class="text-[15px] font-semibold text-head">{{ __('dashboard.my_next_client.title') }}</h2>

  @if ($next === null)
    <p class="text-[13px] text-sub mt-3">{{ __('dashboard.my_next_client.none') }}</p>
    <p class="text-[12px] text-faint mt-1">{{ __('dashboard.my_next_client.none_hint') }}</p>
  @else
    <div class="mt-3 flex flex-wrap items-start gap-4">
      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
          <p class="text-[20px] font-bold text-head">{{ $next->clientName() }}</p>

          {{-- Already in the building, which changes what the provider does
               next: they are not waiting for reception to call. --}}
          @if ($next->status === 'arrived')
            <span class="styledesk_badge styledesk_badge--info">{{ __('dashboard.my_next_client.here') }}</span>
          @endif
        </div>

        <p class="text-[13px] text-sub mt-1">
          {{ collect([
              \App\Support\TimeFormat::time($next->startsAt()).' – '.\App\Support\TimeFormat::time(substr((string) $next->ends_at, 0, 5)),
              $next->services->pluck('name')->implode(', '),
              $next->resource?->name,
          ])->filter()->join(' · ') }}
        </p>

        {{-- What the diary has noticed about this person. Tags rather than
             notes: a note may be somebody else's private one, and this
             panel is read over a client's shoulder. --}}
        @php $tags = $next->client?->behavioralTags() ?? collect(); @endphp

        @if ($tags->isNotEmpty())
          <div class="flex flex-wrap gap-1.5 mt-2">
            @foreach ($tags as $tag)
              <span class="styledesk_badge styledesk_badge--note">{{ $tag['label'] }}</span>
            @endforeach
          </div>
        @endif
      </div>

      {{-- Not shrink-0, for the reason set out in dashboard.blade.php: a flex
           item that may not shrink is sized to its own content, so these two
           would stay on one line and run off the side of a narrow card rather
           than wrapping under the client's name. --}}
      <div class="flex flex-wrap gap-2">
        @if ($next->client)
          <a href="{{ route('clients.show', $next->client) }}" class="styledesk_action">{{ __('dashboard.my_next_client.view_client') }}</a>
        @endif
        <a href="{{ route('bookings.show', $next) }}" class="styledesk_action">{{ __('dashboard.my_next_client.view_booking') }}</a>
      </div>
    </div>
  @endif
</section>
