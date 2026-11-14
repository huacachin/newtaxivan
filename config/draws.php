<?php

/**
 * Sorteos. Al cambiar DRAWS_SUNDAYS en el .env de producción hay que correr
 * `php artisan config:cache`.
 */
return [
    /*
     * Sorteo opcional de los domingos (DrawService::SUNDAYS_*). La grilla muestra
     * siempre todos los domingos del mes (DrawService::calendarDays):
     *  - off:  no se sortean domingos (el calendario los muestra igual)
     *  - test: se ve el próximo domingo para probarlo hasta la confirmación,
     *          pero el sorteo se rechaza y no se guarda nada
     *  - on:   se sortea normal (el sábado ya se ve el domingo)
     */
    'sundays' => env('DRAWS_SUNDAYS', 'off'),
];
