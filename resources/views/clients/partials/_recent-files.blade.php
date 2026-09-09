{{--
    Where the Recent files card lands.

    Empty on purpose. The card is rendered by the Files tab's own component
    and teleported in here, which is what lets it reuse that component's
    viewer: clicking the newest thumbnail opens the same modal the tab opens,
    with the same navigation, rather than a second implementation that will
    eventually disagree with the first.

    It also means the card follows an upload without a page reload — it is
    reading the list the tab just refreshed, not a snapshot taken when the
    page was built.

    See resources/js/components/ClientFiles.vue.
--}}
<div id="client-recent-files"></div>
