{{-- Laravel resolves errors.405 by convention; the page itself lives in the
     shared template so all seventeen cannot drift apart. --}}
@include('errors.template', ['code' => 405])
