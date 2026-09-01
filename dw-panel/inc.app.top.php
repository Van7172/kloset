<?php

use Develoweb\App\Model\Configuracion;
use Develoweb\App\Model\Msgbox;
use Develoweb\App\Model\Sesion;

error_reporting(E_ALL ^ E_NOTICE);
ini_set('allow_url_fopen', '1');

include_once '../inc.core.php';
require_once APP_UTILITIES . 'Libs.php';

if (!isset($_config)) {
	$_config = require dirname(__DIR__) . '/app/inc.config.php';
}

/*
 * La cookie de sesión se configura aquí y no se deja al php.ini del servidor:
 * el hosting trae SameSite=None con Secure=Off, combinación que los navegadores
 * rechazan, y sin cookie no hay forma de iniciar sesión en el panel.
 */
if (session_status() === PHP_SESSION_NONE) {
	$sesionSegura = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
		|| (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
		|| ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443);

	session_set_cookie_params([
		'lifetime' => 0,
		'path' => '/',
		'secure' => $sesionSegura,
		'httponly' => true,
		'samesite' => 'Lax',
	]);
	session_start();
}

if (isset($_SESSION['sesion'])) {
	$sesion = $_SESSION['sesion'];
	if (isset($_SESSION['usuario']) && $_SESSION['usuario']->getId() > 0) {
		$_SESSION['usuario']->setLogeado(true);
		$sesion->setUsuario($_SESSION['usuario']);
	}
} else {
	$sesion = new Sesion($_config);
	$_SESSION['sesion'] = $sesion;
}

$msgbox = new Msgbox();
if (isset($_SESSION['msg'])) {
	$msgbox = $_SESSION['msg'];
}

$config_site = new Configuracion($sesion->getUsuario(), $msgbox);
try {
	$configs = $config_site->getData();
} catch (Throwable $e) {
	$configs = [];
}
foreach ($configs as $clave => $valor) {
	if (!defined($clave)) {
		define($clave, $valor);
	}
}

if (!defined('NOMBRE_SITIO')) {
	define('NOMBRE_SITIO', 'Kloset');
}
