@extends('layouts.app')

@section('title', __('resources.board.title'))

@section('content')
  {{-- The same header shape as the resources listing and the utilization
       board, because all three are the same module read a different way.
       Everything below it is one Vue island: the chart, the filters, the
       booking panel and the resource card all read one day, and three copies
       of "what is this room doing" is three that can disagree.

       The props are built above rather than inline: a directive argument with
       a comma inside brackets does not parse, because Blade counts brackets
       rather than reading PHP. --}}
  <main class="w-full px-6 lg:px-8 pt-5 pb-[100px]">

    <header class="flex flex-wrap items-start gap-4">
      <div class="min-w-0 flex-1">
        <h1 class="text-[22px] sm:text-[24px] font-bold text-head tracking-tight">
          {{ __('resources.board.title') }}
        </h1>
        <p class="text-[14px] font-semibold text-ink mt-1.5">{{ __('resources.board.subtitle') }}</p>
        <p class="text-[13px] text-sub mt-1 leading-relaxed">{{ __('resources.board.intro') }}</p>
      </div>
    </header>

    @php
      $availabilityProps = [
          'urls' => [
              'data' => route('resources.availability.data'),
              'booking' => route('resources.availability.booking', ['booking' => ':booking']),
          ],
          /* The day itself, rendered with the page so the chart opens drawn
             rather than opening empty and then filling. */
          'day' => $availability,
          'locations' => $locations->map(fn ($location) => ['id' => $location->id, 'name' => $location->name])->all(),
          'categories' => $categories->map(fn ($category) => ['id' => $category->id, 'name' => $category->name])->all(),
          /* The server's today. The arrows and the picker work out every
             other day from it, and they must do it from the same clock the
             server answered with or the two quietly disagree — by a timezone
             in production, and by whatever the machine says on a laptop. */
          'today' => $today,
          'filters' => ['date' => $day, 'location' => $locationId],
          'labels' => __('resources.board'),
      ];
    @endphp

    <div class="mt-5" data-vue-component="ResourceAvailability" data-props='@json($availabilityProps)'></div>
  </main>
@endsection
