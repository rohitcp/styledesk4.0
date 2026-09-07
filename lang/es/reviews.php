<?php

declare(strict_types=1);

/*
| Reseñas y comentarios de clientes.
|
| Dos públicos en un archivo, separados por sus claves principales: todo lo que
| está bajo `page` y `email` lo lee un cliente que nunca ha oído hablar de
| StyleDesk, y el resto lo lee el negocio. El texto para el cliente es breve a
| propósito: cada frase entre el enlace y la estrella cuesta una reseña.
*/

return [

    'title' => 'Reseñas y comentarios',

    'stars' => '{1} 1 estrella|[2,*] :count estrellas',

    'rating_labels' => [
        1 => 'Muy malo',
        2 => 'Malo',
        3 => 'Regular',
        4 => 'Bueno',
        5 => 'Excelente',
    ],

    'statuses' => [
        'new' => 'Nuevo',
        'reviewing' => 'En revisión',
        'contacted' => 'Cliente contactado',
        'resolved' => 'Resuelto',
        'closed' => 'Cerrado',
        'no_action' => 'Sin acción necesaria',
    ],

    'request_statuses' => [
        'not_requested' => 'No solicitada',
        'scheduled' => 'Programada',
        'sent' => 'Enviada',
        'completed' => 'Completada',
    ],

    'page' => [
        'title' => '¿Qué tal su visita?',
        'question' => '¿Cómo fue su experiencia?',
        'question_hint' => 'Toque una estrella. Con eso basta.',

        'comment_label' => 'Cuéntenos sobre su experiencia',
        'comment_optional' => 'Opcional',
        'comment_placeholder' => 'Cuéntenos qué le gustó o qué podríamos mejorar.',

        'recommend_label' => '¿Nos recomendaría a un amigo?',
        'recommend' => [
            'yes' => 'Sí',
            'maybe' => 'Quizá',
            'no' => 'No',
        ],

        'sorry_title' => 'Lamentamos que su experiencia no haya estado a la altura.',
        'sorry_hint' => 'Cuéntenos cómo podemos mejorar.',
        'contact_label' => 'Me gustaría que alguien del negocio me contacte.',

        'submit' => 'Enviar',
        'submit_negative' => 'Enviar comentarios',
        'choose_rating' => 'Elija primero una valoración.',

        'thanks_title' => '¡Gracias por sus comentarios!',
        'thanks_positive' => 'Nos alegra que haya disfrutado su visita. ¿Le gustaría compartir su experiencia con otras personas?',
        'thanks_negative' => 'Gracias por decírnoslo. Lo usaremos para poner las cosas en su sitio.',
        'thanks_contact' => 'Alguien del negocio se pondrá en contacto con usted.',
        'google_cta' => 'Escribir una reseña en Google',
        'done' => 'Listo',

        'already' => 'Gracias. Sus comentarios ya fueron enviados.',
    ],

    'email' => [
        'subject' => '¿Qué tal su visita a :business?',
        'preview' => 'Basta un toque.',
        'headline' => '¿Qué tal su visita?',
        'intro' => 'Hola :name: nos encantaría saber cómo fue su experiencia en :business.',
        'rate' => 'Valore su experiencia',
        'cta' => 'Dejar comentarios',
    ],

    'settings' => [
        'title' => 'Reseñas y comentarios',
        'intro' => 'Pregunte a sus clientes qué tal fue su visita tras una cita completada, detecte a los insatisfechos antes de que lo hagan público y dirija a los satisfechos a su ficha.',

        'enable' => 'Activar reseñas de clientes',
        'enable_hint' => 'Cuando está desactivado no se envía nada. Las reseñas ya recibidas siguen en la ficha del cliente y en sus informes.',
        'disabled_note' => 'Actívelo para elegir cuándo se pregunta a los clientes, cómo se les pregunta y adónde se dirige a los satisfechos.',

        'timing' => 'Cuándo preguntar',
        'timing_hint' => 'Se cuenta desde que la cita se marca como completada. Una hora suele funcionar: el cliente ya se ha ido y todavía lo recuerda.',
        'delays' => [
            'immediate' => 'Inmediatamente',
            '1h' => '1 hora después de completarse',
            '3h' => '3 horas después de completarse',
            '6h' => '6 horas después de completarse',
            'next_day' => 'Al día siguiente',
        ],

        'channel' => 'Cómo preguntar',
        'channel_hint' => 'A un cliente sin dirección registrada no se le pregunta. Los mensajes de texto aún no están disponibles.',
        'channels' => [
            'email' => 'Correo electrónico',
            'sms' => 'SMS',
            'both' => 'SMS + correo electrónico',
        ],
        'coming_soon' => 'Próximamente',

        'google' => 'Reseñas de Google',
        'google_enable' => 'Ofrecer una reseña de Google a los clientes satisfechos',
        'google_hint' => 'Solo se muestra a quienes dejan 4 o 5 estrellas. A quienes dejan menos se les pregunta cómo mejorar y nunca se les envía a una ficha pública.',
        'google_urls' => 'Enlaces de reseña por sede',
        'google_urls_hint' => 'Cada sede tiene su propia ficha de Google. Una sede sin enlace simplemente no ofrece el botón.',
        'google_url_placeholder' => 'https://g.page/r/…',
        'no_locations' => 'Añada una sede antes de configurar los enlaces de reseña de Google.',

        'saved' => 'Ajustes de reseñas guardados.',
    ],

];
