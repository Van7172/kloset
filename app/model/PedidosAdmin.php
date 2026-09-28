<?php

namespace Develoweb\App\Model;

class PedidosAdmin
{
	private const SECCION = 'pedidos';

	public const ESTADOS = ['pendiente_pago', 'pagado', 'en_preparacion', 'enviado', 'en_reparto', 'entregado', 'cancelado'];

	private const MENSAJES = [
		'pendiente_pago' => 'Tu pedido fue creado y está pendiente de pago.',
		'pagado' => 'Tu pago fue aprobado. Preparamos tu pedido.',
		'en_preparacion' => 'Tu pedido está en preparación.',
		'enviado' => 'Tu pedido salió del almacén.',
		'en_reparto' => 'Tu pedido está en reparto.',
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
			        p.transportista_pedido, p.seguimiento_pedido, p.entrega_estimada_pedido,
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
		$transportista = trim((string) ($_POST['transportista'] ?? ''));
		$seguimiento = trim((string) ($_POST['seguimiento'] ?? ''));
		$entrega = trim((string) ($_POST['entrega_estimada'] ?? ''));
		if (mb_strlen($transportista) > 120 || mb_strlen($seguimiento) > 100 || mb_strlen($comentario) > 255
			|| ($entrega !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $entrega) || !\DateTime::createFromFormat('!Y-m-d', $entrega) || \DateTime::createFromFormat('!Y-m-d', $entrega)->format('Y-m-d') !== $entrega))) {
			return ['status' => 'error', 'message' => 'Revisa los datos de seguimiento'];
		}

		if ($id <= 0) {
			return ['status' => 'error', 'message' => 'Pedido no válido'];
		}
		if (!in_array($estado, self::ESTADOS, true)) {
			return ['status' => 'error', 'message' => 'Estado no válido'];
		}

		$con = Conexion::getInstance();
		$sth = $con->prepare('SELECT id_usuario_sistema, estado_pedido, transportista_pedido, seguimiento_pedido, entrega_estimada_pedido FROM pedidos WHERE id_pedido = :id');
		$sth->execute([':id' => $id]);
		$pedido = $sth->fetch();
		if (!$pedido) {
			return ['status' => 'error', 'message' => 'Pedido no encontrado'];
		}
		if (!array_key_exists('transportista', $_POST)) $transportista = $pedido['transportista_pedido'];
		if (!array_key_exists('seguimiento', $_POST)) $seguimiento = $pedido['seguimiento_pedido'];
		if (!array_key_exists('entrega_estimada', $_POST)) $entrega = $pedido['entrega_estimada_pedido'];
		if ($estado !== 'cancelado' && $estado === $pedido['estado_pedido'] && $comentario === '' && ($transportista ?: null) === $pedido['transportista_pedido'] && ($seguimiento ?: null) === $pedido['seguimiento_pedido'] && ($entrega ?: null) === $pedido['entrega_estimada_pedido']) return ['status'=>'success', 'message'=>'El pedido ya tiene estos datos'];
		if ($pedido['estado_pedido'] === 'cancelado' || ($pedido['estado_pedido'] === 'entregado' && $estado !== 'entregado')) {
			return ['status' => 'error', 'message' => 'Este pedido está cerrado'];
		}
		$orden = array_flip(self::ESTADOS);
		if ($estado !== 'cancelado' && $orden[$estado] < $orden[$pedido['estado_pedido']]) return ['status'=>'error', 'message'=>'El pedido no puede retroceder a un estado anterior'];

		$admin = Acl::usuario();
		$comentarioFinal = $comentario !== '' ? $comentario : 'Cambio manual desde el panel';

		try {
			$con->beginTransaction();
			$sth = $con->prepare('SELECT estado_pedido FROM pedidos WHERE id_pedido = ? FOR UPDATE');
			$sth->execute([$id]);
			$estadoActual = $sth->fetchColumn();
			if ($estadoActual !== $pedido['estado_pedido']) throw new \RuntimeException('El estado cambió; vuelve a cargar el pedido');
			if ($estado === 'cancelado') {
				$con->prepare('UPDATE productos_variantes v INNER JOIN pedidos_items pi ON pi.id_variante = v.id_variante SET v.stock_variante = v.stock_variante + pi.cantidad_pedido_item WHERE pi.id_pedido = ?')->execute([$id]);
			}

			$con->prepare('UPDATE pedidos SET estado_pedido = :estado, transportista_pedido = :transportista, seguimiento_pedido = :seguimiento, entrega_estimada_pedido = :entrega WHERE id_pedido = :id')
				->execute([':estado' => $estado, ':id' => $id, ':transportista' => $transportista ?: null, ':seguimiento' => $seguimiento ?: null, ':entrega' => $entrega ?: null]);

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
			$preferencia = $con->prepare('SELECT avisos_pedidos_usuario FROM sistema_usuarios WHERE id_usuario_sistema = ?');
			$preferencia->execute([$pedido['id_usuario_sistema']]);
			if ($preferencia->fetchColumn() && $estado !== $pedido['estado_pedido']) {
				$con->prepare('INSERT INTO notificaciones (id_usuario_sistema, id_pedido, mensaje_notificacion) VALUES (:usuario, :pedido, :mensaje)')
					->execute([':usuario' => $pedido['id_usuario_sistema'], ':pedido' => $id, ':mensaje' => $mensaje]);
			}

			$con->commit();
		} catch (\Throwable $e) {
			$con->rollBack();
			error_log('Kloset · fallo al cambiar estado_pedido: ' . $e->getMessage());
			return ['status' => 'error', 'message' => 'No se pudo actualizar el pedido'];
		}

		return [
			'status' => 'success',
			'message' => 'Pedido actualizado · estado e historial guardados',
		];
	}
}
