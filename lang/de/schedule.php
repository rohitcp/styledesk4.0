<?php

declare(strict_types=1);

/*
| Einer Person aus dem Team einen Dienstplan zuweisen.
|
| Die Sprache hält drei Dinge auseinander, die leicht zu verwechseln sind und
| deren Unterschied erst im Text wirklich gemacht wird: Die Öffnungszeiten sagen,
| wann der Betrieb offen ist; eine Schichtregel ist das Muster, dem jemand folgt;
| und der Dienstplan darunter ist die datierte Wirklichkeit, die dieses Muster
| erzeugt hat.
*/

return [

    'year' => 'Jahr',
    'monthly_summary' => 'Monatsübersicht',
    'month_hours' => 'Stunden',
    'month_shifts' => 'Schichten',
    'month_days' => 'Arbeitstage',
    'bookings_pending' => 'Die Buchungszahlen kommen mit dem Buchungsmodul.',

    'working_schedule' => 'Arbeitsplan',
    'assign' => 'Dienstplan zuweisen',
    'assign_title' => 'Dienstplan zuweisen',
    'assign_intro' => 'Weisen Sie der gewählten Person und dem gewählten Zeitraum einen Arbeitsplan zu.',
    'assign_for' => 'Person',
    'period' => 'Zeitraum des Dienstplans',
    'assigning' => 'Wird zugewiesen…',

    'add_shift' => 'Schicht hinzufügen',
    'add_for_day' => 'Zum Dienstplan hinzufügen',
    'delete_day' => 'Dienstplan löschen',
    'delete_day_title' => 'Dienstplan löschen?',
    'delete_day_confirm' => 'Damit wird der Arbeitsplan von :name für den :date entfernt.',
    'day_deleted' => 'Dienstplan für den :date gelöscht.',
    'mark_on_leave' => 'Als abwesend markieren — demnächst',
    'showing' => ':from–:to von :total Tagen werden angezeigt',
    'actions_for' => 'Aktionen für :name',

    'filters' => [
        'period' => 'Zeitraum',
        'month' => 'Monat',
        'year' => 'Jahr',
        'apply' => 'Anwenden',
        'edit_period' => 'Monat wechseln',
        'reset' => 'Zurücksetzen',
    ],

    'periods' => [
        'month' => 'Ein Monat',
        '3-months' => '3 Monate',
        '6-months' => '6 Monate',
        '9-months' => '9 Monate',
        'year' => 'Ein Jahr',
    ],

    'columns' => [
        'date' => 'Datum',
        'day' => 'Tag',
        'working' => 'Arbeitsstatus',
        'time' => 'Zeit',
        'status' => 'Status',
        'published_on' => 'Veröffentlicht am',
        'published_by' => 'Veröffentlicht von',
        'hours' => 'Stunden gesamt',
        'bookings' => 'Buchungen',
    ],

    'empty' => 'Kein Dienstplan gefunden',
    'empty_hint' => 'Für den gewählten Zeitraum gibt es keine Dienstplaneinträge.',

    'duration_intro' => 'Der ganze Monat wird auf einmal geplant. Sobald der Dienstplan offen ist, können Sie jeden Tag ändern.',
    'continue_label' => 'Weiter',
    'back' => 'Zurück',
    'close' => 'Schließen',
    'save' => 'Speichern',
    'save_and_publish' => 'Speichern und veröffentlichen',
    'update_and_publish' => 'Aktualisieren und veröffentlichen',
    'rule_change_title' => 'Die Tage aus dieser Regel neu aufbauen?',
    'rule_change_confirm' => 'Die Tage unten werden erneut aus der gewählten Regel gefüllt, und Ihre Änderungen hier gehen verloren.',
    'rule_change_label' => 'Tage neu aufbauen',
    'leave_title' => 'Ohne Speichern verlassen?',
    'leave_confirm' => 'Dieser Dienstplan hat ungespeicherte Änderungen. Wenn Sie jetzt gehen, gehen sie verloren.',
    'leave_confirm_label' => 'Änderungen verwerfen',
    'needs_javascript' => 'Einen Dienstplan zu bauen braucht JavaScript, das in diesem Browser abgeschaltet ist.',

    'summary' => '{0} Nichts geplant|{1} :hours geplante Stunden · :count Arbeitstag|[2,*] :hours geplante Stunden · :count Arbeitstage',
    'hours_short' => ':count Std.',

    'working' => 'Arbeitet',
    'off' => 'Frei',
    'not_working' => 'Arbeitet nicht',
    'week_number' => 'Woche :number',
    'add_period' => '+ Arbeitszeitraum hinzufügen',
    'remove_period' => 'Diesen Zeitraum entfernen',
    'starts_at' => 'Beginn',
    'ends_at' => 'Ende',
    'break' => 'Pause',
    'no_break' => 'Keine Pause',

    'assigned' => '{1} :count Schicht zugewiesen.|[2,*] :count Schichten zugewiesen.',
    'nothing_to_assign' => 'Es wurden keine Arbeitstage gewählt, also wurde nichts zugewiesen.',
    'replaced_note' => 'Das Zuweisen ersetzt die Schichten, die in diesem Zeitraum bereits stehen.',

    'shift_rule' => 'Schichtregel',
    'no_shift_rule' => 'Keine Schichtregel',
    'change_rule' => 'Wechseln',
    'prefill_hint' => 'Eine Regel zu wählen füllt die Tage unten aus den Öffnungszeiten und den Schichtzeiten der Regel. Einen Tag hier zu ändern ändert nur diesen Dienstplan, nie die Regel.',

    'refused' => '{1} Der Dienstplan wurde nicht gespeichert: ein Tag verstößt gegen die Schichtregel.|[2,*] Der Dienstplan wurde nicht gespeichert: :count Tage verstoßen gegen die Schichtregel.',
    'refused_title' => 'Dieser Dienstplan wurde nicht gespeichert.',

    'publish_statuses' => [
        'draft' => 'Entwurf',
        'published' => 'Veröffentlicht',
    ],

    /*
    | Das Veröffentlichen.
    |
    | Einen Dienstplan zuzuweisen und jemandem davon zu erzählen sind zwei
    | getrennte Handlungen, und der Text ist die Stelle, an der diese Trennung
    | tatsächlich gemacht wird: Ein Entwurf ist die Leitung, die noch überlegt;
    | veröffentlicht ist die Arbeitswoche der Person.
    */
    'draft_badge' => 'Dienstplan-Entwurf',
    'published_badge' => 'Veröffentlicht',
    'published_notice_title' => 'Dieser Dienstplan wurde bereits veröffentlicht.',
    'published_notice' => 'Jede Änderung hier aktualisiert den veröffentlichten Dienstplan von :name. Beim erneuten Veröffentlichen bekommt die Person eine E-Mail, dass sich ihr Plan geändert hat. Als Entwurf zu speichern sagt ihr nichts.',
    'locked' => 'Veröffentlicht — die Person weiß von diesem Tag.',
    'published_on' => 'Veröffentlicht am :date',
    'published_by' => 'von :name',
    'changes_badge' => 'Änderungen nicht veröffentlicht',
    'changes_hint' => 'Dieser Dienstplan wurde am :date veröffentlicht und seither bearbeitet. :name weiß nichts von den Änderungen.',
    'draft_hint' => 'Bisher wurde nichts gesendet. :name bekommt erst beim Veröffentlichen eine E-Mail.',
    'published_hint' => ':name hat diesen Dienstplan per E-Mail bekommen.',

    'save_draft' => 'Entwurf speichern',
    'publish' => 'Dienstplan veröffentlichen',
    'publish_changes' => 'Änderungen veröffentlichen',
    'publishing' => 'Wird veröffentlicht…',

    'draft_saved' => 'Dienstplan als Entwurf gespeichert.',
    'published' => 'Dienstplan veröffentlicht. :name wurde per E-Mail informiert.',
    'republished' => 'Dienstplan aktualisiert und veröffentlicht. :name wurde informiert.',
    'published_without_email' => 'Dienstplan veröffentlicht. Für :name ist keine E-Mail-Adresse hinterlegt, es wurde also nichts gesendet.',
    'published_email_failed' => 'Dienstplan veröffentlicht, aber die E-Mail an :name konnte nicht gesendet werden. Der Fehler wurde protokolliert.',
    'nothing_to_save' => 'In diesem Zeitraum gibt es nichts Geplantes zu speichern.',
    'nothing_to_publish' => 'In diesem Zeitraum gibt es nichts Geplantes zu veröffentlichen.',

    'confirm' => [
        'title' => 'Dienstplan veröffentlichen?',
        'intro' => 'Prüfen Sie den Dienstplan vor dem Veröffentlichen. Danach wird die Person per E-Mail informiert.',
        'staff_member' => 'Person',
        'period' => 'Zeitraum des Dienstplans',
        'duration' => 'Dauer',
        'working_days' => 'Arbeitstage',
        'total_hours' => 'Geplante Stunden gesamt',
        'weeks' => '{1} 1 Woche|[2,*] :count Wochen',
        'hours' => '{1} :count Stunde|[2,*] :count Stunden',
        'days' => ':count',
        'days_long' => '{1} 1 Tag|[2,*] :count Tage',
        'will_notify' => 'Der Dienstplan wird veröffentlicht und die Person per E-Mail informiert.',
        'consequence' => 'Nach dem Veröffentlichen ist dies der offizielle Arbeitsplan der Person, und sie wird per E-Mail informiert.',
    ],

    'confirm_draft' => [
        'title' => 'Dienstplan als Entwurf speichern?',
        'intro' => 'Der Dienstplan wird gespeichert, aber erst beim Veröffentlichen an die Person gesendet.',
    ],

    'day_total' => '{1} :count Stunde|[2,*] :count Stunden',

    'email' => [
        'subject' => 'Ihr Arbeitsplan wurde veröffentlicht',
        'subject_updated' => 'Ihr Arbeitsplan wurde aktualisiert',
        'headline_updated' => 'Ihr Arbeitsplan wurde aktualisiert',
        'intro_updated' => 'Dies ersetzt den Plan, den Sie zuvor bekommen haben. Hier ist er vollständig.',
        'preheader' => 'Ihr Plan für :from – :until.',
        'headline' => 'Ihr Arbeitsplan wurde veröffentlicht',
        'greeting' => 'Hallo :name,',
        'intro' => ':business hat Ihren Arbeitsplan veröffentlicht. Hier ist er vollständig.',
        'period' => 'Zeitraum',
        'location' => 'Standort',
        'working_days' => 'Arbeitstage',
        'total_hours' => 'Geplante Stunden gesamt',
        'published_on' => 'Veröffentlicht',
        'published_by' => 'Veröffentlicht von',
        'hours' => '{1} :count Stunde|[2,*] :count Stunden',
        'daily_schedule' => 'Tagesplan',
        'not_working' => 'Arbeitet nicht',
        'day_total' => '{1} Gesamt: :count Stunde|[2,*] Gesamt: :count Stunden',
        'cta' => 'Meinen Plan ansehen',
        'questions' => 'Falls hier etwas nicht stimmt, sprechen Sie mit Ihrer Leitung bei :business.',
    ],

    'validation' => [
        'staff_not_active' => ':name ist nicht aktiv, es kann also kein Dienstplan zugewiesen werden.',
        'ends_after_starts' => 'Das Ende muss nach dem Beginn liegen.',
        'periods_overlap' => 'Die Arbeitszeiträume dieses Tages überschneiden sich.',
        'split_not_allowed' => 'Die zugewiesene Schichtregel erlaubt keine geteilten Dienste, dieser Tag darf also nur einen Zeitraum haben.',
        'one_period_only' => 'Die zugewiesene Schichtregel erlaubt derselben Person nicht mehr als einen Zeitraum pro Tag.',
        'too_many_periods' => 'Die zugewiesene Schichtregel erlaubt höchstens :count Zeiträume pro Tag.',
        'gap_too_short' => 'Die zugewiesene Schichtregel verlangt :hours Stunden zwischen geteilten Diensten.',
        'business_closed' => 'Der Betrieb hat an diesem Tag geschlossen.',
        'outside_business_hours' => 'Außerhalb der Arbeitszeiten des Betriebs (:hours).',
        'over_daily_hours' => 'Mehr als die :count Stunden pro Tag, die die Schichtregel erlaubt.',
        'over_weekly_hours' => 'Mehr als die :count Stunden pro Woche, die die Schichtregel erlaubt.',
        'not_enough_rest' => 'Weniger als die :count Stunden Ruhe, die die Schichtregel seit der vorigen Schicht verlangt.',
        'too_many_consecutive' => 'Mehr als :count aufeinanderfolgende Arbeitstage, was die Schichtregel nicht erlaubt.',
        'already_working' => 'Arbeitet an diesem Tag bereits :hours.',
    ],
];
