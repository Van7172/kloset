<?php

declare(strict_types=1);

// En XAMPP la aplicación vive en /kloset; en producción dist/index.html
// se despliega en la raíz. Este puente sirve la compilación local sin
// cambiar las rutas públicas de la API ni del panel administrativo.
$archivo = __DIR__ . '/frontend/dist/index.html';
if (!is_file($archivo)) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><title>Kloset</title>'
        . '<body style="font-family:system-ui;padding:3rem"><h1>Kloset</h1>'
        . '<p>Compila la tienda con <code>cd frontend &amp;&amp; npm run build:local</code>.</p>'
        . '<a href="dw-panel/">Ir al panel administrativo</a></body></html>';
    exit;
}

$config = require __DIR__ . '/app/inc.config.php';
$path = parse_url($config['server']['url'], PHP_URL_PATH) ?: '/';
$root = rtrim($path, '/');
$assets = $root . '/frontend/dist';
$html = file_get_contents($archivo);
if ($html === false) {
    http_response_code(500);
    exit;
}

$html = str_replace(
    ['"/assets/', '"/favicon.svg', '"/kloset-icon.png', '"/site.webmanifest'],
    ['"' . $assets . '/assets/', '"' . $assets . '/favicon.svg', '"' . $assets . '/kloset-icon.png', '"' . $assets . '/site.webmanifest'],
    $html
);
$bootstrap = '<script>window.__KLOSET_ROOT__='
    . json_encode($root, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
    . ';window.__KLOSET_ASSETS__='
    . json_encode($assets, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
    . ';</script>';
$html = str_replace('</head>', $bootstrap . '</head>', $html);
header('Content-Type: text/html; charset=utf-8');
echo $html;
