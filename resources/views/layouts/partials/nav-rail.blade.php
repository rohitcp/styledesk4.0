{{-- The desktop icon rail. Hidden below lg, where the drawer takes over. --}}
<nav class="hidden lg:flex self-stretch items-center gap-1 shrink-0" aria-label="Primary">
  @foreach (config('navigation.primary') as $item)
    @php $active = \App\Support\Nav::isActive($item); @endphp

    @if (! empty($item['children']))
      <div class="sd-menu" data-menu>
        <a href="{{ \App\Support\Nav::href($item) }}" {!! \App\Support\Nav::pending($item) !!}
           class="sd-navicon grid sd-tip @if ($active) is-active @endif"
           data-tip="{{ App\Support\Nav::label($item) }}" aria-label="{{ $item['aria'] ?? App\Support\Nav::label($item) }}"
           aria-haspopup="true" aria-expanded="false">
          <x-icon :name="$item['icon']" size="18" />
        </a>
        <div class="sd-menu__pop" data-menu-pop hidden role="menu" aria-label="{{ $item['aria'] ?? $item['label'] }} menu">
          @foreach ($item['children'] as $child)
            @if (! empty($child['separator']))
              <div class="sd-menu__rule" role="separator"></div>
            @else
              <a href="{{ \App\Support\Nav::href($child) }}" {!! \App\Support\Nav::pending($child) !!} class="sd-menu__item" role="menuitem">{{ $child['label'] }}</a>
            @endif
          @endforeach
        </div>
      </div>
    @else
      <a href="{{ \App\Support\Nav::href($item) }}" {!! \App\Support\Nav::pending($item) !!}
         class="sd-navicon grid sd-tip @if ($active) is-active @endif"
         data-tip="{{ App\Support\Nav::label($item) }}" aria-label="{{ $item['aria'] ?? App\Support\Nav::label($item) }}"
         @if ($active) aria-current="page" @endif>
        <x-icon :name="$item['icon']" size="18" />
      </a>
    @endif
  @endforeach
</nav>
