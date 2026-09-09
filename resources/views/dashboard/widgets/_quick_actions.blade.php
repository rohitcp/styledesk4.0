{{-- Only what this reader may actually do. An action they cannot take is
     furniture, and one that 403s is worse. --}}
@php
    $actions = array_values(array_filter([
        $user->hasPermission('appointments.create', 'own')
            ? ['label' => __('dashboard.quick_actions.booking'), 'url' => route('bookings.create')] : null,
        $user->hasPermission('clients.create', 'own')
            ? ['label' => __('dashboard.quick_actions.client'), 'url' => route('clients.create')] : null,
        $canCheckIn
            ? ['label' => __('dashboard.quick_actions.checkin'), 'url' => route('bookings.index', ['tab' => 'check-in'])] : null,
        $user->hasPermission('calendar.view', 'own')
            ? ['label' => __('dashboard.quick_actions.calendar'), 'url' => route('bookings.index')] : null,
        $user->hasPermission('staff.create', 'own')
            ? ['label' => __('dashboard.quick_actions.staff'), 'url' => route('staff.create')] : null,
        $user->hasPermission('services.create', 'own')
            ? ['label' => __('dashboard.quick_actions.service'), 'url' => route('services.create')] : null,
    ]));
@endphp

@php $inline = $inline ?? false; @endphp

@if ($actions !== [])
  {{-- In the greeting row rather than at the foot of the page.

       These are the things somebody came to the dashboard to *do*, and at
       the bottom they sat below everything they came to read. No heading
       either: a row of buttons beside a greeting does not need one. --}}
  @if ($inline)
    {{-- Ranged left on a phone, right beside the greeting from `sm` up. The
         row that holds this must be allowed to shrink, or none of this
         wrapping can happen — see dashboard.blade.php. --}}
    <div class="flex flex-wrap justify-start sm:justify-end gap-2">
      @foreach ($actions as $action)
        <a href="{{ $action['url'] }}" class="styledesk_action">{{ $action['label'] }}</a>
      @endforeach
    </div>
  @else
    <section class="sd-card p-5">
      <h2 class="text-[15px] font-semibold text-head">{{ __('dashboard.quick_actions.title') }}</h2>

      <div class="flex flex-wrap gap-2 mt-3">
        @foreach ($actions as $action)
          <a href="{{ $action['url'] }}" class="styledesk_action">{{ $action['label'] }}</a>
        @endforeach
      </div>
    </section>
  @endif
@endif
