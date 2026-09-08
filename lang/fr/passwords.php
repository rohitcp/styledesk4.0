<?php

declare(strict_types=1);

/*
| Ce que dit le service de réinitialisation de mot de passe.
|
| Remplace le fichier du framework pour que les mots soient ceux de StyleDesk.
| Seule la copie change — quelle ligne est choisie, et quand, reste la décision
| du service.
*/

return [
    'reset' => 'Votre mot de passe a été réinitialisé. Vous pouvez vous connecter avec.',

    /*
    | Affiché que l'adresse corresponde ou non à un compte — voir la note sur
    | 'user' ci-dessous — donc il ne doit pas promettre qu'un e-mail part vers
    | cette personne en particulier. « Si nous avons un compte » fait ce
    | travail.
    */
    'sent' => 'Si nous avons un compte pour cette adresse, nous y avons envoyé un lien de réinitialisation. Suivez les instructions de cet e-mail pour définir un nouveau mot de passe.',

    'throttled' => 'Vous avez demandé un lien récemment. Patientez un instant avant d’en demander un autre.',

    'token' => 'Ce lien de réinitialisation est invalide ou a déjà été utilisé. Demandez-en un nouveau ci-dessous.',

    /*
    | Jamais atteint : FortifyServiceProvider répond à une adresse inconnue par
    | la ligne 'sent', parce qu'un formulaire qui dit « nous ne trouvons pas cet
    | utilisateur » est une façon de demander quelles adresses ont un compte.
    | Conservé parce que le contrat du service attend cette clé.
    */
    'user' => 'Si nous avons un compte pour cette adresse, nous y avons envoyé un lien de réinitialisation.',
];
