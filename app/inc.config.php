<?php

return [
	'server' => [
		'url' => 'http://localhost/kloset/',
		'host' => $_SERVER['DOCUMENT_ROOT'] . '/kloset/',
	],
	'db' => [
		'host' => 'localhost',
		'name' => 'kloset_bd',
		'user' => 'root',
		'password' => '',
		'port' => '3307',
	],
	'plantilla' => [
		'front' => 'kloset',
		'back' => 'kloset',
	],
	'admin' => [
		'servidor_correo_url' => 'localhost',
		'servidor_correo_usuario' => '',
		'servidor_correo_contrasena' => '',
	],
	'jwt' => [
		'secret' => 'kloset-dev-secret-cambiar-en-produccion',
		'ttl' => 86400,
	],
];
