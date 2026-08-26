{{-- Laravel resolves errors.419 by convention; the page itself lives in the
     shared template so all seventeen cannot drift apart. --}}
@include('errors.template', ['code' => 419])
