@extends('layouts.app')

@section('title', __('account.profile.title'))

@section('content')
<x-account.shell current="profile" :title="__('account.profile.title')" :intro="__('account.profile.intro')">

  {{-- The claim in progress, if there is one. Above everything, because it
       is the one thing on this page that is waiting on the reader. --}}
  @if ($user->pending_email)
    <div class="sd-alert sd-alert--info mb-5" role="status">
      <div class="flex flex-wrap items-start gap-2.5">
        <x-icon name="envelope" size="17" class="shrink-0 mt-px" />
        <div class="min-w-0 flex-1">
          <p class="font-semibold">{{ __('account.profile.email_pending_title') }}</p>
          <p class="mt-1">{{ __('account.profile.email_pending_body', ['email' => $user->pending_email]) }}</p>

          <div class="flex flex-wrap items-center gap-2 mt-3">
            <form method="POST" action="{{ route('account.email.resend') }}">
              @csrf
              <button type="submit" data-submit-once class="styledesk_action">{{ __('account.profile.email_resend') }}</button>
            </form>

            <form method="POST" action="{{ route('account.email.cancel') }}">
              @csrf
              @method('DELETE')
              <button type="submit" class="h-8 px-3 rounded-md text-[12px] font-semibold text-sub hover:text-ink hover:bg-hover transition-colors">
                {{ __('account.profile.email_cancel') }}
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  @endif

  <div class="space-y-5 max-w-[820px]">

    {{-- The photo has its own form and its own endpoint: it is applied as it
         is chosen, so it survives the rest of the page being refused. --}}
    <section class="bg-white border border-line rounded-card p-5 sm:p-6">
      <h3 class="text-[15px] font-semibold text-head">{{ __('account.profile.photo_card') }}</h3>
      <p class="text-[13px] text-sub mt-1 max-w-[620px]">{{ __('account.profile.photo_hint') }}</p>

      <form method="POST" action="{{ route('account.photo.store') }}" enctype="multipart/form-data"
            class="mt-5 flex flex-wrap items-center gap-5" data-photo-form>
        @csrf

        <span class="sd-avatar sd-avatar--lg overflow-hidden" data-photo-preview aria-hidden="true">
          @if ($user->avatarUrl())
            <img src="{{ $user->avatarUrl() }}" alt="" class="h-full w-full object-cover">
          @else
            {{ $user->initials() }}
          @endif
        </span>

        <div class="min-w-0">
          <input id="photo" name="photo" type="file" class="sr-only"
                 accept="image/png,image/jpeg,image/webp" data-photo-input
                 aria-label="{{ __('account.profile.photo_upload') }}">

          <div class="flex flex-wrap items-center gap-2">
            {{-- A label rather than a button wired by script: the file dialog
                 then opens with no JavaScript at all, and the keyboard
                 behaviour is the platform's. --}}
            <label for="photo" class="styledesk_action cursor-pointer">
              {{ $user->avatar_file_id ? __('account.profile.photo_replace') : __('account.profile.photo_upload') }}
            </label>

            {{-- Shown only with JavaScript, because without it the file has
                 already been sent by the time this could be pressed. --}}
            <button type="submit" data-photo-save hidden
                    class="h-9 px-3.5 rounded-md bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
              {{ __('account.save') }}
            </button>
          </div>

          <p class="mt-1.5 text-[12px] text-sub" data-photo-note hidden>{{ __('account.profile.photo_pending') }}</p>
          @error('photo')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
        </div>
      </form>

      @if ($user->avatar_file_id)
        <form method="POST" action="{{ route('account.photo.destroy') }}" class="mt-3">
          @csrf
          @method('DELETE')
          <button type="submit" class="text-[13px] font-medium text-danger hover:underline">
            {{ __('account.profile.photo_remove') }}
          </button>
        </form>
      @endif
    </section>

    <form method="POST" action="{{ route('account.profile.update') }}" class="space-y-5"
          data-validate-form
          data-validation-messages='@json(\App\Support\LiveValidation::messages())'>
      @csrf
      @method('PATCH')

      <section class="bg-white border border-line rounded-card p-5 sm:p-6">
        <h3 class="text-[15px] font-semibold text-head">{{ __('account.profile.personal_card') }}</h3>

        <div class="mt-5 grid sm:grid-cols-2 gap-x-5 gap-y-5">
          <div>
            <label for="first_name" class="block text-[13px] font-medium text-ink mb-1.5">
              {{ __('account.profile.first_name') }} <span class="text-danger">*</span>
            </label>
            <input id="first_name" name="first_name" type="text" class="sd-input" data-capitalize required
                   data-rules="required|max:100" autocomplete="given-name"
                   value="{{ old('first_name', $user->first_name) }}">
            <p data-error-for="first_name" role="alert" class="mt-1.5 text-[12px] text-danger"
               @unless ($errors->has('first_name')) hidden @endunless>{{ $errors->first('first_name') }}</p>
          </div>

          <div>
            <label for="last_name" class="block text-[13px] font-medium text-ink mb-1.5">
              {{ __('account.profile.last_name') }} <span class="text-danger">*</span>
            </label>
            <input id="last_name" name="last_name" type="text" class="sd-input" data-capitalize required
                   data-rules="required|max:100" autocomplete="family-name"
                   value="{{ old('last_name', $user->last_name) }}">
            <p data-error-for="last_name" role="alert" class="mt-1.5 text-[12px] text-danger"
               @unless ($errors->has('last_name')) hidden @endunless>{{ $errors->first('last_name') }}</p>
          </div>

          <div>
            <label for="display_name" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('account.profile.display_name') }}</label>
            <input id="display_name" name="display_name" type="text" class="sd-input"
                   data-rules="max:100" placeholder="{{ __('account.profile.display_name_placeholder') }}"
                   value="{{ old('display_name', $user->display_name) }}">
            <p data-error-for="display_name" role="alert" class="mt-1.5 text-[12px] text-danger" hidden></p>
            <p class="mt-1.5 text-[12px] text-sub">{{ __('account.profile.display_name_hint') }}</p>
          </div>

          <div>
            <label for="job_title" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('account.profile.job_title') }}</label>
            <input id="job_title" name="job_title" type="text" class="sd-input" data-capitalize
                   data-rules="max:100" placeholder="{{ __('account.profile.job_title_placeholder') }}"
                   value="{{ old('job_title', $user->job_title) }}">
            <p data-error-for="job_title" role="alert" class="mt-1.5 text-[12px] text-danger" hidden></p>
          </div>

          <div class="sm:col-span-2">
            <label for="phone" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('account.profile.phone') }}</label>
            {{-- The same country-code control as every other phone field in
                 the app; resources/js/phone.js reads the data attributes. --}}
            <div class="relative" data-phone data-phone-country="{{ old('phone_country', $user->phone_country ?? $user->tenant?->country_code ?? 'US') }}">
              <div class="sd-phone">
                <button type="button" class="sd-phone__country" data-phone-toggle
                        aria-haspopup="listbox" aria-expanded="false" aria-label="{{ __('account.profile.phone') }}">
                  <span class="sd-phone__flag" data-phone-flag>&#127482;&#127480;</span>
                  <span class="font-medium" data-phone-code>+1</span>
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" class="text-faint shrink-0" aria-hidden="true"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
                <input id="phone" name="phone" type="tel" class="sd-phone__field" data-phone-input
                       autocomplete="tel-national" value="{{ old('phone', $user->phone) }}">
              </div>
              <div class="sd-pop" data-phone-pop hidden></div>
              <input type="hidden" name="phone_country" data-phone-country-value
                     value="{{ old('phone_country', $user->phone_country ?? $user->tenant?->country_code ?? 'US') }}">
            </div>
            <p data-error-for="phone" role="alert" class="mt-1.5 text-[12px] text-danger"
               @unless ($errors->has('phone')) hidden @endunless>{{ $errors->first('phone') }}</p>
            <p class="mt-1.5 text-[12px] text-sub">{{ __('account.profile.phone_hint') }}</p>
          </div>
        </div>
      </section>

      <div class="flex flex-wrap items-center gap-3">
        <button type="submit" data-submit-once
                class="inline-flex items-center h-11 px-6 rounded-lg bg-brand hover:bg-brand-dark text-white text-[14px] font-semibold transition-colors">
          {{ __('account.save') }}
        </button>
        <a href="{{ route('account.profile') }}"
           class="h-11 px-4 inline-flex items-center rounded-lg text-[13px] font-semibold text-sub hover:text-ink hover:bg-hover transition-colors">
          {{ __('account.cancel') }}
        </a>
      </div>
    </form>

    {{-- Its own form, and deliberately not part of Save Changes: changing
         where sign-in mail goes is not the same kind of act as correcting a
         job title, and it asks for the password to prove it. --}}
    <section class="bg-white border border-line rounded-card p-5 sm:p-6">
      <h3 class="text-[15px] font-semibold text-head">{{ __('account.profile.email_card') }}</h3>
      <p class="text-[13px] text-sub mt-1 max-w-[620px]">{{ __('account.profile.email_hint') }}</p>

      <form method="POST" action="{{ route('account.email.request') }}" class="mt-5 grid sm:grid-cols-2 gap-x-5 gap-y-5">
        @csrf

        <div>
          <label for="email" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('account.profile.email') }}</label>
          <input id="email" name="email" type="email" @class(['sd-input', 'is-error' => $errors->has('email')])
                 autocomplete="email" value="{{ old('email', $user->pending_email ?? $user->email) }}">
          <p data-error-for="email" role="alert" class="mt-1.5 text-[12px] text-danger"
             @unless ($errors->has('email')) hidden @endunless>{{ $errors->first('email') }}</p>
        </div>

        <div>
          <label for="current_password" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('account.profile.email_current_password') }}</label>
          <div class="relative">
            <input id="current_password" name="current_password" type="password"
                   @class(['sd-input', 'has-suffix', 'is-error' => $errors->has('current_password')])
                   autocomplete="current-password">
            <x-password-toggle for="current_password" class="absolute right-2.5 top-1/2 -translate-y-1/2 h-7 w-7 grid place-items-center rounded text-faint hover:text-sub hover:bg-hover transition-colors" />
          </div>
          <p data-error-for="current_password" role="alert" class="mt-1.5 text-[12px] text-danger"
             @unless ($errors->has('current_password')) hidden @endunless>{{ $errors->first('current_password') }}</p>
        </div>

        <div class="sm:col-span-2">
          <button type="submit" data-submit-once class="styledesk_action">{{ __('account.profile.email_change') }}</button>
        </div>
      </form>
    </section>

    {{-- Read-only, and said to be: these are the business's answers about a
         person, and a field that looks editable and refuses to save is worse
         than one that never invited the attempt. --}}
    <section class="bg-white border border-line rounded-card p-5 sm:p-6">
      <h3 class="text-[15px] font-semibold text-head">{{ __('account.profile.staff_card') }}</h3>
      <p class="text-[13px] text-sub mt-1 max-w-[620px]">{{ __('account.profile.staff_intro') }}</p>

      <dl class="mt-5 sd-dl">
        <dt class="sd-dl__t">{{ __('account.profile.role') }}</dt>
        <dd class="sd-dl__d">{{ $facts['role'] }}</dd>

        <dt class="sd-dl__t">{{ __('account.profile.locations') }}</dt>
        <dd class="sd-dl__d">{{ $facts['locations'] }}</dd>

        <dt class="sd-dl__t">{{ __('account.profile.status') }}</dt>
        <dd class="sd-dl__d">{{ $facts['status'] }}</dd>

        <dt class="sd-dl__t">{{ __('account.profile.member_since') }}</dt>
        <dd class="sd-dl__d">{{ $facts['member_since'] }}</dd>
      </dl>
    </section>
  </div>
</x-account.shell>
@endsection

@push('scripts')
<script>
    /*
     * The photo, previewed before it is sent.
     *
     * Without JavaScript the file input posts with its form and the picture
     * that comes back is the one the server stored — correct, just one round
     * trip later. With it, the chosen file is drawn locally first so the
     * reader approves the crop before anything is uploaded, and Save is what
     * sends it. That is why the Save button starts hidden: with no script it
     * would be a second button doing what choosing the file already did.
     */
    document.addEventListener('DOMContentLoaded', function () {
        var form = document.querySelector('[data-photo-form]');
        if (!form) return;

        var input = form.querySelector('[data-photo-input]');
        var preview = form.querySelector('[data-photo-preview]');
        var save = form.querySelector('[data-photo-save]');
        var note = form.querySelector('[data-photo-note]');

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (!file) return;

            var reader = new FileReader();
            reader.onload = function (e) {
                preview.innerHTML = '';
                var img = document.createElement('img');
                img.src = e.target.result;
                img.alt = '';
                img.className = 'h-full w-full object-cover';
                preview.appendChild(img);
            };
            reader.readAsDataURL(file);

            save.hidden = false;
            note.hidden = false;
        });
    });
</script>
@endpush
