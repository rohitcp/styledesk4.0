<?php

declare(strict_types=1);

/*
| Treue und Prämien.
|
| Zwei Zielgruppen, getrennt durch ihre obersten Schlüssel: `settings` liest,
| wer entscheidet, wie das Programm funktioniert; `client` liest, wer am Tresen
| steht und jemanden vor sich hat. Das zweite muss knapp sein — der Empfang
| liest es, während jemand wartet.
*/

return [

    'title' => 'Treue und Prämien',

    /* Eine Zeile je Sache, die einem Punktestand widerfahren kann. Geschrieben
       als das, was geschehen ist, nicht als Kategorie — denn es wird in einer
       Liste neben einem Datum und einer Zahl gelesen. */
    'activities' => [
        'earned' => 'Punkte gesammelt',
        'redeemed' => 'Prämie eingelöst',
        'expired' => 'Punkte verfallen',
        'refund_adjustment' => 'Korrektur wegen Erstattung',
        'cancellation_adjustment' => 'Korrektur wegen Absage',
        'manual_add' => 'Manuell hinzugefügt',
        'manual_deduct' => 'Manuell abgezogen',
    ],

    /* Warum jemand einen Punktestand von Hand verändert hat. */
    'reasons' => [
        'customer_service' => 'Kulanz',
        'promotion' => 'Aktion',
        'correction' => 'Korrektur',
        'duplicate' => 'Doppelte Punkte',
        'refund' => 'Korrektur wegen Erstattung',
        'other' => 'Sonstiges',
    ],

    'purchases' => [
        'services' => 'Leistungen',
        'products' => 'Produkte',
        'membership_package' => 'Pakete',
        'membership_recurring' => 'Mitgliedschaften',
        'gift_cards' => 'Geschenkgutscheine',
        'tips' => 'Trinkgeld',
        'taxes' => 'Steuern',
    ],

    'expiry' => [
        'never' => 'Nie',
        '6m' => 'Nach 6 Monaten',
        '12m' => 'Nach 12 Monaten',
        '24m' => 'Nach 24 Monaten',
    ],

    'notifications' => [
        'earned_email' => 'E-Mail bei gesammelten Punkten',
        'earned_sms' => 'SMS bei gesammelten Punkten',
        'reward_email' => 'E-Mail bei verfügbarer Prämie',
        'reward_sms' => 'SMS bei verfügbarer Prämie',
    ],

    /* ------------------------------------------------------- Einstellungen -- */

    'settings' => [
        'title' => 'Treue und Prämien',
        'intro' => 'Was ein Besuch einbringt, was ein Punkt zurück wert ist, und wer ihn ausgeben darf.',

        'enable' => 'Treue und Prämien aktivieren',
        'enable_hint' => 'Kundschaft sammelt Punkte für abgeschlossene und bezahlte Termine und kann sie bei künftigen Besuchen einsetzen.',
        'disabled_note' => 'Prämien sind aus. Es wird nichts Neues gesammelt und nichts eingelöst — jeder Punktestand und jede Zeile Historie bleibt genau so.',

        'program' => 'Programm',
        'program_hint' => 'Wie Ihre Kundschaft das Programm auf Belegen und in E-Mails genannt sieht.',
        'program_name' => 'Name des Programms',
        'program_name_hint' => 'Zum Beispiel: Glow Rewards, Beauty-Punkte, Wellness-Prämien.',
        'description' => 'Beschreibung',
        'description_hint' => 'Eine Zeile, die das Programm erklärt. Optional.',
        'description_placeholder' => 'Sammeln Sie bei jedem Besuch Punkte und lösen Sie sie für künftige Leistungen ein.',

        'earn' => 'Punkte sammeln',
        'earn_hint' => 'Wie Umsatz zu Punkten wird. Punkte sind ganzzahlig: bei 5 € = 1 Punkt bringt eine Leistung über 17 € drei.',
        'spend_amount' => 'Ausgegeben',
        'points_earned' => 'Punkte',
        'earn_rule' => ':symbol:amount Umsatz = :points',
        'eligible' => 'Berechtigte Umsätze',
        'eligible_hint' => 'Was Punkte bringt. Leistungen immer — aus ihnen besteht eine Buchung.',
        'always_on' => 'Immer an',
        'coming_soon' => 'Demnächst',

        'redeem' => 'Punkte einlösen',
        'redeem_hint' => 'Was ein Punkt zurück wert ist, und die Grenzen dafür, einen Punktestand auf einmal auszugeben.',
        'points_required' => 'Benötigte Punkte',
        'reward_value' => 'Wert der Prämie',
        'minimum_redemption' => 'Mindestpunkte zum Einlösen',
        'minimum_hint' => 'Der kleinste Stand, der sich überhaupt einsetzen lässt. Darunter erfährt die Kundschaft, wie weit es noch ist.',
        'maximum_reward' => 'Höchste Prämie je Vorgang',
        'maximum_hint' => 'Um wie viel ein einzelner Besuch höchstens rabattiert werden darf. Leer lassen für keine Grenze.',
        'rule' => ':points Punkte = :value',

        'expiry' => 'Verfall',
        'expiry_hint' => 'Wie lange ein Punkt nach dem Sammeln lebt. Bereits gesammelte Punkte behalten die Frist, die sie bekommen haben.',

        'notifications' => 'Benachrichtigungen',
        'notifications_hint' => 'Was die Kundschaft hört, wenn sich ihr Punktestand ändert. Noch wird nichts gesendet — die Nachrichten kommen mit der nächsten Version.',

        'rules' => 'Geschäftsregeln',
        'rules_hint' => 'Die Teile des Programms, die StyleDesk entscheidet — damit Sie wissen, was Sie erwartet.',
        'rule_locations' => 'Ein Punktestand für den ganzen Betrieb',
        'rule_locations_body' => 'Gesammelt wird an jedem Standort und eingelöst an jedem anderen. Es gibt keinen getrennten Stand je Filiale.',
        'rule_awarded' => 'Punkte kommen an, wenn der Besuch beendet und bezahlt ist',
        'rule_awarded_body' => 'Entwürfe, Anfragen, Absagen, Ablehnungen, Nichterscheinen und unbezahlte Termine bringen nichts. Ein teilweise bezahlter Termin bringt anteilig, was beglichen wurde.',
        'rule_refunds' => 'Erstattungen holen Punkte zurück',
        'rule_refunds_body' => 'Eine volle Erstattung nimmt alles zurück, was der Besuch eingebracht hat. Eine Teilerstattung nimmt denselben Anteil zurück, den Rest behält die Kundschaft.',
        'rule_calculation' => 'Punkte zählen auf das, was tatsächlich bezahlt wurde',
        'rule_calculation_body' => 'Gutscheine, Rabatte und bereits eingelöste Prämien werden abgezogen, bevor Punkte errechnet werden.',

        'saved' => 'Treueeinstellungen gespeichert.',
    ],

    /* ----------------------------------------------------- Kundenprofil -- */

    'client' => [
        'title' => 'Treue und Prämien',
        'off' => 'Prämien sind für diesen Betrieb abgeschaltet.',
        'off_hint' => 'Punktestände und Historie bleiben erhalten. Es wird nichts gesammelt und nichts eingelöst, bis das Programm wieder eingeschaltet wird.',

        'available' => 'Verfügbare Punkte',
        'available_hint' => 'Punkte, die sich jetzt einlösen lassen.',
        'pending' => 'Ausstehende Punkte',
        'pending_hint' => 'Erwartet aus Terminen, die noch nicht abgeschlossen sind.',
        'lifetime_earned' => 'Insgesamt gesammelt',
        'lifetime_redeemed' => 'Insgesamt eingelöst',

        'reward_value' => 'Wert der Prämie',
        'worth' => 'Wert :value',
        'worth_nothing' => 'Noch nicht genug zum Einlösen',

        'next_reward' => 'Nächste Prämie',
        'progress' => ':have / :need Punkte',
        'to_go' => 'Noch :points Punkte bis :value',
        'unlocked' => ':value bereit zum Einlösen',

        'activity' => 'Prämienaktivität',
        'none' => 'Noch keine Prämienaktivität.',
        'none_filtered' => 'In diesem Teil der Historie steht nichts.',
        'columns' => [
            'date' => 'Datum',
            'activity' => 'Aktivität',
            'booking' => 'Buchung',
            'location' => 'Standort',
            'points' => 'Punkte',
            'balance' => 'Stand',
        ],
        'filters' => [
            'all' => 'Gesamte Aktivität',
            'earned' => 'Gesammelt',
            'redeemed' => 'Eingelöst',
            'adjustments' => 'Korrekturen',
            'expired' => 'Verfallen',
            'refunds' => 'Erstattungen',
        ],

        'adjust' => 'Punkte anpassen',
        'adjust_title' => 'Punkte anpassen',
        'adjust_intro' => 'Von Hand hinzugefügte oder abgezogene Punkte werden unter Ihrem Namen erfasst und lassen sich danach nicht mehr ändern.',
        'direction' => 'Art der Anpassung',
        'add' => 'Punkte hinzufügen',
        'remove' => 'Punkte abziehen',
        'points' => 'Punkte',
        'reason' => 'Grund',
        'note' => 'Interne Notiz',
        'note_hint' => 'Nur Ihr Team sieht das. Optional.',
        'save' => 'Anpassung speichern',
        'adjusted' => 'Punkte angepasst.',

        'by' => 'von :name',
        'expires' => 'Verfällt am :date',
    ],

    /*
     * The reward catalogue on App Settings → Loyalty & Rewards.
     *
     * A reward is what a balance actually buys, as opposed to the conversion
     * rule above it, which is what a balance is worth. Both are needed: a
     * business may want nothing more than "points off the bill", and another
     * wants to give away an upgrade it can afford rather than cash it cannot.
     */
    'rewards' => [
        'title' => 'Reward catalogue',
        'intro' => 'What your clients can spend their points on. Leave it empty and points simply come off the bill at the rate above.',
        'add' => '+ Add reward',
        'edit' => 'Edit reward',
        'empty' => 'No rewards yet. Clients can still redeem points against the bill at the rate you set above.',
        'name' => 'Reward name',
        'name_placeholder' => '$10 Off Any Service',
        'description' => 'Description',
        'description_hint' => 'Shown to the client beside the reward. Optional.',
        'type' => 'Reward type',
        'points_required' => 'Points required',
        'value' => 'Amount off',
        'percent' => 'Percentage off',
        'service' => 'Service given',
        'scope' => 'Can be used on',
        'scope_services' => 'Choose services',
        'scope_categories' => 'Choose categories',
        'active' => 'Available to clients',
        'active_hint' => 'Switch off to take it out of the catalogue without losing the rewards already given.',
        'save' => 'Save reward',
        'cancel' => 'Cancel',
        'remove' => 'Remove',
        'remove_confirm' => 'Remove this reward from the catalogue? Rewards clients have already redeemed keep their history.',
        'added' => 'Reward added.',
        'saved' => 'Reward saved.',
        'removed' => 'Reward removed.',
        'retired' => 'Reward taken off the catalogue. Redemptions already made keep their history.',
        'no_service' => 'No service chosen',
        'value_varies' => 'Set at the till',
        'inactive' => 'Not available',
        'points' => ':count points',

        'types' => [
            'fixed_discount' => 'Amount off',
            'percentage_discount' => 'Percentage off',
            'free_service' => 'Free service',
            'free_add_on' => 'Free add-on',
            'service_upgrade' => 'Service upgrade',
            'free_product' => 'Free product',
            'custom' => 'Something else',
        ],

        'scopes' => [
            'all_services' => 'Any service',
            'services' => 'Chosen services only',
            'categories' => 'Chosen categories only',
        ],

        'validation' => [
            'amount_required' => 'Say how much comes off.',
            'percent_required' => 'Say what percentage comes off.',
            'service_required' => 'Choose the service this reward gives.',
            'scope_required' => 'Choose at least one, or make it available on any service.',
        ],
    ],

    'activity' => [
        'system' => 'StyleDesk',
    ],

];
