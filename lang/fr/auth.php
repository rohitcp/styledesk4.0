<?php

declare(strict_types=1);

/*
| Ce que dit le formulaire de connexion quand il refuse.
|
| Remplace le fichier du framework pour que les mots soient ceux de StyleDesk.
| 'failed' répond volontairement la même chose pour un mot de passe erroné et
| pour une adresse sans compte : les distinguer, c'est dire à un inconnu
| quelles adresses ont un compte ici.
*/

return [
    'failed' => 'E-mail ou mot de passe incorrect. Réessayez.',

    /* Quand la tentative a échoué au lieu d'être refusée — voir
       App\Http\Middleware\ReportSignInFailures. Ne dit volontairement rien de
       ce qui a cassé : le lecteur ne peut rien en faire, le journal si. */
    'unavailable' => 'Nous n’avons pas pu vous connecter pour le moment. Réessayez.',

    'unverified' => 'Votre adresse e-mail n’a pas été vérifiée. Vérifiez-la pour continuer.',
    'password' => 'Ce mot de passe est incorrect.',
    'throttle' => 'Trop de tentatives de connexion. Réessayez dans :seconds secondes.',

    /* Dit clairement. Quelqu'un dont l'entreprise a été désactivée doit
       l'apprendre par l'écran plutôt que par un mot de passe qui aurait
       mystérieusement cessé de fonctionner. */
    'business_disabled' => 'Votre compte StyleDesk est actuellement désactivé. Contactez l’assistance.',
];
