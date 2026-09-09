{{-- How the business is doing.

     Takings come from payments rather than from booking totals: a booking
     worth two hundred that nobody has paid for is not two hundred pounds of
     revenue, and a figure that says otherwise is believed once. --}}
@php $p = $data->performance(); @endphp

<section class="sd-card p-5">
  <h2 class="text-[15px] font-semibold text-head">{{ __('dashboard.performance.title') }}</h2>

  @php
      /* How strongly the tile is washed with its own colour. */
      $fill = '20%';

      /* One tile per figure, each with its own edge.

         The colours are not decoration: they are the same palette the
         badges use, so a reader who has learnt that amber means "wants
         attention" elsewhere in StyleDesk reads it the same way here.
         Money owed is amber; money taken is green. */
      /* One colour per tile, used twice: solid on the edge and at a fifth
         of its strength behind the figure. Written as one value rather than
         two so the pair can never drift — a tile with a green border and a
         blue wash is the sort of thing that survives a year. */
      $tiles = [
          ['label' => __('dashboard.performance.today'), 'value' => $money($p['today_minor']), 'tint' => '#22c55e', 'big' => true],
          ['label' => __('dashboard.performance.month'), 'value' => $money($p['month_minor']), 'tint' => '#3b82f6', 'big' => true, 'change' => true],
          ['label' => __('dashboard.performance.outstanding'), 'value' => $money($p['outstanding_minor']), 'tint' => '#f97316', 'big' => true],
          ['label' => __('dashboard.performance.bookings'), 'value' => $p['bookings_month'], 'tint' => '#8b5cf6'],
          ['label' => __('dashboard.performance.average'), 'value' => $money($p['average_minor']), 'tint' => '#6366f1'],
      ];
  @endphp

  {{-- All five across, so the month is read in one sweep rather than as
       two rows with a break in the middle. Two-up on a phone, where five
       abreast would be five slivers. --}}
  <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-3 mt-4">
    @foreach ($tiles as $tile)
      {{-- The tile's own colour: solid on the edge, and behind the figure
           at the opacity below. One value drives both, so the pair cannot
           drift into a green border over a blue wash.

           `$fill` is the knob. 20% of a saturated hue still reads as a firm
           pastel; drop it toward 8% for a wash you have to look for. --}}
      <div class="rounded-lg border px-4 py-3"
           style="border-color: {{ $tile['tint'] }}; background-color: color-mix(in srgb, {{ $tile['tint'] }} {{ $fill }}, #fff)">
        <span class="block">
        <p class="text-[12px] font-semibold text-sub">{{ $tile['label'] }}</p>
        <p class="{{ ($tile['big'] ?? false) ? 'text-[19px]' : 'text-[17px]' }} font-bold text-head leading-tight mt-0.5">
          {{ $tile['value'] }}
        </p>

        {{-- Against the same stretch of last month, not the whole of it: on
             the third, a full month is not a comparison. --}}
        @if ($tile['change'] ?? false)
          @if ($p['change_percent'] === null)
            <p class="text-[11px] text-faint mt-0.5">{{ __('dashboard.performance.no_comparison') }}</p>
          @else
            <p class="text-[11px] mt-0.5 {{ $p['change_percent'] >= 0 ? 'text-success' : 'text-danger' }}">
              {{ $p['change_percent'] > 0 ? '+' : '' }}{{ $p['change_percent'] }}%
              <span class="text-faint">{{ __('dashboard.performance.change') }}</span>
            </p>
          @endif
        @endif
        </span>
      </div>
    @endforeach
  </div>
</section>
