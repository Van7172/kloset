<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/utilities/vendor/autoload.php';
require_once __DIR__ . '/../inc.core.php';
require_once APP_UTILITIES . 'Libs.php';
require_once APP_UTILITIES . 'JwtHelper.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	http_response_code(204);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
	if (str_contains($contentType, 'application/json')) {
		$raw = file_get_contents('php://input');
		$json = json_decode($raw, true);
		if (is_array($json)) {
			$_POST = array_merge($_POST, $json);
		}
	}
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
if (!preg_match('#/api/v1/?(.*)$#', $uri, $m)) {
	http_response_code(404);
	echo json_encode(['status' => 'error', 'message' => 'API no encontrada']);
	exit;
}

$segments = ($m[1] ?? '') !== '' ? explode('/', trim($m[1], '/')) : [];
$resource = $segments[0] ?? '';
$action = $segments[1] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

$routes = [
	'GET' => [
		'health' => fn () => ['status' => 'ok', 'service' => 'kloset-api'],
		'productos' => ['Develoweb\\App\\Model\\Productos', 'listPublic'],
		'producto' => ['Develoweb\\App\\Model\\Productos', 'getByUrlPublic'],
		'auth/me' => ['Develoweb\\App\\Model\\AuthApi', 'me'],
		'medidas' => ['Develoweb\\App\\Model\\PerfilesCorporales', 'mio'],
		'carrito' => ['Develoweb\\App\\Model\\Carritos', 'mio'],
		'pedidos' => ['Develoweb\\App\\Model\\Pedidos', 'mios'],
	],
	'POST' => [
		'auth/login' => ['Develoweb\\App\\Model\\AuthApi', 'login'],
		'auth/register' => ['Develoweb\\App\\Model\\AuthApi', 'register'],
		'medidas' => ['Develoweb\\App\\Model\\PerfilesCorporales', 'guardar'],
		'carrito/agregar' => ['Develoweb\\App\\Model\\Carritos', 'agregar'],
		'carrito/eliminar' => ['Develoweb\\App\\Model\\Carritos', 'quitar'],
		'pedidos' => ['Develoweb\\App\\Model\\Pedidos', 'crear'],
	],
];

$key = $resource;
if ($resource === 'auth') {
	$key = 'auth/' . $action;
}
if ($resource === 'producto') {
	$key = 'producto';
}
if ($resource === 'carrito' && $action !== '') {
	$key = 'carrito/' . $action;
}

$handler = $routes[$method][$key] ?? null;
if (!$handler) {
	http_response_code(404);
	echo json_encode(['status' => 'error', 'message' => 'Ruta no encontrada']);
	exit;
}

$result = is_callable($handler) ? $handler() : call_user_func($handler);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
