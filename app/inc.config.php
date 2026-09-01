<?php

/**
 * La URL pública se deduce de la petición para que el proyecto funcione igual
 * en XAMPP (http://localhost/kloset/) y en el dominio (https://kloset.shop/),
 * sin tocar este archivo al desplegar.
 *
 * Para forzarla —CLI, cron o un proxy raro— define la variable de entorno
 * KLOSET_URL con la barra final, por ejemplo:
 *   SetEnv KLOSET_URL https://kloset.shop/
 */
$raizProyecto = str_replace('\\', '/', dirname(__DIR__)) . '/';

$urlPublica = getenv('KLOSET_URL') ?: null;

if ($urlPublica === null) {
	$esHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
		|| (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
		|| ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443);
	$dominio = $_SERVER['HTTP_HOST'] ?? 'localhost';

	// Subcarpeta en la que vive el proyecto respecto del document root
	$docRoot = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
	$subcarpeta = '';
	if ($docRoot !== '' && str_starts_with($raizProyecto, $docRoot)) {
		$subcarpeta = trim(substr($raizProyecto, strlen($docRoot)), '/');
	}

	$urlPublica = ($esHttps ? 'https' : 'http') . '://' . $dominio . '/'
		. ($subcarpeta !== '' ? $subcarpeta . '/' : '');
}

return [
	'server' => [
		'url' => $urlPublica,
		'host' => $raizProyecto,
	],
	'db' => [
		'host' => getenv('KLOSET_DB_HOST') ?: 'localhost',
		'name' => getenv('KLOSET_DB_NAME') ?: 'kloset_bd',
		'user' => getenv('KLOSET_DB_USER') ?: 'root',
		'password' => getenv('KLOSET_DB_PASS') ?: '',
		'port' => getenv('KLOSET_DB_PORT') ?: '3307',
	],
	'plantilla' => [
		'front' => 'kloset',
		'back' => 'kloset',
	],
	'admin' => [
		'servidor_correo_url' => getenv('KLOSET_SMTP_HOST') ?: 'localhost',
		'servidor_correo_usuario' => getenv('KLOSET_SMTP_USER') ?: '',
		'servidor_correo_contrasena' => getenv('KLOSET_SMTP_PASS') ?: '',
	],
	'jwt' => [
		// En producción define KLOSET_JWT_SECRET; el valor de abajo es solo para XAMPP.
		'secret' => getenv('KLOSET_JWT_SECRET') ?: 'kloset-dev-secret-cambiar-en-produccion',
		'ttl' => 86400,
	],
];
