<?php

namespace Develoweb\App\Model;

class PedidosAdmin
{
	private const SECCION = 'pedidos';

	public const ESTADOS = ['pendiente_pago', 'pagado', 'en_preparacion', 'enviado', 'entregado', 'cancelado'];

	private const MENSAJES = [
		'pendiente_pago' => 'Tu pedido fue creado y está pendiente de pago.',
		'pagado' => 'Tu pago fue aprobado. Preparamos tu pedido.',
		'en_preparacion' => 'Tu pedido está en preparación.',
		'enviado' => 'Tu pedido salió del almacén.',
		'entregado' => 'Tu pedido fue entregado. ¡Gracias por tu compra!',
		'cancelado' => 'Tu pedido fue cancelado.',
	];

	public static function get(): array
	{
		$con = Conexion::getInstance();
		$sth = $con->query(
			"SELECT p.id_pedido, p.estado_pedido, p.total_pedido, p.fecha_creacion,
			        u.nombre_usuario_sistema AS cliente, u.correo_usuario_sistema AS correo
			 FROM pedidos p
			 INNER JOIN sistema_usuarios u ON u.id_usuario_sistema = p.id_usuario_sistema
			 ORDER BY p.fecha_creacion DESC"
		);
		return $sth->fetchAll();
	}

	public static function getById(): array
	{
		if ($error = Acl::guard(self::SECCION)) {
			return $error;
		}

		$id = (int) ($_POST['id'] ?? 0);
		$con = Conexion::getInstance();

		$sth = $con->prepare(
			"SELECT p.id_pedido, p.estado_pedido, p.subtotal_pedido, p.total_pedido, p.fecha_creacion,
			        u.nombre_usuario_sistema AS cliente, u.correo_usuario_sistema AS correo,
			        d.direccion_envio_cliente, d.ciudad_envio_cliente, d.referencia_envio_cliente,
			        pg.metodo_pago, pg.marca_pago, pg.ultimos_digitos_pago, pg.estado_pago, pg.id_transaccion_pasarela_pago
			 FROM pedidos p
			 INNER JOIN sistema_usuarios u ON u.id_usuario_sistema = p.id_usuario_sistema
			 INNER JOIN direcciones_envio_clientes d ON d.id_direccion_envio = p.id_direccion_envio
			 LEFT JOIN pagos pg ON pg.id_pedido = p.id_pedido
			 WHERE p.id_pedido = :id"
		);
		$sth->execute([':id' => $id]);
		$pedido = $sth->fetch();
		if (!$pedido) {
			return ['status' => 'error', 'message' => 'Pedido no encontrado'];
		}

		$sth = $con->prepare(
			'SELECT pi.cantidad_pedido_item, pi.precio_unitario_pedido_item,
			        v.sku_variante, v.talla_variante, v.corte_variante, pr.nombre_producto
			 FROM pedidos_items pi
			 INNER JOIN productos_variantes v ON v.id_variante = pi.id_variante
			 INNER JOIN productos pr ON pr.id_producto = v.id_producto
			 WHERE pi.id_pedido = :id'
		);
		$sth->execute([':id' => $id]);
		$items = $sth->fetchAll();

		$sth = $con->prepare(
			"SELECT h.estado_historial_estado_pedido, h.comentario_historial_estado_pedido, h.fecha_creacion,
			        u.nombre_usuario_sistema AS autor
			 FROM historial_estados_pedidos h
			 LEFT JOIN sistema_usuarios u ON u.id_usuario_sistema = h.id_usuario_sistema
			 WHERE h.id_pedido = :id
			 ORDER BY h.fecha_creacion DESC, h.id_historial_estado_pedido DESC"
		);
		$sth->execute([':id' => $id]);
		$historial = $sth->fetchAll();

		return ['status' => 'success', 'pedido' => $pedido, 'items' => $items, 'historial' => $historial];
	}

	public static function cambiarEstado(): array
	{
		if ($error = Acl::guard(self::SECCION)) {
			return $error;
		}

		$id = (int) ($_POST['id'] ?? 0);
		$estado = (string) ($_POST['estado'] ?? '');
		$comentario = trim((string) ($_POST['comentario'] ?? ''));

		if ($id <= 0) {
			return ['status' => 'error', 'message' => 'Pedido no válido'];
		}
		if (!in_array($estado, self::ESTADOS, true)) {
			return ['status' => 'error', 'message' => 'Estado no válido'];
		}

		$con = Conexion::getInstance();
		$sth = $con->prepare('SELECT id_usuario_sistema, estado_pedido FROM pedidos WHERE id_pedido = :id');
		$sth->execute([':id' => $id]);
		$pedido = $sth->fetch();
		if (!$pedido) {
			return ['status' => 'error', 'message' => 'Pedido no encontrado'];
		}
		if ($pedido['estado_pedido'] === $estado) {
			return ['status' => 'error', 'message' => 'El pedido ya está en ese estado'];
		}

		$admin = Acl::usuario();
		$comentarioFinal = $comentario !== '' ? $comentario : 'Cambio manual desde el panel';

		try {
			$con->beginTransaction();

			$con->prepare('UPDATE pedidos SET estado_pedido = :estado WHERE id_pedido = :id')
				->execute([':estado' => $estado, ':id' => $id]);

			$con->prepare(
				'INSERT INTO historial_estados_pedidos (id_pedido, id_usuario_sistema, estado_historial_estado_pedido, comentario_historial_estado_pedido)
				 VALUES (:pedido, :usuario, :estado, :comentario)'
			)->execute([
				':pedido' => $id,
				':usuario' => $admin ? $admin->getId() : null,
				':estado' => $estado,
				':comentario' => $comentarioFinal,
			]);

			$mensaje = self::MENSAJES[$estado] ?? ('Tu pedido cambió a ' . str_replace('_', ' ', $estado) . '.');
			$con->prepare(
				'INSERT INTO notificaciones (id_usuario_sistema, id_pedido, mensaje_notificacion) VALUES (:usuario, :pedido, :mensaje)'
			)->execute([':usuario' => $pedido['id_usuario_sistema'], ':pedido' => $id, ':mensaje' => $mensaje]);

			$con->commit();
		} catch (\Throwable $e) {
			$con->rollBack();
			error_log('Kloset · fallo al cambiar estado_pedido: ' . $e->getMessage());
			return ['status' => 'error', 'message' => 'No se pudo actualizar el pedido'];
		}

		return [
			'status' => 'success',
			'message' => 'estado_pedido → ' . str_replace('_', ' ', $estado) . ' · historial y notificación creados',
		];
	}
}
