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
		'servidor_correo_url' => 'localhost',
		'servidor_correo_usuario' => '',
		'servidor_correo_contrasena' => '',
	],
	'jwt' => [
		'secret' => 'cambiar-en-produccion',
		'ttl' => 86400,
	],
];
