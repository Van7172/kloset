<?php

namespace Develoweb\App\Model;

class MenuAdmin
{
	public static function forUsuario(Usuario $usuario): array
	{
		$sections = $usuario->getSecciones();
		if (empty($sections) && (string) $usuario->getRol() !== 'Administrador') {
			return [];
		}

		$counts = self::counts();

		$grouped = [];
		$permitidas = ['dashboard','categorias','productos','variantes','pedidos','pagos','notificaciones','clientes','configuracion'];
		foreach ($sections as $item) {
			$sec = $item['seccion'] ?? null;
			if (!$sec || !is_object($sec) || $sec->getEstado() !== 'activo') {
				continue;
			}
			$url = $sec->getUrl();
			if (!in_array($url, $permitidas, true)) continue;
			if ((string) $usuario->getRol() === 'Asesor' && !Acl::allows($url)) continue;
			$mod = $sec->getModulo();
			$modId = $mod->getId();
			if (!isset($grouped[$modId])) {
				$grouped[$modId] = [
					'nombre_modulo' => $mod->getNombre(),
					'secciones' => [],
				];
			}
			$grouped[$modId]['secciones'][] = [
				'nombre_seccion' => $sec->getNombre(),
				'url_seccion' => $url,
				'count' => $counts[$url] ?? null,
			];
		}

		if ((string) $usuario->getRol() === 'Administrador') {
			$grouped['cuentas'] = ['nombre_modulo' => 'Sistema', 'secciones' => [[
				'nombre_seccion' => 'Usuarios', 'url_seccion' => 'usuarios', 'count' => null,
			]]];
		}
		return array_values($grouped);
	}

	/**
	 * Contadores que el panel muestra junto a cada sección del menú.
	 */
	public static function counts(): array
	{
		$con = Conexion::getInstance();

		return [
			'categorias' => (int) $con->query('SELECT COUNT(*) FROM categorias')->fetchColumn(),
			'productos' => (int) $con->query('SELECT COUNT(*) FROM productos')->fetchColumn(),
			'variantes' => (int) $con->query('SELECT COUNT(*) FROM productos_variantes')->fetchColumn(),
			'pedidos' => (int) $con->query(
				"SELECT COUNT(*) FROM pedidos
				 WHERE estado_pedido IN ('pagado', 'en_preparacion', 'enviado', 'en_reparto')"
			)->fetchColumn(),
			'pagos' => (int) $con->query('SELECT COUNT(*) FROM pagos')->fetchColumn(),
			'notificaciones' => (int) $con->query(
				'SELECT COUNT(*) FROM notificaciones WHERE leido_notificacion = 0'
			)->fetchColumn(),
			'clientes' => (int) $con->query(
				"SELECT COUNT(*) FROM sistema_usuarios u
				 INNER JOIN sistema_roles r ON r.id_rol = u.id_rol
				 WHERE r.nombre_rol = 'Cliente'"
			)->fetchColumn(),
		];
	}
}
