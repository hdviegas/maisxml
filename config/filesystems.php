<?php

// Raiz do disco "local". FILESYSTEM_LOCAL_ROOT aceita caminho absoluto ou relativo ao
// projeto (ex.: ".." = pasta pai). Vazio/ausente = storage/app/private. O valor é
// calculado aqui (string pura), então funciona com `php artisan config:cache`.
$localRoot = (static function (): string {
    $raw = trim((string) env('FILESYSTEM_LOCAL_ROOT', ''));
    if ($raw === '') {
        return storage_path('app/private');
    }

    $raw = str_replace('\\', '/', $raw);
    $absolute = str_starts_with($raw, '/') || preg_match('#^[A-Za-z]:/#', $raw) === 1;
    $path = $absolute ? $raw : str_replace('\\', '/', base_path()).'/'.$raw;

    $real = realpath($path);
    if ($real !== false) {
        return rtrim(str_replace('\\', '/', $real), '/') ?: '/';
    }

    $prefix = str_starts_with($path, '/') ? '/' : '';
    $parts = [];
    foreach (explode('/', $path) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            if ($parts !== [] && end($parts) !== '..' && ! preg_match('#^[A-Za-z]:$#', end($parts))) {
                array_pop($parts);
            }

            continue;
        }
        $parts[] = $segment;
    }

    return $prefix.implode('/', $parts);
})();

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => $localRoot,
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
