<?php

return [
    // Emails opérateurs (voient tout le parc dans le dashboard).
    // Définir sur le VPS : JOOW_ADMIN_EMAILS="ops@exemple.fr,autre@exemple.fr"
    // Vide => chaque client ne voit que ses propres sites.
    'admins' => array_values(array_filter(array_map(
        fn ($e) => strtolower(trim($e)),
        explode(',', (string) env('JOOW_ADMIN_EMAILS', ''))
    ))),
];
