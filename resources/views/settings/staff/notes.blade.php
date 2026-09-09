@extends('layouts.app')

@section('title', $staff->displayName().' — '.__('staff.tabs.notes'))

@section('content')
  {{-- The full-width container the clients screens use: the same padding,
       the same breakpoints, no narrow column of its own. A workspace that
       sat in 1080px while every other screen filled the window would read
       as a different application. --}}
  <main class="w-full px-6 lg:px-8 pt-4 pb-[100px]">

      @include('settings.staff._header', ['tab' => 'notes'])

      <section class="bg-white border border-line rounded-card p-5 mt-5">
        <h2 class="text-[15px] font-semibold text-head">{{ __('staff.notes.title') }}</h2>
        {{-- Said plainly, because the whole value of the tab depends on people
             believing it: these are notes the business keeps about the
             business, and no client ever sees them. --}}
        <p class="text-[13px] text-sub mt-1 leading-relaxed max-w-[560px]">{{ __('staff.notes.intro') }}</p>

        @can('update', $staff)
          <form method="POST" action="{{ \App\Support\StaffSection::route('notes.store', $staff) }}" class="mt-4">
            @csrf

            <label for="noteBody" class="sr-only">{{ __('staff.notes.body') }}</label>
            <textarea id="noteBody" name="body" rows="3" class="sd-input !h-auto py-2.5" maxlength="5000"
                      placeholder="{{ __('staff.notes.placeholder') }}">{{ old('body') }}</textarea>
            @error('body')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror

            <button type="submit" data-submit-once data-busy-label="{{ __('common.saving') }}"
                    class="h-9 px-4 mt-3 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
              {{ __('staff.notes.add') }}
            </button>
          </form>
        @endcan

        @if ($notes->isEmpty())
          <div class="border-t border-line mt-5 py-12 text-center">
            <p class="text-[13px] text-sub">{{ __('staff.notes.none') }}</p>
          </div>
        @else
          {{-- Newest first: the last thing said is the thing being caught up
               on. --}}
          <ol class="mt-5 space-y-3 border-t border-line pt-4">
            @foreach ($notes as $note)
              <li class="flex gap-3">
                <span class="sd-avatar sd-avatar--sm shrink-0" aria-hidden="true">{{ $note->authorInitials() }}</span>

                <div class="min-w-0 flex-1">
                  <p class="flex flex-wrap items-baseline gap-x-2 text-[12px]">
                    <span class="font-semibold text-head">{{ $note->authorName() }}</span>
                    <span class="text-faint" title="{{ $note->created_at?->toDayDateTimeString() }}">
                      {{ $note->created_at?->diffForHumans() }}
                    </span>

                    @if ($note->wasWrittenBy(auth()->user()) || auth()->user()->can('delete', $staff))
                      <form method="POST" action="{{ \App\Support\StaffSection::route('notes.destroy', [$staff, $note]) }}"
                            class="ml-auto">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-[12px] text-sub hover:text-danger transition-colors"
                                data-confirm="{{ __('staff.notes.delete_confirm') }}"
                                data-confirm-title="{{ __('common.delete') }}"
                                data-confirm-label="{{ __('common.delete') }}"
                                data-confirm-tone="danger">
                          {{ __('common.delete') }}
                        </button>
                      </form>
                    @endif
                  </p>

                  {{-- The reader's own line breaks kept: a note typed as three
                       short paragraphs should not arrive as one. --}}
                  <p class="text-[13px] text-ink mt-1 leading-relaxed whitespace-pre-line">{{ $note->body }}</p>
                </div>
              </li>
            @endforeach
          </ol>
        @endif
      </section>
  </main>
@endsection
