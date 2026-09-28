<?php

return [
	'server' => [
		'url' => 'http://localhost/kloset/',
		'host' => $_SERVER['DOCUMENT_ROOT'] . '/kloset/',
	],
	'db' => [
		'host' => 'localhost',
		'name' => 'kloset',
		'user' => 'root',
		'password' => '',
		'port' => '3306',
	],
	'plantilla' => [
		'front' => 'kloset',
		'back' => 'kloset',
	],
	'admin' => [
		'servidor_correo_url' => getenv('KLOSET_SMTP_HOST') ?: 'localhost',
		'servidor_correo_usuario' => getenv('KLOSET_SMTP_USER') ?: '',
		'servidor_correo_contrasena' => getenv('KLOSET_SMTP_PASS') ?: '',
		'servidor_correo_puerto' => getenv('KLOSET_SMTP_PORT') ?: '587',
		'servidor_correo_seguridad' => getenv('KLOSET_SMTP_SECURITY') ?: 'tls',
	],
	'jwt' => [
		'secret' => 'cambiar-en-produccion',
		'ttl' => 86400,
	],
];
