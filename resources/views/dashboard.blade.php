@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
    <div class="max-w-3xl mx-auto px-6 py-12">
        <h1 class="text-[20px] font-semibold text-head mb-1">Dashboard</h1>
        <p class="text-sub mb-6">Signed in as {{ auth()->user()->email }}</p>

        @if (tenancy()->initialized)
            <div class="styledesk_alert--success mb-4">
                Tenancy resolved from your account: <strong>{{ tenant('name') }}</strong>
                (<code>{{ tenant('id') }}</code>)
            </div>
        @else
            <div class="styledesk_alert--danger mb-4">
                No business linked to this account yet, so no tenant is in scope.
                Onboarding creates one and sets <code>users.tenant_id</code>.
            </div>
        @endif

        <div data-vue-component="TenancyProbe"
             data-props='@json(["tenant" => tenancy()->initialized ? tenant("name") : "central"])'></div>

        <form method="POST" action="{{ route('logout') }}" class="mt-6">
            @csrf
            <button type="submit" class="text-link">Sign out</button>
        </form>
    </div>
@endsection
