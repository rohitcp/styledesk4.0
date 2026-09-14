{{--
    The fields one reward is made of, shared by the add form and every edit
    form on the page.

    `uid` makes the ids unique. There are as many copies of this markup on the
    screen as there are rewards plus one, and duplicate ids would point every
    label at the first form's field — a page where clicking a label three
    rewards down focuses the wrong input.

    Which value field is asked for depends on the type, so all three are
    rendered and the script below shows the one that applies. Rendered rather
    than fetched because the answer changes as the reader changes the type,
    and a round trip per change is a form that stutters.
--}}
@php
    /* `old()` belongs to whichever form was posted, and there is one add form
       and one edit form per reward on this page. Only the form that failed
       should come back filled in — every other one shows its stored values. */
    $isNew = $reward === null;
    $repopulate = $isNew && old('name') !== null;
    $val = fn (string $key, $fallback = null) => $repopulate ? old($key, $fallback) : ($reward?->{$key} ?? $fallback);
@endphp

<div data-reward-form class="grid gap-4 sm:grid-cols-2">
  <div class="sm:col-span-2">
    <label for="{{ $uid }}-name" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('loyalty.rewards.name') }}</label>
    <input id="{{ $uid }}-name" name="name" type="text" required maxlength="80" class="sd-input"
           placeholder="{{ __('loyalty.rewards.name_placeholder') }}"
           value="{{ $val('name') }}">
    @if ($isNew) @error('name')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror @endif
  </div>

  <div>
    <label for="{{ $uid }}-type" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('loyalty.rewards.type') }}</label>
    <select id="{{ $uid }}-type" name="type" class="sd-input" data-reward-type>
      @foreach ($rewardTypes as $key => $type)
        {{-- The unavailable ones are shown and disabled, the way the purchase
             list above does it: the screen tells the truth about what is
             planned rather than hiding it. --}}
        <option value="{{ $key }}"
                data-needs="{{ $type['needs'] }}"
                @disabled(! $type['available'])
                @selected($val('type', 'fixed_discount') === $key)>
          {{ __('loyalty.rewards.types.'.$key) }}@unless ($type['available']) — {{ __('common.coming_soon') }}@endunless
        </option>
      @endforeach
    </select>
  </div>

  <div>
    <label for="{{ $uid }}-points" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('loyalty.rewards.points_required') }}</label>
    <input id="{{ $uid }}-points" name="points_required" type="number" min="1" step="1" required class="sd-input"
           value="{{ $val('points_required', 500) }}">
    @if ($isNew) @error('points_required')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror @endif
  </div>

  {{-- One of these three, decided by the type above. --}}
  <div data-reward-needs="amount" hidden>
    <label for="{{ $uid }}-value" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('loyalty.rewards.value') }}</label>
    <div class="relative">
      <span class="styledesk_input__prefix pointer-events-none" aria-hidden="true">{{ $symbol }}</span>
      <input id="{{ $uid }}-value" name="value" type="number" min="0.01" step="0.01" class="sd-input styledesk_input--prefixed"
             value="{{ $repopulate ? old('value') : ($reward?->value_minor !== null ? number_format($reward->value_minor / 100, 2, '.', '') : '') }}">
    </div>
    @if ($isNew) @error('value')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror @endif
  </div>

  <div data-reward-needs="percent" hidden>
    <label for="{{ $uid }}-percent" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('loyalty.rewards.percent') }}</label>
    <input id="{{ $uid }}-percent" name="percent" type="number" min="1" max="100" step="1" class="sd-input"
           value="{{ $val('percent') }}">
    @if ($isNew) @error('percent')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror @endif
  </div>

  <div data-reward-needs="service" hidden>
    <label for="{{ $uid }}-service" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('loyalty.rewards.service') }}</label>
    <select id="{{ $uid }}-service" name="service_id" class="sd-input">
      <option value="">{{ __('loyalty.rewards.no_service') }}</option>
      @foreach ($services as $service)
        <option value="{{ $service->id }}" @selected((int) $val('service_id') === (int) $service->id)>{{ $service->name }}</option>
      @endforeach
    </select>
    @if ($isNew) @error('service_id')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror @endif
  </div>

  <div class="sm:col-span-2">
    <label for="{{ $uid }}-scope" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('loyalty.rewards.scope') }}</label>
    <select id="{{ $uid }}-scope" name="scope" class="sd-input" data-reward-scope>
      @foreach ($rewardScopes as $scope)
        <option value="{{ $scope }}" @selected($val('scope', 'all_services') === $scope)>{{ __('loyalty.rewards.scopes.'.$scope) }}</option>
      @endforeach
    </select>
  </div>

  @php $chosenScopeIds = array_map('intval', $repopulate ? (old('scope_ids') ?? []) : ($reward?->scope_ids ?? [])); @endphp

  <div class="sm:col-span-2" data-reward-scope-list="services" hidden>
    <p class="block text-[13px] font-medium text-ink mb-1.5">{{ __('loyalty.rewards.scope_services') }}</p>
    <div class="grid gap-2 sm:grid-cols-2 max-h-[240px] overflow-y-auto">
      @foreach ($services as $service)
        <x-choice name="scope_ids[]" :value="$service->id" :label="$service->name"
                  :checked="in_array((int) $service->id, $chosenScopeIds, true)" />
      @endforeach
    </div>
    @if ($isNew) @error('scope_ids')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror @endif
  </div>

  <div class="sm:col-span-2" data-reward-scope-list="categories" hidden>
    <p class="block text-[13px] font-medium text-ink mb-1.5">{{ __('loyalty.rewards.scope_categories') }}</p>
    <div class="grid gap-2 sm:grid-cols-2 max-h-[240px] overflow-y-auto">
      @foreach ($categories as $category)
        <x-choice name="scope_ids[]" :value="$category->id" :label="$category->name"
                  :checked="in_array((int) $category->id, $chosenScopeIds, true)" />
      @endforeach
    </div>
  </div>

  <div class="sm:col-span-2">
    <label for="{{ $uid }}-description" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('loyalty.rewards.description') }}</label>
    <input id="{{ $uid }}-description" name="description" type="text" maxlength="255" class="sd-input"
           value="{{ $val('description') }}">
    <p class="mt-1.5 text-[12px] text-sub">{{ __('loyalty.rewards.description_hint') }}</p>
  </div>

  <div class="sm:col-span-2">
    <x-choice name="is_active" value="1"
              :label="__('loyalty.rewards.active')"
              :hint="__('loyalty.rewards.active_hint')"
              :checked="$isNew ? ($repopulate ? (bool) old('is_active') : true) : (bool) $reward->is_active" />
  </div>
</div>
