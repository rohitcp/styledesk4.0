<?php

declare(strict_types=1);

/*
| Attribuer un planning de travail à un membre de l'équipe.
|
| Le vocabulaire distingue trois choses, faciles à confondre et dont la copie
| est le seul endroit où la distinction se fait vraiment : les horaires
| d'ouverture disent quand l'entreprise est ouverte, une règle de service est le
| motif que suit quelqu'un, et le planning ci-dessous est la réalité datée que
| ce motif a produite.
*/

return [

    'year' => 'Année',
    'monthly_summary' => 'Récapitulatif mensuel',
    'month_hours' => 'Heures',
    'month_shifts' => 'Services',
    'month_days' => 'Jours travaillés',
    'bookings_pending' => 'Le décompte des réservations arrivera avec le module de réservation.',

    'working_schedule' => 'Planning de travail',
    'assign' => 'Attribuer un planning',
    'assign_title' => 'Attribuer un planning',
    'assign_intro' => 'Attribuez un planning de travail au membre et à la période sélectionnés.',
    'assign_for' => 'Membre de l’équipe',
    'period' => 'Période du planning',
    'assigning' => 'Attribution…',

    'add_shift' => 'Ajouter un service',
    'add_for_day' => 'Ajouter au planning',
    'delete_day' => 'Supprimer le planning',
    'delete_day_title' => 'Supprimer le planning ?',
    'delete_day_confirm' => 'Cela retirera le planning de :name pour le :date.',
    'day_deleted' => 'Planning du :date supprimé.',
    'mark_on_leave' => 'Marquer en congé — bientôt',
    'showing' => 'Affichage de :from à :to sur :total jours',
    'actions_for' => 'Actions pour :name',

    'filters' => [
        'period' => 'Période',
        'month' => 'Mois',
        'year' => 'Année',
        'apply' => 'Appliquer',
        'edit_period' => 'Changer de mois',
        'reset' => 'Réinitialiser',
    ],

    'periods' => [
        'month' => 'Un mois',
        '3-months' => '3 mois',
        '6-months' => '6 mois',
        '9-months' => '9 mois',
        'year' => 'Un an',
    ],

    'columns' => [
        'date' => 'Date',
        'day' => 'Jour',
        'working' => 'Statut de travail',
        'time' => 'Heure',
        'status' => 'Statut',
        'published_on' => 'Publié le',
        'published_by' => 'Publié par',
        'hours' => 'Heures totales',
        'bookings' => 'Réservations',
    ],

    'empty' => 'Aucun planning trouvé',
    'empty_hint' => 'Il n’y a aucun planning pour la période sélectionnée.',

    'duration_intro' => 'Le mois entier se planifie d’un bloc. Vous pourrez modifier n’importe quel jour une fois le planning ouvert.',
    'continue_label' => 'Continuer',
    'back' => 'Retour',
    'close' => 'Fermer',
    'save' => 'Enregistrer',
    'save_and_publish' => 'Enregistrer et publier',
    'update_and_publish' => 'Mettre à jour et publier',
    'rule_change_title' => 'Reconstruire les jours à partir de cette règle ?',
    'rule_change_confirm' => 'Les jours ci-dessous seront remplis à nouveau depuis la règle choisie, et les modifications faites ici seront perdues.',
    'rule_change_label' => 'Reconstruire les jours',
    'leave_title' => 'Quitter sans enregistrer ?',
    'leave_confirm' => 'Ce planning contient des modifications non enregistrées. Partir maintenant les abandonne.',
    'leave_confirm_label' => 'Abandonner les modifications',
    'needs_javascript' => 'Construire un planning nécessite JavaScript, désactivé dans ce navigateur.',

    'summary' => '{0} Rien de planifié|{1} :hours heures planifiées · :count jour travaillé|[2,*] :hours heures planifiées · :count jours travaillés',
    'hours_short' => ':count h',

    'working' => 'Travaille',
    'off' => 'Repos',
    'not_working' => 'Ne travaille pas',
    'week_number' => 'Semaine :number',
    'add_period' => '+ Ajouter une plage de travail',
    'remove_period' => 'Retirer cette plage',
    'starts_at' => 'Début',
    'ends_at' => 'Fin',
    'break' => 'Pause',
    'no_break' => 'Aucune pause',

    'assigned' => '{1} :count service attribué.|[2,*] :count services attribués.',
    'nothing_to_assign' => 'Aucun jour travaillé n’a été choisi, rien n’a donc été attribué.',
    'replaced_note' => 'L’attribution remplace les services déjà présents sur cette période.',

    'shift_rule' => 'Règle de service',
    'no_shift_rule' => 'Aucune règle de service',
    'change_rule' => 'Changer',
    'prefill_hint' => 'Choisir une règle remplit les jours ci-dessous à partir des horaires d’ouverture et des plages propres à la règle. Modifier un jour ici ne change que ce planning, jamais la règle.',

    'refused' => '{1} Le planning n’a pas été enregistré : un jour enfreint la règle de service.|[2,*] Le planning n’a pas été enregistré : :count jours enfreignent la règle de service.',
    'refused_title' => 'Ce planning n’a pas été enregistré.',

    'publish_statuses' => [
        'draft' => 'Brouillon',
        'published' => 'Publié',
    ],

    /*
    | La publication.
    |
    | Attribuer un planning et en informer quelqu'un sont deux actes distincts,
    | et la copie est l'endroit où cette séparation se fait : un brouillon,
    | c'est le responsable qui réfléchit encore ; publié, c'est la semaine de
    | travail du membre.
    */
    'draft_badge' => 'Planning brouillon',
    'published_badge' => 'Publié',
    'published_notice_title' => 'Ce planning a déjà été publié.',
    'published_notice' => 'Toute modification faite ici met à jour le planning publié de :name. À la prochaine publication, un e-mail lui dira que son planning a changé. Enregistrer en brouillon ne lui dit rien.',
    'locked' => 'Publié — le membre a été informé de cette journée.',
    'published_on' => 'Publié le :date',
    'published_by' => 'par :name',
    'changes_badge' => 'Modifications non publiées',
    'changes_hint' => 'Ce planning a été publié le :date et modifié depuis. :name n’a pas été informé des changements.',
    'draft_hint' => 'Rien n’a encore été envoyé. :name ne recevra un e-mail qu’à la publication.',
    'published_hint' => ':name a reçu ce planning par e-mail.',

    'save_draft' => 'Enregistrer le brouillon',
    'publish' => 'Publier le planning',
    'publish_changes' => 'Publier les modifications',
    'publishing' => 'Publication…',

    'draft_saved' => 'Planning enregistré comme brouillon.',
    'published' => 'Planning publié. :name a été prévenu par e-mail.',
    'republished' => 'Planning mis à jour et publié. :name a été prévenu.',
    'published_without_email' => 'Planning publié. :name n’a pas d’adresse e-mail au dossier, aucune notification n’a donc été envoyée.',
    'published_email_failed' => 'Planning publié, mais l’e-mail à :name n’a pas pu être envoyé. L’échec a été consigné.',
    'nothing_to_save' => 'Il n’y a rien de planifié à enregistrer sur cette période.',
    'nothing_to_publish' => 'Il n’y a rien de planifié à publier sur cette période.',

    'confirm' => [
        'title' => 'Publier le planning ?',
        'intro' => 'Vérifiez le planning avant de publier. Une fois publié, le membre sera prévenu par e-mail.',
        'staff_member' => 'Membre de l’équipe',
        'period' => 'Période du planning',
        'duration' => 'Durée',
        'working_days' => 'Jours travaillés',
        'total_hours' => 'Total des heures planifiées',
        'weeks' => '{1} 1 semaine|[2,*] :count semaines',
        'hours' => '{1} :count heure|[2,*] :count heures',
        'days' => ':count',
        'days_long' => '{1} 1 jour|[2,*] :count jours',
        'will_notify' => 'Le planning sera publié et le membre prévenu par e-mail.',
        'consequence' => 'Une fois publié, ce planning devient le planning officiel du membre, qui en sera informé par e-mail.',
    ],

    'confirm_draft' => [
        'title' => 'Enregistrer le planning en brouillon ?',
        'intro' => 'Le planning sera enregistré mais ne sera pas envoyé au membre tant qu’il n’est pas publié.',
    ],

    'day_total' => '{1} :count heure|[2,*] :count heures',

    'email' => [
        'subject' => 'Votre planning de travail a été publié',
        'subject_updated' => 'Votre planning de travail a été mis à jour',
        'headline_updated' => 'Votre planning de travail a été mis à jour',
        'intro_updated' => 'Ceci remplace le planning qui vous avait été envoyé. Le voici en entier.',
        'preheader' => 'Votre planning du :from au :until.',
        'headline' => 'Votre planning de travail a été publié',
        'greeting' => 'Bonjour :name,',
        'intro' => ':business a publié votre planning de travail. Le voici en entier.',
        'period' => 'Période du planning',
        'location' => 'Établissement',
        'working_days' => 'Jours travaillés',
        'total_hours' => 'Total des heures planifiées',
        'published_on' => 'Publié le',
        'published_by' => 'Publié par',
        'hours' => '{1} :count heure|[2,*] :count heures',
        'daily_schedule' => 'Planning journalier',
        'not_working' => 'Ne travaille pas',
        'day_total' => '{1} Total : :count heure|[2,*] Total : :count heures',
        'cta' => 'Voir mon planning',
        'questions' => 'Si quelque chose vous semble incorrect, parlez-en à votre responsable chez :business.',
    ],

    'validation' => [
        'staff_not_active' => ':name n’est pas actif, aucun planning ne peut donc lui être attribué.',
        'ends_after_starts' => 'L’heure de fin doit être postérieure à l’heure de début.',
        'periods_overlap' => 'Les plages de travail de cette journée se chevauchent.',
        'split_not_allowed' => 'La règle de service attribuée n’autorise pas les journées coupées : cette journée ne peut avoir qu’une plage.',
        'one_period_only' => 'La règle de service attribuée n’autorise pas plus d’une plage par jour pour la même personne.',
        'too_many_periods' => 'La règle de service attribuée autorise au plus :count plages par jour.',
        'gap_too_short' => 'La règle de service attribuée exige :hours heures entre deux plages.',
        'business_closed' => 'L’entreprise est fermée ce jour-là.',
        'outside_business_hours' => 'En dehors des heures de travail de l’entreprise (:hours).',
        'over_daily_hours' => 'Plus que les :count heures par jour autorisées par la règle de service.',
        'over_weekly_hours' => 'Plus que les :count heures par semaine autorisées par la règle de service.',
        'not_enough_rest' => 'Moins que les :count heures de repos exigées depuis le service précédent.',
        'too_many_consecutive' => 'Plus de :count jours travaillés consécutifs, ce que la règle de service n’autorise pas.',
        'already_working' => 'Travaille déjà :hours ce jour-là.',
    ],
];
