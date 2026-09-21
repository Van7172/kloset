<?php

use Develoweb\App\Model\Categorias;
use Develoweb\App\Model\Configuracion as ConfiguracionModel;
use Develoweb\App\Model\Dashboard;
use Develoweb\App\Model\MenuAdmin;
use Develoweb\App\Model\Productos as ProductosModel;
use Develoweb\App\Model\Roles;
use Develoweb\App\Model\Sesion;
use Develoweb\App\Model\Usuarios;

/** @var Sesion $sesion */

$navbar = true;
$header = true;
$admin_menu = [];
$layout = 'tpl_login.php';
$tpl = null;
$title = 'Acceso';
$crumb = 'Kloset';
$view_js = null;
$login_error = null;
$search_ph = null;
$primary_label = null;
$primary_disabled = false;

$param1 = isset($_GET['param1']) ? trim((string) $_GET['param1']) : '';
$usuario = $sesion->getUsuario();
$logeado = $usuario->getLogeado() && $usuario->getId() > 0;

if ($logeado && $param1 === 'sign-in') {
	header('Location: ' . DW_PANEL . 'dashboard');
	exit;
}

if ($logeado) {
	$layout = 'tpl_layout.php';
	$admin_menu = MenuAdmin::forUsuario($usuario);
	$ruta = $param1 !== '' ? $param1 : 'dashboard';

	if ($ruta === 'sign-out') {
		$sesion->logout();
	}

	$seccionesPermitidas = $usuario->findSeccionByCurrentUrl($ruta);
	if ($ruta !== 'dashboard' && empty($seccionesPermitidas)) {
		$tpl = 'tpl_404.php';
		$title = 'No encontrado';
	} else {
		switch ($ruta) {
			case 'dashboard':
				$kpis = Dashboard::kpis();
				$estado_stats = Dashboard::estadoStats();
				$stock_critico = Dashboard::stockCritico();
				$actividad = Dashboard::actividad();
				$tpl = 'tpl_dashboard.php';
				$title = 'Panel general';
				$crumb = 'Resumen';
				$search_ph = null;
				break;
			case 'categorias':
				$categorias = Categorias::get();
				$tpl = 'tpl_categorias.php';
				$view_js = 'categorias.js';
				$title = 'Categorías';
				$crumb = 'Catálogo';
				$search_ph = 'nombre o URL de la categoría';
				$primary_label = 'Nueva categoría';
				break;
			case 'productos':
				$productos = ProductosModel::get();
				$categorias = Categorias::getActivas();
				$tpl = 'tpl_productos.php';
				$view_js = 'productos.js';
				$title = 'Productos';
				$crumb = 'Catálogo';
				$search_ph = 'nombre o URL del producto';
				$primary_label = 'Nuevo producto';
				$primary_disabled = empty($categorias);
				break;
			case 'pedidos':
				$tpl = 'tpl_pedidos.php';
				$title = 'Pedidos';
				$crumb = 'Ventas';
				$search_ph = 'id_pedido o cliente';
				break;
			case 'reportes':
				$tpl = 'tpl_reportes.php';
				$title = 'Reportes';
				$crumb = 'Ventas';
				break;
			case 'usuarios':
				$users = Usuarios::get();
				$roles = Roles::getAll();
				$tpl = 'tpl_usuarios.php';
				$view_js = 'usuarios.js';
				$title = 'Usuarios';
				$crumb = 'Sistema · ACL';
				$search_ph = 'nombre o correo';
				break;
			case 'configuracion':
				$configurations = ConfiguracionModel::getConfiguration();
				$tpl = 'tpl_configuracion.php';
				$view_js = 'configuracion.js';
				$title = 'Configuración';
				$crumb = 'Sistema';
				break;
			default:
				$tpl = 'tpl_404.php';
				$title = 'No encontrado';
				break;
		}
	}
} else {
	if ($param1 === 'sign-in' && $_SERVER['REQUEST_METHOD'] === 'POST') {
		$result = Sesion::signIn();
		if ($result === true) {
			header('Location: ' . DW_PANEL . 'dashboard');
			exit;
		}
		$login_error = is_array($result)
			? (is_string($result['errors'] ?? null) ? $result['errors'] : 'Credenciales inválidas')
			: 'Error de acceso';
	}
}

if ($tpl === null) {
	$tpl = 'tpl_login.php';
	$layout = 'tpl_login.php';
}

include ADMIN_TEMPLATE_HOST . $layout;
