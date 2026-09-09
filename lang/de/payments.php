<?php

declare(strict_types=1);

return [

    'title' => 'Zahlungen',
    'intro' => 'Ob Sie Zahlungen annehmen, wer sie abwickelt und was Sie akzeptieren.',

    'enable' => 'Zahlungen aktivieren',
    'enable_hint' => 'Ist das aus, erfasst StyleDesk keine Beträge zu Buchungen und die Kasse bleibt verborgen.',

    'processor' => 'Zahlungsdienstleister',
    'processor_hint' => 'Ein Dienstleister wickelt Ihre Kartenzahlungen ab. Bargeld und Überweisungen können Sie unabhängig davon immer erfassen.',
    'active' => 'AKTIV',
    'coming_soon' => 'Demnächst',
    'use_this' => 'Diesen verwenden',

    'gateways' => [
        'manual' => [
            'name' => 'Zahlungen nur erfassen',
            'description' => 'Geld, das auf anderem Weg eingeht — bar, per Überweisung oder als Karte an Ihrem eigenen Terminal. StyleDesk hält fest, dass es eingegangen ist; es bucht bei niemandem ab.',
            'unavailable' => '',
        ],
        'stripe' => [
            'name' => 'Stripe',
            'description' => 'Nehmen Sie Karten, Apple Pay und Google Pay online und am Tresen an, mit Auszahlung auf Ihr eigenes Bankkonto.',
            'unavailable' => '',
        ],
        'square' => [
            'name' => 'Square',
            'description' => 'Verbinden Sie das Square-Konto, das Sie bereits nutzen — samt Ihrer vorhandenen Lesegeräte und Terminals.',
            'unavailable' => 'Noch nicht verfügbar. StyleDesk arbeitet daran.',
        ],
    ],

    /* Card brands, as a person writes them rather than as a gateway keys
       them. Anything not listed falls back to its own key, tidied up — a new
       brand should read as itself rather than as nothing. */
    'methods_list' => [
        'no_vault' => 'Es ist kein Zahlungsdienstleister verbunden, daher können keine Karten hinterlegt werden.',
        'default_set' => ':card ist jetzt die Standard-Zahlungsmethode.',
        'removed' => ':card wurde entfernt.',
        'title' => 'Zahlungsmethoden',
        'none' => 'Keine Karten gespeichert',
        'none_hint' => 'Eine hier gespeicherte Karte kann für Verlängerungen ohne anwesende Kundin belastet werden.',
        'default' => 'Standard',
        'make_default' => 'Als Standard festlegen',
        'expires' => 'Gültig bis :date',
        'expired' => 'Abgelaufen',
        'expiring' => 'Läuft diesen Monat ab',
        'needs_attention' => 'Zahlungsmethode braucht Aufmerksamkeit',
        'add' => 'Karte hinzufügen',
        'remove' => 'Karte entfernen',
        'remove_confirm' => 'Diese Karte entfernen? Sie kann dann nicht mehr belastet werden.',
        'in_use' => 'Diese Karte verlängert :name. Wählen Sie zuerst eine andere Zahlungsmethode.',
        'used_by' => 'Verlängert :name',
        'gateway' => 'Abgewickelt über :name',
        'statuses' => [
            'active' => 'Aktiv',
            'expired' => 'Abgelaufen',
            'removed' => 'Entfernt',
        ],
    ],

    'brands' => [
        'visa' => 'Visa',
        'mastercard' => 'Mastercard',
        'amex' => 'American Express',
        'discover' => 'Discover',
        'diners' => 'Diners Club',
        'jcb' => 'JCB',
        'unionpay' => 'UnionPay',
    ],

    'stripe' => [
        'not_settled' => 'Die Karte wurde nicht belastet. Die Zahlung wurde nicht abgeschlossen.',
        'connect' => 'Stripe verbinden',
        'continue' => 'Einrichtung fortsetzen',
        'manage' => 'Konto verwalten',
        'disconnect' => 'Trennen',
        'not_connected' => 'Noch kein Stripe-Konto verbunden.',
        'no_account' => 'Es gibt kein Stripe-Konto zum Öffnen.',
        'payout_account' => 'Auszahlung auf •••• :last4',
        'no_payout_account' => 'Noch kein Auszahlungskonto',
        'connected' => 'Stripe ist verbunden. Sie können jetzt Kartenzahlungen annehmen.',
        'still_needed' => 'Stripe braucht noch ein paar Angaben, bevor Sie Zahlungen annehmen können.',
        'disconnected' => 'Stripe wurde getrennt. Bargeld und Überweisungen können Sie weiterhin erfassen.',
        'failed' => 'Stripe war nicht erreichbar. :reason',
        'not_ready' => 'Dieser Betrieb kann noch keine Kartenzahlungen annehmen.',
        'outstanding' => 'Stripe braucht noch: :fields',
        'refunded_at_stripe' => 'Im Stripe-Dashboard erstattet.',
        'modes' => [
            'platform' => 'Über StyleDesk verbunden',
            'own' => 'Ihr eigenes Stripe-Konto',
        ],
        'use_own' => 'Stattdessen mein eigenes Stripe-Konto verwenden',
        'replace_keys' => 'Meine Stripe-Schlüssel ersetzen',
        'use_own_hint' => 'Sie haben schon Stripe? Fügen Sie Ihre Schlüssel ein, dann nutzt StyleDesk Ihr Konto direkt. Zahlungen, Auszahlungen und Streitfälle bleiben ganz zwischen Ihnen und Stripe.',
        'secret_key' => 'Geheimer Schlüssel',
        'publishable_key' => 'Veröffentlichbarer Schlüssel',
        'save_keys' => 'Speichern und prüfen',
        'keys_saved' => 'Ihre Stripe-Schlüssel wurden gespeichert und geprüft.',
        'key_rejected' => 'Stripe hat diesen Schlüssel nicht akzeptiert. :reason',
        'key_empty' => 'Es wurde kein Schlüssel angegeben.',
        'key_warning' => 'Ein geheimer Schlüssel kann auf Ihrem Stripe-Konto abbuchen, erstatten und alles lesen. StyleDesk verschlüsselt ihn und zeigt ihn nie wieder an — behandeln Sie ihn wie ein Passwort, und nutzen Sie einen eingeschränkten Schlüssel, wenn Sie begrenzen möchten, was StyleDesk darf.',
        'platform_not_configured' => 'StyleDesk ist nicht dafür eingerichtet, Stripe-Konten anzulegen.',
        'platform_unavailable' => 'Die Verbindung über StyleDesk ist in dieser Installation nicht verfügbar. Ihr eigenes Stripe-Konto können Sie unten trotzdem nutzen.',
        'statuses' => [
            'connected' => 'Verbunden',
            'needs_attention' => 'Prüfung nötig',
            'incomplete' => 'Einrichtung unvollständig',
        ],
        /* Einmal gesagt, weil es das ist, was Inhaber:innen am meisten wissen
           wollen — und der Grund, warum Connect statt eines gemeinsamen
           Händlerkontos gewählt wurde. */
        'money_note' => 'Zahlungen gehen direkt auf Ihr eigenes Stripe-Konto und Ihre eigene Bank. StyleDesk hält Ihr Geld nie.',
    ],

    'methods' => 'Akzeptierte Zahlungsarten',
    'methods_hint' => 'Was Ihr Team an der Kasse wählen kann. Einiges wickelt Ihr Kartendienstleister ab; der Rest wird anders vereinnahmt und hier erfasst.',
    'needs_processor' => 'Erfordert einen verbundenen Kartendienstleister',

    'method_names' => [
        'card' => 'Kredit-/Debitkarte',
        'cash' => 'Bargeld',
        'apple_pay' => 'Apple Pay',
        'google_pay' => 'Google Pay',
        'gift_card' => 'Geschenkgutschein',
        'store_credit' => 'Guthaben',
        'paypal' => 'PayPal',
        'zelle' => 'Zelle',
        'venmo' => 'Venmo',
        'cash-app' => 'Cash App',
        'external' => 'Andere / externe Zahlung',
    ],

    'deposit' => 'Anzahlung bei Buchung',
    'deposit_hint' => 'Was Sie im Voraus verlangen, wenn eine Buchung angenommen wird.',
    'deposit_type' => 'Anzahlung',
    'deposit_value' => 'Betrag',
    'deposit_types' => [
        'none' => 'Keine Anzahlung',
        'fixed' => 'Fester Betrag',
        'percent' => 'Prozentsatz der Buchung',
    ],
    /* Die drei Ebenen, einmal gesagt. Eine Leistung mit eigener Anzahlung und
       eine Buchung, die beide überschreibt, sind die anderen zwei — und wer
       die Reihenfolge nicht kennt, versteht nicht, warum eine Leistung dies
       hier ignoriert. */
    'deposit_levels' => 'Das ist die Voreinstellung. Eine Leistung kann eine eigene verlangen, und eine einzelne Buchung kann beide überschreiben.',

    'save' => 'Speichern',
    'saved' => 'Ihre Zahlungseinstellungen wurden gespeichert.',
];
