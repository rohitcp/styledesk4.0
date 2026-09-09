{{-- The desktop icon rail. Hidden below lg, where the drawer takes over. --}}
@php
    /* Handed down by the layout rather than looked up here: the drawer
       renders the same config and would otherwise repeat every query. */
    $navCounts = $navCounts ?? [];
@endphp
<nav class="hidden lg:flex self-stretch items-center gap-1 shrink-0" aria-label="{{ __('navigation.rail_primary') }}">
  @foreach (config('navigation.primary') as $item)
    @php $active = \App\Support\Nav::isActive($item); @endphp

    @if (! empty($item['children']))
      @php $count = $navCounts[$item['count'] ?? ''] ?? null; @endphp

      <div class="sd-menu" data-menu>
        {{-- The number rides on the tooltip as well as on the icon: the rail
             draws no labels, so "Staff · 12" is the only place the badge is
             actually explained. --}}
        <a href="{{ \App\Support\Nav::href($item) }}" {!! \App\Support\Nav::pending($item) !!}
           class="sd-navicon grid sd-tip relative @if ($active) is-active @endif"
           data-tip="{{ App\Support\Nav::label($item) }}@if ($count !== null) · {{ $count }}@endif" data-tip-placement="right"
           aria-label="{{ App\Support\Nav::aria($item) }}@if ($count !== null), {{ trans_choice('navigation.active_staff', $count, ['count' => $count]) }}@endif"
           aria-haspopup="true" aria-expanded="false"
           @if ($active) aria-current="page" @endif>
          <x-icon :name="$item['icon']" size="18" />

          @if ($count !== null)
            {{-- aria-hidden: the accessible name above already carries the
                 number, and announcing it twice reads as two facts. --}}
            <span class="sd-navicon__count" aria-hidden="true">{{ $count }}</span>
          @endif
        </a>
        <div class="sd-menu__pop" data-menu-pop hidden role="menu" aria-label="{{ __('navigation.menu_for', ['name' => App\Support\Nav::aria($item)]) }}">
          @foreach ($item['children'] as $child)
            {{-- A module the business has switched off has no menu entry:
                 the screen behind it answers 404, and a link that leads
                 there is a link that lies. --}}
            @continue (! App\Support\Nav::visible($child))

            @if (! empty($child['separator']))
              <div class="sd-menu__rule" role="separator"></div>
            @elseif (! empty($child['section']))
              {{-- A heading, not a link: it names the group below it and has
                   no page of its own. presentation, so a screen reader reads
                   it as the label it is rather than announcing a menu item
                   that cannot be chosen. --}}
              <div class="sd-menu__section" role="presentation">{{ App\Support\Nav::section($child) }}</div>
            @else
              @php $childActive = \App\Support\Nav::isCurrent($child); @endphp

              @if (\App\Support\Nav::isPending($child))
                {{-- Not a link. A screen that does not exist yet must not
                     look like one you can open: clicking it did nothing,
                     which reads as the product being broken rather than as
                     work still to come. --}}
                <span class="sd-menu__item sd-menu__item--soon" role="menuitem"
                      aria-disabled="true" {!! \App\Support\Nav::pending($child) !!}>
                  {{ App\Support\Nav::label($child) }}
                  <span class="sd-menu__soon">{{ __('navigation.coming_soon') }}</span>
                </span>
              @else
                <a href="{{ \App\Support\Nav::href($child) }}"
                   class="sd-menu__item @if ($childActive) is-active @endif" role="menuitem"
                   @if ($childActive) aria-current="page" @endif>{{ App\Support\Nav::label($child) }}</a>
              @endif
            @endif
          @endforeach
        </div>
      </div>
    @else
      @php $itemPending = \App\Support\Nav::isPending($item); @endphp

      {{-- A section with nowhere to go says so in its tooltip and does not
           pretend to be a link. --}}
      <a @if (! $itemPending) href="{{ \App\Support\Nav::href($item) }}" @endif
         {!! \App\Support\Nav::pending($item) !!}
         @class(['sd-navicon grid sd-tip', 'is-active' => $active, 'sd-navicon--soon' => $itemPending])
         data-tip="{{ App\Support\Nav::label($item) }}@if ($itemPending) · {{ __('navigation.coming_soon') }}@endif"
         data-tip-placement="right"
         aria-label="{{ App\Support\Nav::aria($item) }}"
         @if ($itemPending) aria-disabled="true" @endif
         @if ($active) aria-current="page" @endif>
        <x-icon :name="$item['icon']" size="18" />
      </a>
    @endif
  @endforeach
</nav>
