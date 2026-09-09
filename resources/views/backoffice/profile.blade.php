@extends('layouts.backoffice')

@section('title', __('backoffice.nav.profile'))

@section('content')


    <h1 class="text-[22px] font-bold text-head tracking-tight">{{ __('backoffice.profile.title') }}</h1>

    <div class="grid lg:grid-cols-2 gap-5 mt-5">
        <section class="sd-card p-5">
            <h2 class="text-[15px] font-semibold text-head">{{ __('backoffice.profile.details') }}</h2>

            <form method="POST" action="{{ route('backoffice.profile.update') }}" class="mt-4 space-y-4">
                @csrf
                @method('PATCH')

                <div>
                    <label for="name" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('backoffice.profile.name') }}</label>
                    <input id="name" name="name" type="text" class="sd-input" required value="{{ old('name', $admin->name) }}">
                    @error('name')<p class="mt-1.5 text-[12px] text-danger" role="alert">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="email" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('backoffice.auth.email_label') }}</label>
                    <input id="email" name="email" type="email" class="sd-input" required value="{{ old('email', $admin->email) }}">
                    @error('email')<p class="mt-1.5 text-[12px] text-danger" role="alert">{{ $message }}</p>@enderror
                </div>

                {{-- Shown, never editable. A console where people can promote
                     themselves has no roles at all. --}}
                <div>
                    <span class="block text-[13px] font-medium text-ink mb-1.5">{{ __('backoffice.profile.role') }}</span>
                    <p class="text-[13px] text-sub">{{ $admin->roleLabel() }}</p>
                </div>

                <button type="submit" class="h-10 px-5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
                    {{ __('common.save') }}
                </button>
            </form>
        </section>

        <section class="sd-card p-5">
            <h2 class="text-[15px] font-semibold text-head">{{ __('backoffice.profile.password') }}</h2>
            <p class="text-[12.5px] text-sub mt-1">{{ __('backoffice.profile.password_hint') }}</p>

            <form method="POST" action="{{ route('backoffice.profile.password') }}" class="mt-4 space-y-4">
                @csrf
                @method('PATCH')

                <div>
                    <label for="current_password" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('backoffice.profile.current_password') }}</label>
                    <input id="current_password" name="current_password" type="password" class="sd-input" required autocomplete="current-password">
                    @error('current_password')<p class="mt-1.5 text-[12px] text-danger" role="alert">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="new_password" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('backoffice.auth.new_password') }}</label>
                    <input id="new_password" name="password" type="password" class="sd-input" required autocomplete="new-password">
                    @error('password')<p class="mt-1.5 text-[12px] text-danger" role="alert">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('backoffice.auth.confirm_password') }}</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" class="sd-input" required autocomplete="new-password">
                </div>

                <button type="submit" class="h-10 px-5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
                    {{ __('backoffice.profile.change_password') }}
                </button>
            </form>
        </section>
    </div>
@endsection
