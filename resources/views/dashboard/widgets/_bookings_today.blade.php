{{-- Today by where each appointment has got to.

     Every number is a link: a count somebody wants to act on should be one
     press from the list behind it, not a figure they then go hunting for. --}}
{{-- No card around it.

     The six tiles are already boxes, and a border around a row of borders
     is a frame nobody needed — it reads as one thing containing six rather
     than six things worth pressing. --}}
<section>
  <h2 class="text-[15px] font-semibold text-head">{{ __('dashboard.bookings_today.title') }}</h2>

  <div class="grid grid-cols-3 lg:grid-cols-6 gap-3 mt-3">
    @foreach ($data->bookingsToday() as $metric)
      <a href="{{ route('bookings.index', $metric['query']) }}"
         class="rounded-lg border border-line px-3 py-2.5 hover:border-brand transition-colors">
        <p class="text-[18px] font-bold text-head leading-tight">{{ $metric['count'] }}</p>
        <p class="text-[11px] text-sub mt-0.5">{{ __('dashboard.bookings_today.'.$metric['key']) }}</p>
      </a>
    @endforeach
  </div>
</section>
