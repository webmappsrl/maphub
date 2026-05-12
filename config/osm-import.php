<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Ritardo tra una richiesta HTTP all’API OSM e la successiva (import POI)
    |--------------------------------------------------------------------------
    |
    | Riduce burst verso api.openstreetmap.org. Valore in millisecondi.
    | Imposta 0 per disattivare (solo ambienti di test o volumi molto bassi).
    |
    */
    'request_delay_ms' => max(0, (int) env('OSM_IMPORT_REQUEST_DELAY_MS', 350)),

    /*
    |--------------------------------------------------------------------------
    | Numero massimo di OSMID processati per singola esecuzione dell’import
    |--------------------------------------------------------------------------
    |
    | Se la lista supera questo valore, gli ID eccedenti vengono ignorati e il
    | report indica quanti ne sono stati omessi. 0 = nessun limite.
    |
    */
    'max_ids_per_run' => max(0, (int) env('OSM_IMPORT_MAX_IDS_PER_RUN', 500)),

];
