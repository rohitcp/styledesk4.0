<?php

declare(strict_types=1);

/*
| Le module Devise.
|
| Les *noms* des devises ne sont pas ici : ils vivent dans
| config/currencies.php aux côtés du symbole et des règles de format, parce que
| le nom d'une devise fait partie de sa définition plutôt que de la copie de
| l'interface. « US Dollar » est le nom de cette devise ; le traduire par
| langue ferait diverger le code et le nom sur l'argent dont il s'agit.
*/

return [
    'title' => 'Devise',
    'intro' => 'La devise dans laquelle vos prix sont fixés, et les devises supplémentaires dans lesquelles vous fixez aussi vos prix.',
    'edit' => 'Modifier les devises',
    'saved' => 'Réglages de devise mis à jour.',

    'primary' => 'Devise principale',

    'is_primary' => 'principale',
    'primary_hint' => 'La devise par défaut des prestations, produits, forfaits, abonnements, acomptes, frais, remises, taxes, paiements, remboursements et rapports.',
    'secondary' => 'Devises supplémentaires',
    'secondary_hint' => 'Les devises dans lesquelles vous fixez aussi vos prix. La principale est toujours disponible et n’est pas listée ici.',
    'enabled' => 'Devises activées',
    'format' => 'Comment les prix s’affichent',
    'format_hint' => 'Défini par la devise, pas par vous — le symbole, sa position, les séparateurs et le nombre de décimales.',

    'single_currency' => 'Vous fixez vos prix dans une seule devise. Ajoutez-en une autre et les champs de prix en demanderont un pour chacune.',

    'no_conversion' => 'Les prix ne sont pas convertis d’une devise à l’autre. Vous fixez vous-même le prix dans chacune, si bien qu’un taux qui bouge dans la nuit ne change jamais ce qui a été annoncé à un client.',
    'scope_note' => 'Changer votre devise principale ne re-tarife rien. Les prix existants conservent la devise dans laquelle ils ont été saisis.',

    'validation' => [
        'primary_required' => 'Choisissez une devise principale.',
        'unsupported' => 'Cette devise n’est pas disponible.',
    ],
];
