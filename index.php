<?php

include_once './app/utilities/vendor/autoload.php';
include_once 'inc.core.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Kloset</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 640px; margin: 48px auto; padding: 0 16px; line-height: 1.6; }
        a { color: #C4211F; }
        code { background: #f2f2f0; padding: 2px 6px; }
    </style>
</head>
<body>
    <h1>Kloset</h1>
    <p>Backend PHP activo. Enlaces:</p>
    <ul>
        <li><a href="<?= htmlspecialchars(DW_PANEL) ?>">Panel administrativo</a></li>
        <li><a href="<?= htmlspecialchars(URL_WEB) ?>api/v1/health">API health</a></li>
        <li>Frontend React (dev): <code>npm run dev</code> en <code>frontend/</code></li>
    </ul>
</body>
</html>
