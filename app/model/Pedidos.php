<?php

namespace Develoweb\App\Model;

use Develoweb\App\Utilities\JwtHelper;

class Pedidos
{
	private static function ref(int $idPedido): string
	{
		return sprintf('KL-%05d', $idPedido);
	}

	public static function crear(): array
	{
		$userId = JwtHelper::bearerUserId();
		if (!$userId) {
			http_response_code(401);
			return ['status' => 'error', 'message' => 'No autorizado'];
		}

		$direccion = trim((string) ($_POST['direccion'] ?? ''));
		$ciudad = trim((string) ($_POST['ciudad'] ?? ''));
		$referencia = trim((string) ($_POST['referencia'] ?? ''));
		$marcaTarjeta = trim((string) ($_POST['marca_tarjeta'] ?? 'Tarjeta'));
		$ultimosDigitos = substr(preg_replace('/\D/', '', (string) ($_POST['ultimos_digitos'] ?? '')), -4);

		if ($direccion === '' || $ciudad === '') {
			return ['status' => 'error', 'message' => 'La dirección de envío es obligatoria'];
		}

		$con = Conexion::getInstance();

		// `carritos.id_usuario_sistema` es UNIQUE: una sola fila de carrito por usuario.
		$sth = $con->prepare('SELECT id_carrito FROM carritos WHERE id_usuario_sistema = :id');
		$sth->execute([':id' => $userId]);
		$idCarrito = $sth->fetchColumn();
		if (!$idCarrito) {
			return ['status' => 'error', 'message' => 'Tu bolsa está vacía'];
		}

		$sth = $con->prepare(
			'SELECT ci.id_carrito_item, ci.id_variante, ci.cantidad_carrito_item,
			        v.stock_variante, v.talla_variante, v.corte_variante,
			        p.id_producto, p.nombre_producto, p.precio_producto
			 FROM carritos_items ci
			 INNER JOIN productos_variantes v ON v.id_variante = ci.id_variante
			 INNER JOIN productos p ON p.id_producto = v.id_producto
			 WHERE ci.id_carrito = :id'
		);
		$sth->execute([':id' => $idCarrito]);
		$items = $sth->fetchAll();
		if (empty($items)) {
			return ['status' => 'error', 'message' => 'Tu bolsa está vacía'];
		}

		foreach ($items as $item) {
			if ((int) $item['stock_variante'] < (int) $item['cantidad_carrito_item']) {
				return ['status' => 'error', 'message' => "Ya no hay stock suficiente de {$item['nombre_producto']}"];
			}
		}

		$subtotal = 0.0;
		foreach ($items as $item) {
			$subtotal += (float) $item['precio_producto'] * (int) $item['cantidad_carrito_item'];
		}
		$total = $subtotal; // envío gratis, sin impuestos adicionales en este simulador

		try {
			$con->beginTransaction();

			$sth = $con->prepare(
				'INSERT INTO direcciones_envio_clientes (id_usuario_sistema, direccion_envio_cliente, ciudad_envio_cliente, referencia_envio_cliente)
				 VALUES (:id, :direccion, :ciudad, :referencia)'
			);
			$sth->execute([
				':id' => $userId,
				':direccion' => $direccion,
				':ciudad' => $ciudad,
				':referencia' => $referencia !== '' ? $referencia : null,
			]);
			$idDireccion = (int) $con->lastInsertId();

			$sth = $con->prepare(
				"INSERT INTO pedidos (id_usuario_sistema, id_direccion_envio, estado_pedido, subtotal_pedido, total_pedido)
				 VALUES (:id, :direccion, 'pagado', :subtotal, :total)"
			);
			$sth->execute([
				':id' => $userId,
				':direccion' => $idDireccion,
				':subtotal' => number_format($subtotal, 2, '.', ''),
				':total' => number_format($total, 2, '.', ''),
			]);
			$idPedido = (int) $con->lastInsertId();

			$insertItem = $con->prepare(
				'INSERT INTO pedidos_items (id_pedido, id_variante, cantidad_pedido_item, precio_unitario_pedido_item)
				 VALUES (:pedido, :variante, :cantidad, :precio)'
			);
			$descontarStock = $con->prepare(
				'UPDATE productos_variantes SET stock_variante = stock_variante - :cantidad WHERE id_variante = :variante'
			);
			foreach ($items as $item) {
				$insertItem->execute([
					':pedido' => $idPedido,
					':variante' => $item['id_variante'],
					':cantidad' => $item['cantidad_carrito_item'],
					':precio' => $item['precio_producto'],
				]);
				$descontarStock->execute([
					':cantidad' => $item['cantidad_carrito_item'],
					':variante' => $item['id_variante'],
				]);
			}

			$sth = $con->prepare(
				"INSERT INTO pagos (id_pedido, metodo_pago, marca_pago, ultimos_digitos_pago, monto_pago, estado_pago, id_transaccion_pasarela_pago)
				 VALUES (:pedido, 'tarjeta', :marca, :ultimos, :monto, 'aprobado', :txn)"
			);
			$sth->execute([
				':pedido' => $idPedido,
				':marca' => $marcaTarjeta,
				':ultimos' => $ultimosDigitos !== '' ? $ultimosDigitos : null,
				':monto' => number_format($total, 2, '.', ''),
				':txn' => 'SIM-' . strtoupper(bin2hex(random_bytes(6))),
			]);

			$sth = $con->prepare(
				"INSERT INTO historial_estados_pedidos (id_pedido, id_usuario_sistema, estado_historial_estado_pedido, comentario_historial_estado_pedido)
				 VALUES (:pedido, :usuario, 'pagado', 'Pago aprobado por la pasarela (simulador)')"
			);
			$sth->execute([':pedido' => $idPedido, ':usuario' => $userId]);

			// Vaciamos la única fila de carrito del usuario; ya quedó snapshotada en pedidos_items.
			$con->prepare('DELETE FROM carritos_items WHERE id_carrito = :id')
				->execute([':id' => $idCarrito]);

			$con->commit();
		} catch (\Throwable $e) {
			$con->rollBack();
			error_log('Kloset · fallo al crear pedido: ' . $e->getMessage());
			return ['status' => 'error', 'message' => 'No se pudo registrar el pedido, vuelve a intentarlo'];
		}

		return [
			'status' => 'success',
			'pedido' => [
				'id_pedido' => $idPedido,
				'ref' => self::ref($idPedido),
				'fecha' => date('Y-m-d H:i:s'),
				'estado' => 'pagado',
				'total' => (float) $total,
				'marca_tarjeta' => $marcaTarjeta,
				'ultimos_digitos' => $ultimosDigitos,
				'items' => array_map(static fn ($it) => [
					'id_producto' => (int) $it['id_producto'],
					'nombre_producto' => $it['nombre_producto'],
					'talla' => $it['talla_variante'],
					'corte' => $it['corte_variante'],
					'cantidad' => (int) $it['cantidad_carrito_item'],
					'precio' => (float) $it['precio_producto'],
				], $items),
			],
		];
	}

