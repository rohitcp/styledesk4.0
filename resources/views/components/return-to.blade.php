{{--
    Carries the caller's address through a form submission.

    Without it a rejected-then-corrected save would forget where the reader
    came from, and land them on the module's own page instead.
--}}
@props(['path' => null])

@if ($path)
    <input type="hidden" name="{{ \App\Support\ReturnTo::KEY }}" value="{{ $path }}">
@endif
