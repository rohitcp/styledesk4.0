<?php

declare(strict_types=1);

/*
| Termine.
|
| Die Sprache hält zwei Dinge auseinander, die leicht verschwimmen: Die
| Terminnotiz gilt dieser Buchung, die Kundennotiz gilt der Person. Die eine
| wird am Tag selbst einmal gelesen; die andere von allen, die diese Person
| buchen — so lange sie Kundschaft ist.
*/

return [

    'title' => 'Buchungen',
    'search_placeholder' => 'Buchungen, Kundschaft, Buchungsnummer suchen…',
    'intro' => 'Jeder angenommene Termin, und bei wem er ist.',
    'new' => 'Neue Buchung',
    'new_intro' => 'Person suchen — oder Laufkundschaft aufnehmen.',
    'walk_in_intro' => 'Die Angaben so aufnehmen, wie sie am Tresen kommen.',

    'add' => [
        'client' => 'Kundin oder Kunden anlegen',
        'booking' => 'Buchung anlegen',
        'walk_in' => 'Buchung anlegen · Laufkundschaft',
        'leave' => 'Abwesenheit eintragen',
    ],

    'modes' => [
        'booking' => 'Buchung',
        'walkin' => 'Laufkundschaft',
    ],

    'any_staff' => 'Wer frei ist',
    'walk_in_guest' => 'Laufkundschaft',

    'columns' => [
        'arrival' => 'Ankunft',
        'checkin' => 'Check-in',
        'location' => 'Standort',
        'reference' => 'Buchungsnummer',
        'booked_by' => 'Gebucht von',
        'client' => 'Kundin/Kunde',
        'date' => 'Datum',
        'time' => 'Uhrzeit',
        'services' => 'Leistungen',
        'staff' => 'Bei',
        'total' => 'Gesamt',
        'status' => 'Status',
    ],

    'filters' => [
        'all_statuses' => 'Alle Status',
        'all_locations' => 'Alle Standorte',
        'all_services' => 'Alle Leistungen',
        'all_payments' => 'Alle Zahlungsstatus',
        'all_staff' => 'Ganzes Team',
        'date' => 'Datum',
        'reset' => 'Zurücksetzen',
    ],

    'statuses' => [
        'pending' => ['label' => 'Offen'],
        'draft' => ['label' => 'Entwurf'],
        'confirmed' => ['label' => 'Bestätigt'],
        'arrived' => ['label' => 'Eingecheckt'],
        'completed' => ['label' => 'Abgeschlossen'],
        'no-show' => ['label' => 'Nicht erschienen'],
        'declined' => ['label' => 'Abgelehnt'],
        'cancelled' => ['label' => 'Storniert'],
    ],

    'sources' => [
        'front-desk' => 'Empfang',
        'phone' => 'Telefon',
        'online' => 'Online',
        'walk-in' => 'Laufkundschaft',
        'social' => 'Social Media',
        'referral' => 'Empfehlung',
        'other' => 'Sonstiges',
    ],

    /*
    | Die fünf Entscheidungen, die die Buchungsansicht trägt — in der
    | Reihenfolge, in der man sie ausspricht, nicht in der, die eine Datenbank
    | gern hätte.
    */
    'sections' => [
        'purchase' => 'Art wählen',
        'client' => 'Kundin/Kunde',
        'service' => 'Leistung',
        'when' => 'Fachkraft und Zeit',
        'details' => 'Buchungsdetails',
        'payment' => 'Anzahlung / Zahlung',
        'comms' => 'Kommunikation',
        'summary' => 'Buchungsübersicht',
    ],

    'purchase' => [
        'title' => 'Art wählen',
        'question' => 'Was möchten Sie verkaufen?',
        'services' => 'Leistungen',
        'services_hint' => 'Ein Termin: Leistungen, Team, Uhrzeit und Rechnung.',
        'membership' => 'Mitgliedschaft',
        'membership_hint' => 'Eine laufende Mitgliedschaft oder ein Leistungspaket.',
        'gift_card' => 'Geschenkkarte',
        'gift_card_hint' => 'Ein Guthaben, das die Kundin später einlösen kann.',
        'coming_soon' => 'Demnächst',
        'membership_off' => 'Schalten Sie Mitgliedschaft zuerst in den App-Einstellungen ein.',
        'membership_empty' => 'Veröffentlichen Sie zuerst eine Mitgliedschaft unter Kunden → Mitgliedschaft.',
    ],

    'membership' => [
        'select' => 'Mitgliedschaft wählen',
        'plans' => 'Mitgliedschaftstarife',
        'packages' => 'Mitgliedschaftspakete',
        'none' => 'Noch nichts zum Verkauf veröffentlicht.',
        'none_hint' => 'Veröffentlichen Sie eine Mitgliedschaft unter Kunden → Mitgliedschaft, dann erscheint sie hier.',
        'includes' => 'Enthält',
        'select_this' => 'Mitgliedschaft wählen',
        'selected' => 'Gewählt',
        'change' => 'Ändern',
        'saving' => 'Die Kundin spart :amount',
        'trial' => ':days Tage Test',
        'joining_fee' => 'Aufnahmegebühr :amount',
        'setup_fee' => 'Einrichtungsgebühr :amount',
        'start' => 'Startdatum',
        'start_today' => 'Heute beginnen',
        'start_later' => 'Startdatum wählen',
        'start_locked' => 'Mitgliedschaften beginnen am Verkaufstag.',
        'scheduled_note' => 'Diese Mitgliedschaft ist bis zum :date geplant.',
        'purchase_summary' => 'Kaufübersicht',
        'billing' => 'Abrechnung',
        'next_billing' => 'Nächste Abbuchung',
        'one_off' => 'Einmaliger Kauf',
        'due_today' => 'Heute fällig',
        'client_required' => 'Wählen Sie zuerst eine Kundin — eine Mitgliedschaft gehört immer zu jemandem.',
        'plan_required' => 'Wählen Sie eine Mitgliedschaft.',
        'method_note' => 'Eine laufende Mitgliedschaft braucht eine erneut belastbare Zahlungsmethode.',
        'complete' => 'Kauf abschließen',
    ],

    'credits' => [
        'available' => 'Mitgliedervorteil verfügbar',
        'from' => 'Aus :name',
        'remaining' => ':count verfügbar',
        'apply' => 'Guthaben einlösen',
        'apply_many' => 'Mitgliedschaft nutzen · :count Guthaben',
        'applied_many' => 'Mitgliedschaft · :count Guthaben eingelöst',
        'applied' => 'Guthaben eingelöst',
        'remove' => 'Entfernen',
        'line' => 'Mitgliedschaftsguthaben',
        'covered' => 'Durch Mitgliedschaft abgedeckt',
        'used_line' => 'Genutztes Guthaben',
        'benefits_section' => 'Mitgliedschaftsvorteile',
        'col_service' => 'Leistung',
        'col_included' => 'Enthalten',
        'col_used' => 'Genutzt',
        'col_remaining' => 'Übrig',
        'covered_by' => 'Abgedeckt durch :name · :count übrig',
        'used_up' => 'Kontingent aufgebraucht. Zum normalen Preis buchbar.',
        'each' => 'je :count Guthaben',
        'renews' => 'Erneuert sich am :date',
        'balance_line' => 'Offener Leistungsbetrag',
        'deposit_covered' => 'Keine Anzahlung nötig',
        'deposit_covered_hint' => 'Die Mitgliedschaft deckt alle Leistungen dieser Buchung ab, es gibt also nichts im Voraus einzuziehen. Ein Trinkgeld wird weiterhin vollständig eingezogen.',
        'expires' => 'Läuft am :date ab',
        'membership_id' => 'Mitgliedsnummer',
        'period' => 'Leistungszeitraum',
        'plan_details' => 'Tarifdetails',
        'col_reserved' => 'Reserviert',
        'col_status' => 'Status',
        'status_available' => 'Verfügbar',
        'status_reserved' => 'Reserviert',
        'status_used' => 'Genutzt',
        'reserved_for' => 'Reserviert für :date',
        'reserved_title' => 'Mitgliedervorteil bereits reserviert',
        'reserved_body' => 'Dieser Mitgliedervorteil ist für den Termin am :date reserviert. Um ihn hier zu nutzen, storniere oder ändere zuerst jene Buchung.',
        'reserved_view' => 'Buchung vom :date ansehen',
        'reserved_cancel' => 'Buchung vom :date stornieren',
        'reserved_pay' => 'Normal bezahlen',
        'reserved_close' => 'Schließen',
    ],

    'cards' => [
        'recurring' => 'Wiederkehrende Zahlung',
        'recurring_hint' => 'Verlängert sich automatisch bis zur Kündigung.',
        'recurring_locked' => 'Diese Mitgliedschaft wird als Abo verkauft und verlängert sich immer.',
        'renews' => 'Verlängert sich :price',
        'one_off' => 'Jede Verlängerung am Empfang einziehen',
        'title' => 'Hinterlegte Karte',
        'why' => 'Für die automatische Verlängerung erforderlich.',
        'existing' => 'Vorhandene Karte verwenden',
        'add' => '+ Karte hinzufügen',
        'default' => 'Standard',
        'expires' => 'Gültig bis :date',
        'expired' => 'Abgelaufen',
        'expiring' => 'Läuft diesen Monat ab',
        'save' => 'Diese Karte hinterlegen',
        'save_required' => 'Für die automatische Verlängerung erforderlich.',
        'none' => 'Für diese Kundin ist keine Karte hinterlegt.',
        'unavailable' => 'Karten können nicht hinterlegt werden',
        'unavailable_hint' => 'Verbinden Sie unter App-Einstellungen → Zahlungen einen Zahlungsdienstleister, um Karten für die automatische Verlängerung zu hinterlegen.',
        'client_first' => 'Wählen Sie zuerst eine Kundin, bevor Sie eine Karte hinzufügen.',
        'required' => 'Wählen Sie eine Karte für die Verlängerung, oder schalten Sie die wiederkehrende Zahlung aus.',
        'adding' => 'Karte wird hinzugefügt…',
        'failed' => 'Diese Karte konnte nicht gespeichert werden.',
        'cancel' => 'Abbrechen',
        'summary_recurring' => 'Wiederkehrend',
        'summary_payment_method' => 'Zahlungsmethode',
        'summary_next_billing' => 'Nächste Abbuchung',
        'yes' => 'Ja',
        'no' => 'Nein',
    ],

    'client' => [
        'search' => 'Nach Name, Telefon oder E-Mail suchen…',
        'search_label' => 'Kundschaft nach Name, Telefon oder E-Mail suchen',
        'or' => 'ODER',
        'add' => 'Kundin oder Kunden anlegen',
        'guest' => 'Anonyme Laufkundschaft buchen',
        'none' => 'Niemand passt dazu.',
        'change' => 'Ändern',
        'guest_name' => 'Name',
        'guest_phone' => 'Mobil',
        'guest_email' => 'E-Mail',
        'guest_hint' => 'Eine Mobilnummer oder E-Mail nimmt die Person in die Kundenliste auf oder verknüpft diese Buchung mit ihrem vorhandenen Datensatz. Der Name allein genügt nicht.',
        'guest_save' => 'Angaben speichern',
        'guest_checking' => 'Wird geprüft…',
        'guest_saved' => 'Gespeichert',
        'guest_known' => 'Das ist womöglich schon Kundschaft.',
        'guest_known_hint' => 'Nehmen Sie den vorhandenen Datensatz, dann behält die Buchung die Historie. Machen Sie als Laufkundschaft weiter, wenn es jemand anderes ist.',
        'guest_phone_invalid' => 'Enter a valid mobile number.',
        'guest_email_invalid' => 'Enter a valid email address.',
        'guest_created' => 'Added to your client list.',
        'guest_linked' => 'Attached to their existing client record.',
        'guest_conflict' => 'These details belong to two different clients.',
        'guest_conflict_hint' => 'The email is on one record and the number on another, so nothing has been attached. Choose which is right, or carry on as a walk-in and sort it out later.',
    ],

    'service' => [
        'search' => 'Leistungen durchsuchen…',
        'category' => 'Leistungskategorie',
        'all_categories' => 'Alle Kategorien',
        'search_categories' => 'Kategorien durchsuchen…',
        'chosen' => 'Gewählt',
        'none' => 'Keine Leistung passt dazu.',
        'empty' => 'Noch keine Leistungen. Legen Sie eine an, dann bietet die Buchungsansicht sie an.',
        'minutes' => ':count Min.',
        'remove' => ':name entfernen',

        /*
        | Der ganzseitige Auswahldialog.
        |
        | Ein Salon mit hundert Leistungen kann keine davon aus einer Liste in
        | einem Formularfeld wählen — also wird das Wählen zur eigenen Ansicht:
        | Kategorien auf der einen Seite, Leistungen auf der anderen, und unten
        | ein einziges Speichern statt einer Bestätigung je Leistung.
        */
        'add' => 'Leistung hinzufügen / zuweisen',
        'change' => 'Leistungen hinzufügen oder ändern',
        'card_empty' => 'Noch keine Leistungen gewählt.',
        'select_title' => 'Leistungen auswählen',
        'close' => 'Zurück zur Buchung',
        'categories' => 'Leistungskategorien',
        'all_services' => 'Alle Leistungen',
        'client_favorites' => 'Favoriten der Kundschaft',
        'selected' => ':count ausgewählt',
        'selected_one' => '1 ausgewählt',
        'selected_none' => 'Nichts ausgewählt',
        'save_close' => 'Speichern und schließen',
        'nothing_here' => 'Nichts in dieser Kategorie.',
        'hours' => ':count Std.',
        'hours_minutes' => ':hours Std. :minutes Min.',
        'count' => ':count Leistungen',
        'count_one' => '1 Leistung',
        'needs' => 'Benötigt :names',
    ],

    'when' => [
        'none_in_period' => 'Zu dieser Tageszeit ist nichts frei.',
        'change_location' => 'Standort wechseln',
        'search_locations' => 'Standorte durchsuchen…',
        'no_locations' => 'Kein Standort passt zu dieser Suche.',
        'closed_date' => 'Keine Termine verfügbar — dieser Standort hat an diesem Tag geschlossen.',
        'too_long' => 'Diese Leistungen passen an diesem Tag nicht in die Öffnungszeiten dieses Standorts.',
        'nothing_free' => 'An diesem Tag ist nichts frei — die Zeiten sind belegt, oder niemand hat Dienst.',
        'day_over' => 'No times left today — every remaining slot has already passed. Try tomorrow.',
        'already_passed' => 'This appointment time has already passed. Please select a future time.',
        'loading_times' => 'Freie Zeiten werden geprüft…',
        'staff' => 'Teammitglied',
        'any' => 'Wer frei ist',
        'date' => 'Datum',
        'today' => 'Heute',
        'tomorrow' => 'Morgen',
        'next_3' => 'Nächste 3 Tage',
        'next_7' => 'Nächste 7 Tage',
        'custom' => 'Eigenes Datum',
        'done' => 'Fertig',
        'previous_month' => 'Voriger Monat',
        'next_month' => 'Nächster Monat',
        'time' => 'Beginn',
        'ends' => 'Endet um :time',
        'location' => 'Standort',
        'morning' => 'Vormittag',
        'afternoon' => 'Nachmittag',
        'evening' => 'Abend',
    ],

    'details' => [
        'source' => 'Herkunft der Buchung',
        'note' => 'Terminnotiz',
        'note_hint' => 'Nur für diese Buchung. Sie wird nie zu einer dauerhaften Kundennotiz.',
        'note_placeholder' => 'Möchte denselben Schnitt, heute aber etwas kürzer.',
        'client_note' => 'Kundennotiz',
        'client_note_aside' => '— bleibt im Kundenprofil',
        'client_note_hint' => 'Wird bei der Bestätigung am Datensatz gespeichert. Alle, die die Person buchen, sehen sie.',
        'client_note_placeholder' => 'Empfindliche Kopfhaut — keine Hitze direkt an den Ansatz.',
        'choose_source' => 'Herkunft wählen',
        'search_sources' => 'Herkunft suchen…',
    ],

    'duplicate' => [
        'title' => 'Mögliche Doppelbuchung',
        'title_exact' => 'Das sieht nach derselben Buchung zweimal aus',
        'message' => ':client hat am :date bereits :service gebucht.',
        'message_overlap' => 'Das überschneidet sich mit einer bestehenden Buchung derselben Person für dieselbe Leistung.',
        'message_exact' => 'Das scheint eine exakte Dublette einer bestehenden Buchung zu sein — dieselbe Person, dieselbe Leistung, derselbe Standort, dieselbe Zeit.',
        'existing' => 'Bereits gebucht',
        'view' => 'Bestehende Buchung ansehen',
        'ask' => 'Jemand kann dieselbe Leistung an einem Tag wirklich zweimal wollen. Machen Sie weiter, wenn es so ist.',
        'continue' => 'Trotzdem fortfahren',
        'create_anyway' => 'Trotzdem anlegen',
        'cancel' => 'Diese Buchung verwerfen',
        'discard_title' => 'Diese Buchung verwerfen?',
        'discard_body' => 'Das entfernt die Buchung, die gerade aufgenommen wird, samt der Anfrage, unter der sie sich zwischengespeichert hat.',
        'discard_keeps' => 'Der Termin, den diese Person bereits hat, bleibt unberührt.',
        'keep' => 'Buchung behalten',
        'discard_confirm' => 'Verwerfen und löschen',
        'blocked' => 'Diese Person hat an dem Tag bereits eine dieser Leistungen gebucht. Prüfen Sie den Hinweis in der Buchungsansicht, bevor Sie annehmen.',
    ],

    'payment' => [
        'deposit_percent' => 'Anzahlung',
        'deposit_now' => 'Anzahlung jetzt fällig',
        'deposit_now_percent' => ':percent % Anzahlung jetzt fällig',
        'remaining' => 'Restbetrag',
        'type' => 'Zahlungsoption',
        'none' => 'Jetzt keine Zahlung',
        'none_hint' => 'Während der Buchung nichts einziehen.',
        'deposit' => 'Anzahlung nehmen',
        'deposit_hint' => 'Jetzt einen Teil des Gesamtbetrags einziehen.',
        'full' => 'Vollständige Zahlung',
        'full_hint' => 'Jetzt den gesamten Betrag einziehen.',
        'amount' => 'Höhe der Anzahlung',
        /* Die zwei Arten, wie am Tresen über eine Anzahlung gesprochen wird:
           Eine Regelung ist in Prozent geschrieben; getippt wird eine Zahl. */
        'preset' => ':percent %',
        'preset_custom' => 'Eigener Betrag',
        'too_much' => 'Eine Anzahlung kann nicht höher sein als der Gesamtbetrag.',
        /* Eine Anzahlung, auf der die Leistung selbst besteht: Hier wird nicht
           gefragt, wie viel — hier wird es gesagt. */
        'too_little' => 'Diese Leistungen erfordern eine Anzahlung von mindestens :amount.',
        'deposit_required' => 'Anzahlung erforderlich',
        'deposit_required_error' => 'Diese Leistungen erfordern eine Anzahlung — ohne Einzug lässt sich die Buchung nicht annehmen.',
        'deposit_required_hint' => 'Diese Leistungen verlangen eine Anzahlung, es muss also eine eingezogen werden. Sie lässt sich erhöhen, aber nicht entfernen.',
        'deposit_required_percent' => ':percent % des Gesamtbetrags',
        'balance' => 'Offener Betrag',
        'collecting' => 'Wird jetzt eingezogen',
        'nothing_collected' => 'Für diese Buchung wird nichts eingezogen.',
        'action' => 'Art des Einzugs',
        'choose_action' => 'Wählen Sie, wie eingezogen wird',
        'search_actions' => 'Suchen…',
        'action_hint' => 'Wird nur gefragt, wenn es etwas einzuziehen gibt.',
        'actions' => [
            'collect-now' => 'Jetzt einziehen',
            'desk' => 'Am Tresen genommen',
            'link' => 'Zahlungslink senden',
            'later' => 'Bei Ankunft fragen',
            'waive' => 'Erlassen',
        ],
        'action_hints' => [
            'collect-now' => 'Hier abbuchen, bevor die Buchung abgeschlossen ist.',
            'desk' => 'Der Tresen nimmt es persönlich entgegen. Bis dahin bleibt der Betrag offen.',
            'link' => 'Die Person zahlt, wann sie mag. Wird nach Annahme der Buchung per E-Mail geschickt.',
            'later' => 'Wird bei der Ankunft eingezogen. Der Betrag bleibt offen.',
            'waive' => 'Es wird bewusst nichts eingezogen. Wird unter Ihrem Namen erfasst.',
        ],
        'waiver_reason' => 'Warum erlassen wird',
        'waiver_placeholder' => 'Eine Stammkundin, deren Farbe letztes Mal schiefging.',
        'waiver_hint' => 'Wird mit Ihrem Namen und dem Datum aufbewahrt, damit die Entscheidung später zu verantworten ist.',
        'waiver_needed' => 'Sagen Sie, warum die Zahlung erlassen wird.',
        'no_waive_permission' => 'Sie können keine Zahlung erlassen. Fragen Sie eine leitende Person.',
        'collect_now_hint' => 'Die Zahlungsmaske öffnet sich, sobald die Buchung angenommen ist.',
        'link_sent' => 'Zahlungslink an :to gesendet.',
        'link_status' => 'Zahlungslink',
        'link_statuses' => [
            'sent' => 'Gesendet',
            'opened' => 'Geöffnet',
            'paid' => 'Bezahlt',
            'expired' => 'Abgelaufen',
        ],
        'link_no_email' => 'Diese Person hat keine E-Mail-Adresse, der Link hat also kein Ziel.',
    ],

    'comms' => [
        'send' => 'Bestätigung senden',
        'both' => 'SMS + E-Mail',
        'sms' => 'SMS',
        'email' => 'E-Mail',
        'none' => 'Nichts',
        'to_sms' => 'SMS an',
        'to_email' => 'E-Mail an',
        'missing' => 'Weder Nummer noch Adresse hinterlegt, es kann also nichts gesendet werden.',
    ],

    'summary' => [
        'client' => 'Kundin/Kunde',
        'services' => 'Leistungen',
        'staff' => 'Bei',
        'when' => 'Wann',
        'duration' => 'Dauer',
        'total' => 'Gesamt',
        'deposit' => 'Anzahlung',
        'due' => 'Am Tag fällig',
        'nothing' => 'Noch nichts gewählt.',
        'minutes' => '{1} :count Minute|[2,*] :count Minuten',
        'reference' => 'Buchungsreferenz',
        'location' => 'Standort',
        'resource' => 'Ressource',
        'starts' => 'Beginn',
        'ends' => 'Voraussichtliches Ende',
        'subtotal' => 'Zwischensumme',
        'discount' => 'Rabatt',
        'coupon' => 'Gutscheincode',
        'tip_due' => 'Vereinbartes Trinkgeld',
        'amount_due' => 'Fälliger Betrag',
        'tip_paid' => 'Bereits gezahltes Trinkgeld',
        'due_now' => 'Offener Betrag',
        'additional_tip' => 'Zusätzliches Trinkgeld',
        'collect_today' => 'Zahlung heute',
        'tax' => 'Steuer (:rate)',
        'tax_included' => 'Inkl. Steuer (:rate)',
        'paid' => 'Bezahlt',
        'estimate' => 'Die Preise werden bei Annahme der Buchung bestätigt.',
        'status' => 'Status',
        'date' => 'Datum',
        'source' => 'Gebucht über',
    ],

    /*
    | Das Panel, das sich neben der Buchung öffnet, sobald eine Person gewählt
    | ist.
    |
    | Zwei Arten von Tatsachen stehen nebeneinander und sind bewusst
    | unterschiedlich formuliert: „Namentlich verlangt“ ist etwas, das gesagt
    | wurde; „Am häufigsten gebucht“ ist etwas, das der Kalender bemerkt hat.
    | Ein Panel, das beides vermischt, ließe den Tresen die Software zitieren,
    | als hätten die Leute selbst darum gebeten.
    */
    'new_client' => [
        'title' => 'Kundin oder Kunden anlegen',
        'intro' => 'Gerade genug, um die Buchung anzunehmen. Der Rest lässt sich später im Profil ergänzen.',
        'first_name' => 'Vorname',
        'last_name' => 'Nachname',
        'email' => 'E-Mail-Adresse',
        'mobile' => 'Mobilnummer',
        'contact_hint' => 'Eine Mobilnummer oder eine E-Mail — eines von beiden, damit die Bestätigung ein Ziel hat.',
        'needs_contact' => 'Eine Mobilnummer oder eine E-Mail — die Bestätigung braucht ein Ziel.',
        'duplicate' => 'Das ist womöglich schon Kundschaft.',
        'add' => 'Anlegen',
        'add_anyway' => 'Trotzdem anlegen',
        'full_form' => 'Vollständiges Kundenformular',
    ],

    'context' => [
        'preferred' => 'Bevorzugte Fachkraft',
        'also_seen' => 'Auch gesehen bei',
        'last' => 'Letzte Buchung',
        'again' => 'Dasselbe erneut buchen',
        'recent' => 'Letzte Besuche',
        'average' => 'Durchschnittliche Bewertung :rating',
        'preferences' => 'Buchungspräferenzen',
        'none' => 'Noch keine Historie — das ist die erste Buchung.',
        'visits' => '{0} Keine früheren Besuche|{1} :count früherer Besuch|[2,*] :count frühere Besuche',
        'from_diary' => 'Aus der Buchungshistorie',
        'kinds' => [
            'asked_for' => 'Namentlich verlangt',
            'most_booked' => 'Am häufigsten gebucht',
            'also_seen' => 'Schon einmal dort',
        ],
        'cadence' => '{1} Bucht etwa wöchentlich|[2,*] Bucht etwa alle :count Wochen',
        'windows' => [
            'morning' => 'Bevorzugt Termine am Vormittag',
            'afternoon' => 'Bevorzugt Termine am Nachmittag',
            'evening' => 'Bevorzugt Termine am Abend',
        ],
        'walk_in' => 'Laufkundschaft',
        'walk_in_hint' => 'Über diesen Termin hinaus wird nichts gespeichert.',
        'remove' => 'Diese Person aus der Buchung entfernen',
    ],

    'confirm' => 'Buchung bestätigen',
    'draft' => 'Als Entwurf speichern',
    'cancel' => 'Abbrechen',

    /*
    | Was noch fehlt, immer nur eines auf einmal. Eine Liste aller offenen
    | Fragen ist eine Wand; die nächste Frage ist eine Anweisung.
    */
    'lead' => [
        'created' => 'Buchungsreferenz :reference angelegt. Im Moment ist nichts zu tun.',
    ],

    /*
    | Die Buchung speichert sich selbst, während sie ausgefüllt wird.
    |
    | Leise gesagt und neben der Referenz, weil es keine Nachricht ist: Am
    | Empfang wird gerade mit jemandem gesprochen, und ein Speichern, das sich
    | alle paar Sekunden meldet, wäre das Lauteste auf dem Bildschirm.
    */
    'autosave' => [
        'reference' => 'Buchungsnr.: :reference',
        'saving' => 'Wird gespeichert…',
        'saved' => 'Gespeichert',
        'failed' => 'Nicht gespeichert',
        'draft' => 'Entwurf',
    ],

    'steps' => [
        'save' => 'Speichern und weiter',
        'edit' => 'Bearbeiten',
    ],

    'blockers' => [
        'client' => 'Wählen Sie eine Person, oder nehmen Sie es als Laufkundschaft auf.',
        'guest' => 'Geben Sie der Laufkundschaft einen Namen.',
        'service' => 'Wählen Sie mindestens eine Leistung.',
        'time' => 'Wählen Sie eine Anfangszeit.',
        'ready' => 'Bereit zum Buchen.',
    ],

    'booked' => 'Buchung für :name bestätigt.',
    'no_time_yet' => 'Noch keine Zeit',
    'saved_draft' => 'Buchung als Entwurf gespeichert.',

    'empty' => 'Keine Buchung passt zu diesen Filtern.',
    'empty_hint' => 'Setzen Sie die Filter zurück, um alle Termine zu sehen.',
    'none_yet' => 'Noch keine Buchungen',
    'none_yet_hint' => 'Nehmen Sie die erste an, dann erscheint sie hier.',

    'showing' => ':from–:to von :total Buchungen werden angezeigt',
    'results' => [
        'zero' => 'Keine Buchungen',
        'one' => '1 Buchung',
        'many' => ':count Buchungen',
        'clear' => 'Filter zurücksetzen',
    ],
    'actions_for' => 'Aktionen für :name',

    /*
    | Geld an einer Buchung.
    |
    | Fast jede Zahlung, die ein Salon annimmt, passiert woanders — in der
    | Kasse, am Terminal neben dem Tresen, in irgendeiner Banking-App. Deshalb
    | geht es sprachlich ums Festhalten dessen, was geschehen ist, nicht ums
    | Abbuchen. „Als bezahlt markieren“ ist das Wort eines Menschen dafür, und
    | sagt das auch.
    */
    /*
    | Die Detailseite der Buchung.
    |
    | Wird gelesen wie das Kundenprofil: die Person links, die Arbeit in der
    | Mitte, was zu tun ist rechts.
    */
    /*
    | Was sich mit einer Buchung tun lässt, nachdem sie angenommen wurde.
    |
    | Vier Handlungen, und jede stellt dieselben zwei Fragen: warum, und gibt es
    | sonst noch etwas. Die Gründe selbst gehören dem Betrieb — festgelegt unter
    | Einstellungen → Gründe — deshalb nennt hier nichts einen davon.
    */
    /*
    | Die Ansichten, in denen am Tresen gearbeitet wird.
    |
    | Keine gespeicherten Suchen: Jede ist eine Frage, die am Empfang zwischen
    | neun und sechs tatsächlich gestellt wird — deshalb öffnet die Seite auch
    | auf „Heute“.
    */
    'tabs' => [
        'today' => 'Heute',
        'next-3' => 'Nächste 3 Tage',
        'month' => 'Monat',
        'check-in' => 'Check-in offen',
        'more' => 'Mehr',
        'all' => 'Alle Buchungen',
        'completed' => 'Abgeschlossen',
        'cancelled' => 'Storniert',
        'no-shows' => 'Nicht erschienen',
        'declined' => 'Abgelehnt',

        'summary' => [
            'total' => 'Buchungen heute',
            'checked_in' => 'Eingecheckt',
            'pending' => 'Check-in offen',
            'completed' => 'Abgeschlossen',
            'no_show' => 'Nicht erschienen',
        ],

        /* Wo sie stehen und — solange sie erwartet werden — wie weit sie von
           der Zeit abweichen. „12 Min. zu spät“ ist das, worauf der Empfang
           reagiert; die reine Uhrzeit zwingt jemanden, auf die Uhr zu sehen und
           selbst zu rechnen, vierzigmal an einem Vormittag. */
        'arrival' => [
            'checked_in' => 'Eingecheckt',
            'waiting' => 'Noch nicht da',
            'not_due' => 'Heute nicht erwartet',
            'done' => 'Bedient',
            'absent' => 'Nicht erschienen',
            'due' => 'Jetzt erwartet',
            'later' => 'Später heute',
            'early' => ':count Min. zu früh',
            'late' => ':count Min. zu spät',
        ],

        'previous_month' => 'Voriger Monat',
        'next_month' => 'Nächster Monat',
        'empty' => [
            'today' => 'Für heute ist nichts gebucht.',
            'next-3' => 'Für die nächsten drei Tage ist nichts gebucht.',
            'check-in' => 'Niemand wartet auf den Check-in.',
        ],
    ],

    'resources' => [
        'none_available' => 'Zur gewählten Zeit ist für diese Leistung keine Ressource frei.',
        'auto' => 'Automatisch zugewiesen',
        'manual' => 'Manuell gewählt',
        'change' => 'Ressource wechseln',
        'choose' => 'Ressource wählen',
        'unavailable' => 'Nicht verfügbar',
        'currently' => 'Aktuell zugewiesen',
        'updated' => 'Ressource geändert: :name',
    ],

    'status' => [
        'check-in' => [
            'action' => 'Einchecken',
            'title' => 'Person einchecken',
            'intro' => 'Die Person ist da. Ihr Termin wechselt auf eingecheckt und die Uhrzeit wird festgehalten.',
            'confirm' => 'Einchecken',
            'note' => 'Check-in-Notiz',
            'note_hint' => 'Optional. „Zehn Minuten zu früh da“ und alles andere, was das Team wissen sollte.',
            'done_at' => 'Um :time von :name eingecheckt',
            'already' => 'Eingecheckt',
        ],
        /* Keine Gründeliste und kaum ein Dialog: Einen Termin abzuschließen ist
           der gewöhnliche Ausgang, und ein Pflichtfeld davor bekäme jedes Mal
           dieselbe Antwort. */
        'complete' => [
            'action' => 'Abschließen',
            'title' => 'Termin abschließen',
            'intro' => 'Die Arbeit ist getan. Der Termin wird als erbracht erfasst, und die Person wird nach ihrem Eindruck gefragt, wenn Bewertungsanfragen eingeschaltet sind.',
            'confirm' => 'Termin abschließen',
            'note' => 'Abschlussnotiz',
            'note_hint' => 'Optional. Für das Team, nicht für die Kundschaft.',
        ],
        'no-show' => [
            'action' => 'Nicht erschienen',
            'title' => 'Buchung als nicht erschienen markieren',
            'intro' => 'Der Termin bleibt im Datensatz; die Person wird als nicht erschienen vermerkt.',
            'reason' => 'Grund',
            'confirm' => 'Speichern',
        ],
        'cancelled' => [
            'action' => 'Buchung stornieren',
            'title' => 'Buchung stornieren',
            'intro' => 'Der Termin wird abgesagt und die Zeit, die er belegt hat, wieder freigegeben.',
            'reason' => 'Stornogrund',
            /* „Buchung behalten“ statt „Abbrechen“: In einem Dialog über das
               Stornieren ist ein Knopf mit „Abbrechen“ der einzige, den niemand
               zweimal gleich liest. */
            'dismiss' => 'Buchung behalten',
            'confirm' => 'Buchung stornieren',
        ],
        'declined' => [
            'action' => 'Buchung ablehnen',
            'title' => 'Buchung ablehnen',
            'intro' => 'Die Anfrage wird abgelehnt. Es wird nichts gebucht, und der Person kann der Grund genannt werden.',
            'reason' => 'Ablehnungsgrund',
            'confirm' => 'Buchung ablehnen',
        ],
        'reschedule' => [
            'action' => 'Buchung verschieben',
            'title' => 'Buchung verschieben',
            'intro' => 'Dieselbe Buchung zu einer anderen Zeit. Referenz, Person und Rechnung bleiben, wie sie sind.',
            'reason' => 'Grund der Verschiebung',
            'confirm' => 'Verschiebung speichern',
            'current' => 'Aktuell',
            'new_date' => 'Neues Datum',
            'new_time' => 'Neue Uhrzeit',
            'staff' => 'Teammitglied',
            'location' => 'Standort',
            'pick_date' => 'Wählen Sie ein Datum, um freie Zeiten zu sehen.',
            'no_slots' => 'An dem Tag ist nichts frei.',
            'loading' => 'Freie Zeiten werden geprüft…',
        ],

        'choose_reason' => 'Grund wählen',
        'note' => 'Notiz',
        'note_hint' => 'Optional. Für das Team, nicht für die Kundschaft.',
        'details' => 'Weitere Angaben',
        'details_hint' => 'Für diesen Grund erforderlich.',
        'details_required' => 'Dieser Grund verlangt eine Erklärung.',
        'reason_unavailable' => 'Dieser Grund steht nicht mehr zur Verfügung. Bitte einen anderen wählen.',
        'slot_taken' => 'Diese Zeit ist nicht frei. Bitte eine andere wählen.',
        'dismiss' => 'Abbrechen',

        'done' => [
            'check-in' => 'Person eingecheckt.',
            'complete' => 'Termin abgeschlossen.',
            'no-show' => 'Als nicht erschienen markiert.',
            'cancelled' => 'Buchung storniert.',
            'declined' => 'Buchung abgelehnt.',
            'reschedule' => 'Buchung verschoben.',
        ],
    ],

    /*
    | Die eigene Historie der Buchung: alles, was mit ihr geschehen ist — in den
    | Worten, die die Gründe an dem Tag trugen, nicht in denen von heute.
    */
    'activity' => [
        'title' => 'Buchungsaktivität',
        'none' => 'Mit dieser Buchung ist noch nichts geschehen.',
        'system' => 'StyleDesk',
        'by' => 'von :name',
        'reason' => 'Grund',
        'note' => 'Notiz',
        'previous' => 'Vorher',
        'new' => 'Nachher',
        'events' => [
            'no-show' => 'Buchung als nicht erschienen markiert',
            'cancelled' => 'Buchung storniert',
            'declined' => 'Buchung abgelehnt',
            'confirmed' => 'Buchung verschoben',
            'pending' => 'Buchung verschoben',
            'arrived' => 'Person eingecheckt',
        ],
    ],

    'detail' => [
        'payment_status' => 'Zahlung',
        'book_again' => 'Erneut buchen',
        'cancel_booking' => 'Buchung stornieren',
        'reschedule' => 'Verschieben',
        'soon_hint' => 'Noch nicht gebaut — Stornieren und Verschieben verändern beide den Kalender.',
        'services' => 'Details zu den Leistungen',
        'notes' => 'Buchungsnotizen',
        'no_notes' => 'Zu diesem Termin wurde nichts notiert.',
        'payment_summary' => 'Zahlungsübersicht',
        'take_payment' => 'Zahlung annehmen',
        'paid_in_full' => 'Vollständig bezahlt',
        'transactions' => 'Zahlungsvorgänge',
        'no_transactions' => 'Für diese Buchung wurde noch kein Geld eingenommen.',
        'recorded_by' => 'erfasst von :name',
        'taken_by' => 'Angenommen von :name am :when',
        'someone' => 'jemand aus dem Team',
        'updated' => 'zuletzt aktualisiert :when',
        'walk_in' => 'An der Tür angenommen.',
        /* Ausdrücklich gesagt, weil Profil und diese Seite auseinandergehen,
           während sich die Person ändert — und genau darum geht es. */
        'snapshot' => 'Stand :when, als diese Buchung angenommen wurde.',
    ],

    'pay' => [
        'coupon' => 'Gutscheincode',
        'coupon_placeholder' => 'Gutscheincode eingeben',
        'apply' => 'Anwenden',
        'remove_coupon' => 'Entfernen',
        'add_tip' => 'Trinkgeld hinzufügen',
        'no_tip' => 'Kein Trinkgeld',
        'custom_tip' => 'Eigener Betrag',
        'discount' => 'Rabatt',
        'tip' => 'Trinkgeld',
        'total_due' => 'Gesamt fällig',
        'paying_by' => 'Zahlung per',
        'paying_by_hint' => 'Manche Leistungen kosten bar einen anderen Betrag. Der Gesamtbetrag richtet sich danach.',
        'title' => 'Zahlung',
        'due' => 'Fälliger Betrag',
        'collect_now' => 'Jetzt einzuziehender Betrag',
        'booking_total' => 'Buchungssumme',
        'remaining' => 'Restbetrag',
        'method' => 'Wie wird gezahlt?',
        'change_method' => 'Ändern',
        'back' => 'Zurück zur Buchungsübersicht',
        'again' => 'Änderungen speichern und weiter',
        'skip' => 'Ohne Zahlung bestätigen',
        'skip_hint' => 'Die Buchung kommt zustande und der Betrag bleibt offen.',
        'pay_amount' => ':amount zahlen',
        'record' => 'Barzahlung erfassen',
        'mark_paid' => 'Als bezahlt markieren',
        'marking' => 'Wird erfasst…',
        'amount' => 'Betrag',
        'received' => 'Erhaltener Betrag',
        'change' => 'Rückgeld',
        'reference' => 'Referenz',
        'reference_hint' => 'Was die Zahlung auf der anderen Seite anzeigt — optional.',
        'cardholder' => 'Name auf der Karte',
        'card_number' => 'Kartennummer',
        'expiry' => 'Gültig bis',
        'cvv' => 'Prüfziffer',
        'zip' => 'Postleitzahl der Rechnungsadresse',
        'card_safe' => 'Kartendaten gehen direkt an den Zahlungsdienstleister. StyleDesk speichert sie nie.',
        'nothing_to_take' => 'Gib einen Betrag oder ein Trinkgeld ein — eine Zahlung über nichts ist keine Zahlung.',
        'no_card_provider' => 'Es ist kein Kartendienstleister verbunden, StyleDesk kann die Karte also nicht selbst belasten. Nehmen Sie sie am Terminal und erfassen Sie sie unten.',
        'terminal' => 'Am Terminal genommen',
        'not_ready' => 'Noch nicht eingerichtet. Fügen Sie das Konto in den Betriebseinstellungen hinzu.',
        'cash_only' => 'Diese Buchung ist bar kalkuliert und wird deshalb bar beglichen.',
        'cash_only_row' => 'Nicht verfügbar — diese Buchung ist bar kalkuliert.',
        'handle_hint' => 'Lesen Sie das vor und markieren Sie die Zahlung dann als erhalten.',
        'short_cash' => 'Das ist weniger als der zu zahlende Betrag.',
        'failed' => 'Diese Zahlung konnte nicht erfasst werden. Es wurde nichts abgebucht — bitte erneut versuchen.',
        'partial' => ':paid von :total bezahlt · :due noch offen',
    ],

    'methods' => [
        'card' => ['name' => 'Kreditkarte', 'hint' => 'Jetzt belastet oder am Terminal genommen'],
        'cash' => ['name' => 'Bargeld', 'hint' => 'Am Tresen gezählt'],
        'paypal' => ['name' => 'PayPal', 'hint' => 'An das PayPal des Betriebs gesendet'],
        'zelle' => ['name' => 'Zelle', 'hint' => 'An das Zelle des Betriebs gesendet'],
        'cash-app' => ['name' => 'Cash App', 'hint' => 'An die Cash App des Betriebs gesendet'],
        'venmo' => ['name' => 'Venmo', 'hint' => 'An das Venmo des Betriebs gesendet'],
    ],

    'payment_statuses' => [
        'authorized' => ['label' => 'Autorisiert'],
        'cancelled' => ['label' => 'Storniert'],
        'disputed' => ['label' => 'Angefochten'],
        'chargeback' => ['label' => 'Rückbuchung'],
        'unpaid' => ['label' => 'Unbezahlt'],
        'partial' => ['label' => 'Teilweise bezahlt'],
        'paid' => ['label' => 'Bezahlt'],
        'pending' => ['label' => 'Zahlung ausstehend'],
        'failed' => ['label' => 'Fehlgeschlagen'],
        'refunded' => ['label' => 'Erstattet'],
        'partially-refunded' => ['label' => 'Teilweise erstattet'],
    ],

    /* The confirmation as a text message. One segment where it can be:
       a text is charged by the 160 characters, and a template that
       quietly became three is a bill nobody agreed to. */
    'sms' => [
        'confirmation' => ':business über StyleDesk: Hallo :name, dein Termin ist bestätigt für :date um :time bei :staff. Antworte YES zum Bestätigen, CANCEL für eine Änderung, STOP zum Abmelden. Ref :reference',
    ],

    'confirmation' => [
        'title' => 'Buchung bestätigt',
        'made' => 'Der Termin steht im Kalender.',
        'payment' => 'Zahlung',
        'view' => 'Buchung ansehen',
        'another' => 'Weitere Buchung anlegen',
        'to_list' => 'Zurück zu den Buchungen',
        'print' => 'Bestätigung drucken',
        'receipt' => 'Beleg herunterladen',
        'receipt_title' => 'Beleg',
        'send' => 'Bestätigung senden',
        'sending' => 'Wird gesendet…',
        'sent' => 'Bestätigung an :to gesendet.',
        'no_email' => 'Für diese Person ist keine E-Mail-Adresse hinterlegt.',
        'no_mobile' => 'Zu dieser Buchung ist keine Mobilnummer hinterlegt. Trage eine beim Kunden ein oder sende per E-Mail.',
        'no_sms_consent' => 'Dieser Kunde hat SMS deaktiviert. Sende es stattdessen per E-Mail.',
        'link_failed' => 'Die Buchung steht, aber der Zahlungslink konnte nicht per E-Mail gesendet werden. Rufen Sie stattdessen an.',
        'due_notice' => 'Fällige Zahlung :amount',
        'amount_due' => 'Fälliger Betrag',
    ],

    'email' => [
        'subject' => ':business — Ihr Termin am :date',
        'headline' => 'Ihr Termin ist bestätigt',
        'intro' => 'Danke, :name. Hier sind die Details.',
        'footer_note' => 'Etwas ändern oder absagen? Antworten Sie auf diese E-Mail oder rufen Sie uns an.',
        'link_subject' => ':business — Zahlung für Ihren Termin am :date',
        'link_headline' => 'So bezahlen Sie Ihren Termin',
        'link_intro' => 'Danke, :name. Hier steht, was noch offen ist und wie Sie es begleichen.',
        'link_amount' => 'Zu zahlender Betrag',
        'link_cta' => 'Zahlungsdetails ansehen',
        'link_expiry' => 'Dieser Link gilt bis :when.',
    ],

    /*
    | Die Seite, auf der Kundschaft über einen Zahlungslink landet.
    |
    | Geschrieben für jemanden, der StyleDesk nicht nutzt und nie nutzen wird:
    | Sie sagt, was offen ist und wohin es geht — und nichts über die internen
    | Abläufe des Betriebs.
    */
    'pay_link' => [
        'title' => 'Ihren Termin bezahlen',
        'amount' => 'Angeforderter Betrag',
        'balance' => 'Offen auf dieser Buchung: :amount',
        'how' => 'So bezahlen Sie',
        'reference_hint' => 'Bitte geben Sie :reference an, damit wir die Zahlung Ihrem Termin zuordnen können.',
        'no_handles' => 'Rufen Sie uns an, wir nehmen es telefonisch entgegen.',
        'expired' => 'Dieser Zahlungslink ist abgelaufen.',
        'expired_hint' => 'Melden Sie sich, wir schicken Ihnen einen neuen.',
        'settled' => 'Diese Buchung ist vollständig bezahlt.',
        'settled_hint' => 'Es ist nichts weiter offen. Vielen Dank.',
        'footer' => 'Dieser Link gilt für einen Termin und meldet Sie nirgends an.',
    ],

    'validation' => [
        'who' => 'Wählen Sie eine Person, oder geben Sie der Laufkundschaft einen Namen.',
    ],
];
