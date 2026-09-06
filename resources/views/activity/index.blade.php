@extends('layouts.app')

@section('title', __('activity.title'))

@section('content')
  {{-- A page rather than the drawer this started as. The app bar opens it in
       its own tab, so whatever somebody was working on is still there when
       they have finished reading. --}}
  <main class="w-full px-6 lg:px-8 pt-5 pb-[100px]">

    <header class="flex flex-wrap items-start gap-4">
      <div class="min-w-0 flex-1">
        <h1 class="text-[22px] sm:text-[24px] font-bold text-head tracking-tight">{{ __('activity.title') }}</h1>
        <p class="text-[13px] text-sub mt-1.5 leading-relaxed">{{ __('activity.intro') }}</p>
      </div>
    </header>

    {{-- One symbol per activity type. The type is decided by data at runtime,
         which is why these cannot be the Blade icon component: the component
         runs on the server and the choice is made in the browser. --}}
    <svg width="0" height="0" aria-hidden="true" focusable="false" style="position:absolute">
      <defs>
        @foreach (\App\Support\ActivityStream::KINDS as $kind => $meta)
          {!! \App\Support\Icon::symbol($meta['icon'], 'act-'.$kind) !!}
        @endforeach
      </defs>
    </svg>

    @php
      $activityProps = [
          'urls' => [
              'index' => route('activity.feed'),
              'read' => route('activity.read'),
          ],
          'unread' => $unread,
          'labels' => __('activity'),
      ];
    @endphp

    <div class="mt-5" data-vue-component="ActivityFeed" data-props='@json($activityProps)'></div>
  </main>
@endsection