	public static function mios(): array
	{
		$userId = JwtHelper::bearerUserId();
		if (!$userId) {
			http_response_code(401);
			return ['status' => 'error', 'message' => 'No autorizado'];
		}

		$con = Conexion::getInstance();
		$sth = $con->prepare(
			"SELECT p.id_pedido, p.estado_pedido, p.total_pedido, p.fecha_creacion,
			        pg.marca_pago, pg.ultimos_digitos_pago
			 FROM pedidos p
			 LEFT JOIN pagos pg ON pg.id_pedido = p.id_pedido
			 WHERE p.id_usuario_sistema = :id
			 ORDER BY p.fecha_creacion DESC"
		);
		$sth->execute([':id' => $userId]);
		$pedidos = $sth->fetchAll();

		$itemsSth = $con->prepare(
			'SELECT pi.cantidad_pedido_item, pi.precio_unitario_pedido_item,
			        v.talla_variante, v.corte_variante, pr.nombre_producto
			 FROM pedidos_items pi
			 INNER JOIN productos_variantes v ON v.id_variante = pi.id_variante
			 INNER JOIN productos pr ON pr.id_producto = v.id_producto
			 WHERE pi.id_pedido = :id'
		);

		foreach ($pedidos as &$pedido) {
			$itemsSth->execute([':id' => $pedido['id_pedido']]);
			$pedido['ref'] = self::ref((int) $pedido['id_pedido']);
			$pedido['items'] = $itemsSth->fetchAll();
		}
		unset($pedido);

		return ['status' => 'success', 'pedidos' => $pedidos];
	}
}
