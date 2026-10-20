<?php

use Develoweb\App\Model\Categorias;
use Develoweb\App\Model\Clientes;
use Develoweb\App\Model\Configuracion as ConfiguracionModel;
use Develoweb\App\Model\Dashboard;
use Develoweb\App\Model\MenuAdmin;
use Develoweb\App\Model\MiCuentaAdmin;
use Develoweb\App\Model\Notificaciones;
use Develoweb\App\Model\Pagos;
use Develoweb\App\Model\PedidosAdmin;
use Develoweb\App\Model\Productos as ProductosModel;
use Develoweb\App\Model\Sesion;
use Develoweb\App\Model\Variantes;

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
$logeado = $usuario->getLogeado() && $usuario->getId() > 0
	&& in_array((string) $usuario->getRol(), ['Administrador', 'Asesor'], true);

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

		$seccionesPermitidas = \Develoweb\App\Model\Acl::allows($ruta);
		if (!$seccionesPermitidas) {
		$tpl = 'tpl_404.php';
		$title = 'No encontrado';
	} else {
		switch ($ruta) {
			case 'dashboard':
				if ((string) $usuario->getRol() === 'Asesor') {
					$tpl = 'tpl_dashboard_asesor.php';
					$title = 'Panel de asesor';
					$crumb = 'Resumen';
					break;
				}
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
				$search_ph = 'código, nombre o URL del producto';
				$primary_label = 'Nuevo producto';
				$primary_disabled = empty($categorias);
				break;
			case 'variantes':
				$variantes = Variantes::get();
				$productos = ProductosModel::get();
				$umbralStock = Dashboard::umbralStock();
				$tpl = 'tpl_variantes.php';
				$view_js = 'variantes.js';
				$title = 'Inventario';
				$crumb = 'Catálogo · SKU';
				$search_ph = 'sku_variante o producto';
				$primary_label = 'Nueva variante';
				$primary_disabled = empty($productos);
				break;
			case 'pedidos':
				$pedidos = PedidosAdmin::get();
				$tpl = 'tpl_pedidos.php';
				$view_js = 'pedidos.js';
				$title = 'Pedidos';
				$crumb = 'Ventas';
				$search_ph = 'cliente, correo o id_pedido';
				break;
			case 'pagos':
				$pagos = Pagos::get();
				$tpl = 'tpl_pagos.php';
				$view_js = 'pagos.js';
				$title = 'Pagos';
				$crumb = 'Ventas';
				$search_ph = 'id_transaccion o cliente';
				break;
			case 'notificaciones':
				$notificaciones = Notificaciones::get();
				$alertas = Notificaciones::alertas();
				$tpl = 'tpl_notificaciones.php';
				$view_js = 'notificaciones.js';
				$title = 'Notificaciones';
				$crumb = 'Ventas';
				$search_ph = 'cliente o mensaje';
				$primary_label = 'Marcar leídas';
				break;
			case 'clientes':
				$clientes = Clientes::get();
				$tpl = 'tpl_clientes.php';
				$view_js = 'clientes.js';
				$title = 'Clientes';
				$crumb = 'Clientes';
				$search_ph = 'nombre o correo';
				break;
			case 'reportes':
				$tpl = 'tpl_reportes.php';
				$title = 'Reportes';
				$crumb = 'Ventas';
				break;
			case 'configuracion':
				$configurations = ConfiguracionModel::getConfiguration();
				$tpl = 'tpl_configuracion.php';
				$view_js = 'configuracion.js';
				$title = 'Configuración';
				$crumb = 'Sistema';
				break;
			case 'mi-cuenta':
				$miCuenta = MiCuentaAdmin::datos();
				$tpl = 'tpl_mi_cuenta.php';
				$view_js = 'mi-cuenta.js';
				$title = 'Mi cuenta';
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
