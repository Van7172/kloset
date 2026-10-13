<?php

namespace Develoweb\App\Model;

class Dashboard
{
	public const ESTADOS_PEDIDO = ['pagado', 'en_preparacion', 'enviado', 'en_reparto', 'entregado', 'cancelado'];

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

		$ventasMes = $con->query(
			"SELECT DATE_FORMAT(p.fecha_pago_aprobado,'%Y-%m') AS mes,COALESCE(SUM(p.total_pedido),0) AS total
			 FROM pedidos p JOIN pagos pg ON pg.id_pedido=p.id_pedido AND pg.estado_pago='aprobado'
			 WHERE p.estado_pedido <> 'cancelado' AND p.fecha_pago_aprobado >= DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 1 MONTH),'%Y-%m-01')
			 GROUP BY mes"
		)->fetchAll();
		$totales = array_column($ventasMes, 'total', 'mes');
		$ventas = (float) ($totales[date('Y-m')] ?? 0);
		$mesAnterior = date('Y-m', strtotime('first day of previous month'));
		$anterior = (float) ($totales[$mesAnterior] ?? 0);
		$variacion = $anterior > 0 ? sprintf('%+.1f%% frente al mes anterior', ($ventas - $anterior) / $anterior * 100) : 'Sin base comparable del mes anterior';

		$abiertos = (int) $con->query(
			"SELECT COUNT(*) FROM pedidos WHERE estado_pedido IN ('pagado', 'en_preparacion', 'enviado', 'en_reparto')"
		)->fetchColumn();

		$sth = $con->prepare('SELECT COUNT(*) FROM productos_variantes WHERE stock_variante < :u');
		$sth->execute([':u' => $umbral]);
		$bajoMinimo = (int) $sth->fetchColumn();

		$clientes = (int) $con->query(
			"SELECT COUNT(*) FROM sistema_usuarios u
			 INNER JOIN sistema_roles r ON r.id_rol = u.id_rol
			 WHERE r.nombre_rol = 'Cliente'"
		)->fetchColumn();

		$conPerfil = (int) $con->query("SELECT COUNT(*) FROM perfiles_corporales pc JOIN sistema_usuarios u ON u.id_usuario_sistema=pc.id_usuario_sistema JOIN sistema_roles r ON r.id_rol=u.id_rol WHERE r.nombre_rol='Cliente' AND pc.estatura_perfil_corporal>=100 AND pc.pecho_perfil_corporal>=50 AND pc.cintura_perfil_corporal>=40 AND pc.cadera_perfil_corporal>=50")->fetchColumn();

		return [
			[
				'label' => 'Ventas del mes',
				'value' => 'S/ ' . number_format($ventas, 2),
				'delta' => $variacion,
				'url' => 'pagos',
				'clase' => 'kl-fg-ok',
			],
			[
				'label' => 'Pedidos abiertos',
				'value' => (string) $abiertos,
				'delta' => 'Pagado, preparación, enviado o reparto',
				'url' => 'pedidos',
				'clase' => 'kl-fg-warn',
			],
			[
				'label' => 'Stock crítico',
				'value' => (string) $bajoMinimo,
				'delta' => 'umbral ' . $umbral . ' uds',
				'url' => 'variantes',
				'clase' => 'kl-fg-red',
			],
			[
				'label' => 'Clientes',
				'value' => $conPerfil . ' / ' . $clientes,
				'delta' => 'con perfil corporal completo',
				'url' => 'clientes',
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
		return 'var(--ink)';
	}

	public static function stockCritico(int $limite = 6): array
	{
		$con = Conexion::getInstance();
		$umbral = self::umbralStock();
		$sth = $con->prepare(
			'SELECT v.sku_variante, v.stock_variante, v.talla_variante, v.corte_variante,c.nombre_color,
			        p.nombre_producto
			 FROM productos_variantes v
			 INNER JOIN productos p ON p.id_producto = v.id_producto
			 INNER JOIN productos_colores c ON c.id_color = v.id_color
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
		$historial = $con->query(
			"SELECT h.id_pedido,h.estado_historial_estado_pedido AS estado,h.comentario_historial_estado_pedido AS comentario,
			 h.fecha_creacion,u.nombre_usuario_sistema,NULL AS variante
			 FROM historial_estados_pedidos h LEFT JOIN sistema_usuarios u ON u.id_usuario_sistema=h.id_usuario_sistema
			 WHERE h.estado_historial_estado_pedido IN ('pagado','en_preparacion','enviado','en_reparto','entregado','cancelado')
			 ORDER BY h.fecha_creacion DESC LIMIT " . (int)$limite
		)->fetchAll();
		$stock = $con->query(
			"SELECT NULL AS id_pedido,'sin_stock' AS estado,NULL AS comentario,e.fecha_creacion,u.nombre_usuario_sistema,
			 CONCAT(p.nombre_producto,' · ',c.nombre_color,' · ',v.sku_variante) AS variante
			 FROM inventario_eventos e JOIN productos_variantes v ON v.id_variante=e.id_variante
			 JOIN productos p ON p.id_producto=v.id_producto JOIN productos_colores c ON c.id_color=v.id_color
			 LEFT JOIN sistema_usuarios u ON u.id_usuario_sistema=e.id_usuario_sistema
			 ORDER BY e.fecha_creacion DESC LIMIT " . (int)$limite
		)->fetchAll();
		$filas = array_merge($historial,$stock);
		usort($filas, static fn($a,$b) => strcmp($b['fecha_creacion'],$a['fecha_creacion']));

		$eventos = [];
		foreach (array_slice($filas,0,$limite) as $fila) {
			$eventos[] = [
				'text' => $fila['estado']==='sin_stock' ? 'Variante sin stock: '.$fila['variante'] : ($fila['estado']==='pagado' ? 'Pago aprobado · pedido #'.$fila['id_pedido'] : 'Pedido #'.$fila['id_pedido'].' pasó a '.str_replace('_',' ',$fila['estado'])),
				'meta' => ($fila['nombre_usuario_sistema'] ?: 'sistema') . ' · ' . date('d/m/Y H:i', strtotime($fila['fecha_creacion'])),
				'comentario' => $fila['comentario'],
			];
		}

		return $eventos;
	}
}
