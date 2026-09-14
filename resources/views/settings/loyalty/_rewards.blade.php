{{--
    The reward catalogue.

    Outside the settings form above it, and deliberately: that form saves one
    row of rules with one button, and this is a list whose every entry saves,
    edits and removes on its own. Nesting them would put a form inside a form,
    which no browser posts and every reader expects to work.

    Each reward's edit form is a <details> rather than a dialog. The whole
    catalogue is on the page already, a salon has a handful of rewards rather
    than hundreds, and a disclosure keeps the reward being edited next to the
    others it has to make sense beside.
--}}
<div class="sd-card p-5 mt-4">
  <h2 class="text-[15px] font-semibold text-head">{{ __('loyalty.rewards.title') }}</h2>
  <p class="text-[12.5px] text-sub mt-1 leading-relaxed">{{ __('loyalty.rewards.intro') }}</p>

  @if ($rewards->isEmpty())
    <p class="mt-4 text-[13px] text-sub">{{ __('loyalty.rewards.empty') }}</p>
  @else
    <ul class="mt-4 divide-y divide-line border border-line rounded-card">
      @foreach ($rewards as $reward)
        <li class="p-4">
          <div class="flex flex-wrap items-start gap-3">
            <div class="min-w-0 flex-1">
              <p class="text-[13.5px] font-semibold text-head">
                {{ $reward->name }}
                @unless ($reward->is_active)
                  <span class="styledesk_badge styledesk_badge--soon ml-1.5">{{ __('loyalty.rewards.inactive') }}</span>
                @endunless
              </p>

              <p class="text-[12.5px] text-sub mt-0.5">
                {{ $reward->typeLabel() }}
                <span class="text-faint">·</span>
                {{ $reward->valueLabel() }}
                <span class="text-faint">·</span>
                {{ __('loyalty.rewards.scopes.'.$reward->scope) }}
              </p>

              @if ($reward->description)
                <p class="text-[12px] text-faint mt-1 leading-relaxed">{{ $reward->description }}</p>
              @endif
            </div>

            <p class="text-[13px] font-semibold text-head shrink-0">
              {{ __('loyalty.rewards.points', ['count' => number_format($reward->points_required)]) }}
            </p>
          </div>

          @if ($canManage)
            <details class="mt-3">
              <summary class="styledesk_action inline-flex cursor-pointer">{{ __('loyalty.rewards.edit') }}</summary>

              <form method="POST" action="{{ route('settings.loyalty.rewards.update', $reward) }}" class="mt-3">
                @csrf
                @method('PATCH')

                @include('settings.loyalty._reward-fields', [
                    'reward' => $reward,
                    'uid' => 'r'.$reward->id,
                ])

                <div class="mt-4 flex flex-wrap items-center gap-2">
                  <button type="submit"
                          class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
                    {{ __('loyalty.rewards.save') }}
                  </button>
                </div>
              </form>

              {{-- Its own form, because a Remove inside the edit form would
                   post the edit's fields with it. --}}
              <form method="POST" action="{{ route('settings.loyalty.rewards.destroy', $reward) }}" class="mt-2">
                @csrf
                @method('DELETE')
                <button type="submit"
                        data-confirm-title="{{ __('loyalty.rewards.remove') }}"
                        data-confirm="{{ __('loyalty.rewards.remove_confirm') }}"
                        data-confirm-label="{{ __('loyalty.rewards.remove') }}"
                        class="h-9 px-3 rounded-md text-sub hover:bg-hover hover:text-danger text-[13px] font-semibold transition-colors">
                  {{ __('loyalty.rewards.remove') }}
                </button>
              </form>
            </details>
          @endif
        </li>
      @endforeach
    </ul>
  @endif

  @if ($canManage)
    <details class="mt-4" @if ($errors->hasAny(['name', 'type', 'points_required', 'value', 'percent', 'service_id', 'scope_ids'])) open @endif>
      <summary class="styledesk_action inline-flex cursor-pointer">{{ __('loyalty.rewards.add') }}</summary>

      <form method="POST" action="{{ route('settings.loyalty.rewards.store') }}" class="mt-3">
        @csrf

        @include('settings.loyalty._reward-fields', ['reward' => null, 'uid' => 'new'])

        <div class="mt-4">
          <button type="submit"
                  class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
            {{ __('loyalty.rewards.save') }}
          </button>
        </div>
      </form>
    </details>
  @endif
</div>
