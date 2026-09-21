<?php

namespace Develoweb\App\Model;

class Dashboard
{
	public const ESTADOS_PEDIDO = ['pendiente_pago', 'pagado', 'en_preparacion', 'enviado', 'entregado', 'cancelado'];
	private const ESTADOS_ABIERTOS = ['pendiente_pago', 'pagado', 'en_preparacion'];

	/**
	 * Umbral de stock crítico, configurable desde sistema_configuraciones.
	 */
	public static function umbralStock(): int
	{
		$con = Conexion::getInstance();
		$sth = $con->prepare('SELECT valor_configuracion FROM sistema_configuraciones WHERE llave_configuracion = :k');
		$sth->execute([':k' => 'inventario.alerta_stock']);
		$valor = (int) $sth->fetchColumn();
		return $valor > 0 ? $valor : 6;
	}

	public static function kpis(): array
	{
		$con = Conexion::getInstance();
		$umbral = self::umbralStock();

		$ventas = (float) $con->query(
			"SELECT COALESCE(SUM(total_pedido), 0) FROM pedidos
			 WHERE estado_pedido NOT IN ('cancelado', 'pendiente_pago')
			   AND YEAR(fecha_creacion) = YEAR(CURDATE())
			   AND MONTH(fecha_creacion) = MONTH(CURDATE())"
		)->fetchColumn();

		$abiertos = (int) $con->query(
			"SELECT COUNT(*) FROM pedidos WHERE estado_pedido IN ('pendiente_pago', 'pagado', 'en_preparacion')"
		)->fetchColumn();

		$esperandoPago = (int) $con->query(
			"SELECT COUNT(*) FROM pedidos WHERE estado_pedido = 'pendiente_pago'"
		)->fetchColumn();

		$sth = $con->prepare('SELECT COUNT(*) FROM productos_variantes WHERE stock_variante < :u');
		$sth->execute([':u' => $umbral]);
		$bajoMinimo = (int) $sth->fetchColumn();

		$clientes = (int) $con->query(
			"SELECT COUNT(*) FROM sistema_usuarios u
			 INNER JOIN sistema_roles r ON r.id_rol = u.id_rol
			 WHERE r.nombre_rol = 'Cliente'"
		)->fetchColumn();

		$conPerfil = (int) $con->query('SELECT COUNT(*) FROM perfiles_corporales')->fetchColumn();

		return [
			[
				'label' => 'Ventas del mes',
				'value' => 'S/ ' . number_format($ventas, 2),
				'delta' => 'Pedidos pagados o posteriores',
				'clase' => 'kl-fg-ok',
			],
			[
				'label' => 'Pedidos abiertos',
				'value' => (string) $abiertos,
				'delta' => $esperandoPago . ' esperan pago',
				'clase' => 'kl-fg-warn',
			],
			[
				'label' => 'SKU bajo mínimo',
				'value' => (string) $bajoMinimo,
				'delta' => 'umbral ' . $umbral . ' uds',
				'clase' => 'kl-fg-red',
			],
			[
				'label' => 'Clientes con perfil',
				'value' => $conPerfil . ' / ' . $clientes,
				'delta' => 'con perfil corporal completo',
				'clase' => 'kl-fg-soft',
			],
		];
	}

	public static function estadoStats(): array
	{
		$con = Conexion::getInstance();
		$sth = $con->query('SELECT estado_pedido, COUNT(*) AS n FROM pedidos GROUP BY estado_pedido');
		$conteo = [];
		foreach ($sth->fetchAll() as $fila) {
			$conteo[$fila['estado_pedido']] = (int) $fila['n'];
		}
		$total = array_sum($conteo) ?: 1;

		$stats = [];
		foreach (self::ESTADOS_PEDIDO as $estado) {
			$n = $conteo[$estado] ?? 0;
			$stats[] = [
				'label' => str_replace('_', ' ', $estado),
				'n' => $n,
				'pct' => round(($n / $total) * 100) . '%',
				'color' => self::colorEstado($estado),
			];
		}

		return $stats;
	}

	public static function colorEstado(string $estado): string
	{
		if ($estado === 'entregado') {
			return 'var(--ok)';
		}
		if ($estado === 'cancelado') {
			return 'var(--red)';
		}
		if ($estado === 'pendiente_pago') {
			return 'var(--warn)';
		}
		return 'var(--ink)';
	}

	public static function stockCritico(int $limite = 6): array
	{
		$con = Conexion::getInstance();
		$umbral = self::umbralStock();
		$sth = $con->prepare(
			'SELECT v.sku_variante, v.stock_variante, v.talla_variante, v.corte_variante,
			        p.nombre_producto
			 FROM productos_variantes v
			 INNER JOIN productos p ON p.id_producto = v.id_producto
			 WHERE v.stock_variante < :u
			 ORDER BY v.stock_variante ASC
			 LIMIT ' . (int) $limite
		);
		$sth->execute([':u' => $umbral]);
		return $sth->fetchAll();
	}

	public static function actividad(int $limite = 5): array
	{
		$con = Conexion::getInstance();
		$sth = $con->query(
			'SELECT h.id_pedido, h.estado_historial_estado_pedido AS estado,
			        h.comentario_historial_estado_pedido AS comentario, h.fecha_creacion,
			        u.nombre_usuario_sistema
			 FROM historial_estados_pedidos h
			 LEFT JOIN sistema_usuarios u ON u.id_usuario_sistema = h.id_usuario_sistema
			 ORDER BY h.fecha_creacion DESC, h.id_historial_estado_pedido DESC
			 LIMIT ' . (int) $limite
		);

		$eventos = [];
		foreach ($sth->fetchAll() as $fila) {
			$eventos[] = [
				'text' => 'Pedido #' . $fila['id_pedido'] . ' pasó a ' . str_replace('_', ' ', $fila['estado']),
				'meta' => ($fila['nombre_usuario_sistema'] ?: 'sistema') . ' · ' . date('d/m/Y H:i', strtotime($fila['fecha_creacion'])),
				'comentario' => $fila['comentario'],
			];
		}

		return $eventos;
	}
}
