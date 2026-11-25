<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Legacy authentication table
    |--------------------------------------------------------------------------
    |
    | Mais XML uses the legacy usuários table (singular "usuario" in MySQL).
    | Override if your dump uses another name (e.g. usuarios).
    |
    */

    'usuario_table' => env('LEGACY_USUARIO_TABLE', 'usuario'),

];
