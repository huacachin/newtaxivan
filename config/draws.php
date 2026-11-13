<?php

/**
 * Sorteos. Al cambiar DRAWS_SUNDAYS en el .env de producción hay que correr
 * `php artisan config:cache`.
 */
return [
    /*
     * Sorteo opcional de los domingos (DrawService::SUNDAYS_*):
     *  - off:  no se ven ni se sortean domingos
     *  - test: se ve el próximo domingo para probarlo hasta la confirmación,
     *          pero el sorteo se rechaza y no se guarda nada
     *  - on:   se sortea normal (el sábado ya se ve el domingo)
     */
    'sundays' => env('DRAWS_SUNDAYS', 'off'),
];
