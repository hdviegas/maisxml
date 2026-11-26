<?php

use Illuminate\Http\Request;

// Simula a hospedagem em public_html/admin: o .htaccess reescreve para o index.php da RAIZ
// do projeto, então SCRIPT_NAME=/admin/index.php e o Symfony deriva baseUrl=/admin.
// (Reescrever para public/index.php deixaria o baseUrl vazio e as rotas dariam 404.)

$subdir = fn () => [
    'SCRIPT_NAME' => '/admin/index.php',
    'SCRIPT_FILENAME' => base_path('index.php'),
    'PHP_SELF' => '/admin/index.php',
];

test('app served from /admin resolves routes and generates /admin URLs', function () use ($subdir) {
    $this->call('GET', '/admin/login', [], [], [], $subdir())->assertOk();

    expect(url('/'))->toEndWith('/admin');
    expect(route('login'))->toEndWith('/admin/login');
    expect(asset('build/x.js'))->toEndWith('/admin/build/x.js');
});

test('root of the subdirectory redirects guests to /admin/login', function () use ($subdir) {
    // Request direto: o helper call() remove a barra final, e o Apache envia /admin/.
    $request = Request::create('/admin/', 'GET', [], [], [], $subdir());
    $response = $this->app->handle($request);

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toEndWith('/admin/login');
});

test('standard docroot=public (artisan serve) still serves /login', function () {
    $this->get('/login')->assertOk();
});

test('root index.php only delegates to public/index.php', function () {
    expect(file_get_contents(base_path('index.php')))->toContain("require __DIR__.'/public/index.php';");
});
