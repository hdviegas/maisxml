<?php

// Front controller da raiz do projeto, usado quando o Laravel inteiro vive em uma
// subpasta (ex.: public_html/admin) e o document root não pode ser alterado.
// O .htaccess reescreve para ESTE arquivo (e não para public/index.php) para que
// SCRIPT_NAME seja /admin/index.php e o Symfony calcule baseUrl=/admin; apontar
// direto para public/index.php deixaria o baseUrl vazio e as rotas dariam 404.
require __DIR__.'/public/index.php';
