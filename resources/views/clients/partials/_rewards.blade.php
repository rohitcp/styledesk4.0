{{--
    Client profile → Rewards tab.

    A wrapper now: what it shows lives in `_rewards-body`, which the Loyalty
    module's own client page renders too. The tab's own business is the panel
    the tab strip toggles — the id, the role and the hidden attribute — and
    nothing else.

    The dialog sits outside that panel on purpose. The panel carries `hidden`
    whenever another tab is showing, and a dialog inside a hidden container
    cannot open however it is positioned.
--}}
<div id="panel-rewards" role="tabpanel" aria-labelledby="tab-rewards" data-panel="rewards" class="pt-4" hidden>
    @include('clients.partials._rewards-body')
</div>

@include('clients.partials._rewards-modal')
