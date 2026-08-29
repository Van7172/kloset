<?php

use Develoweb\App\Model\Configuracion;
use Develoweb\App\Model\Sesion;

session_start();	

ini_set('display_errors',1);
error_reporting(E_ALL);

error_reporting(E_ALL ^ E_NOTICE);

include_once 'inc.core.php';

require_once APP_UTILITIES . "Libs.php";



if (isset($_SESSION['sesion_agencia'])) {
	$sesion = $_SESSION['sesion_agencia'];
} else {
	$sesion = new Sesion($_config);
	$_SESSION['sesion_agencia'] = $sesion;
}

$config_site = new Configuracion($sesion->getUsuario(), NULL);
$configs = $config_site->getData();

foreach ($configs as $clave => $valor) {
	define($clave, $valor);
}
