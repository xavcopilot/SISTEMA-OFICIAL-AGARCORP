<?php

return [
    // Activa o desactiva el guardado automatico de toasts en la tabla notifications.
    'enabled' => true,

    // Coincidencia exacta por titulo (insensible a mayusculas/minusculas).
    'exclude_titles' => [
        // 'Historial limpiado',
    ],

    // Excluir por estado Filament (success, warning, danger, info).
    'exclude_statuses' => [
        // 'info',
    ],

    // Excluir si titulo o body contiene alguno de estos textos.
    'exclude_contains' => [
        // 'temporario',
    ],
];
