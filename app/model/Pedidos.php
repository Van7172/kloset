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
		$idDireccionSolicitada = (int) ($_POST['id_direccion'] ?? 0);
		$marcaTarjeta = trim((string) ($_POST['marca_tarjeta'] ?? 'Tarjeta'));
		$ultimosDigitos = substr(preg_replace('/\D/', '', (string) ($_POST['ultimos_digitos'] ?? '')), -4);
		$numeroSimulado = preg_replace('/\D/', '', (string) ($_POST['numero_simulado'] ?? ''));
		$otpSimulado = preg_replace('/\D/', '', (string) ($_POST['otp_simulado'] ?? ''));
		if ($numeroSimulado !== '4242424242424242' && !($numeroSimulado === '4000000000003220' && preg_match('/^\d{6}$/', $otpSimulado))) {
			return ['status'=>'error','message'=>'Pago de demostración no aprobado. Usa una tarjeta de prueba válida.'];
		}
		$ultimosDigitos = substr($numeroSimulado, -4);

		if ($idDireccionSolicitada <= 0 && ($direccion === '' || $ciudad === '')) {
			return ['status' => 'error', 'message' => 'La dirección de envío es obligatoria'];
		}

		$departamentoManual = $idDireccionSolicitada <= 0 ? Direcciones::departamentoParaDistrito($ciudad) : null;
		if ($idDireccionSolicitada <= 0 && !$departamentoManual) {
			http_response_code(422);
			return ['status' => 'error', 'message' => 'Selecciona un distrito dentro de la cobertura de Lima y Callao'];
		}

		$con = Conexion::getInstance();
		try {
			$con->beginTransaction();

		// `carritos.id_usuario_sistema` es UNIQUE: una sola fila de carrito por usuario.
		$sth = $con->prepare('SELECT id_carrito FROM carritos WHERE id_usuario_sistema = :id FOR UPDATE');
		$sth->execute([':id' => $userId]);
		$idCarrito = $sth->fetchColumn();
		if (!$idCarrito) {
			$con->rollBack();
			return ['status' => 'error', 'message' => 'Tu bolsa está vacía'];
		}

		$sth = $con->prepare(
			'SELECT ci.id_carrito_item, ci.id_variante, ci.cantidad_carrito_item,
		        v.stock_variante, v.talla_variante, v.corte_variante, v.sku_variante, c.nombre_color,
			        p.id_producto, p.nombre_producto, p.precio_producto, p.estado_producto
			 FROM carritos_items ci
			 INNER JOIN productos_variantes v ON v.id_variante = ci.id_variante
		 INNER JOIN productos_colores c ON c.id_color = v.id_color
			 INNER JOIN productos p ON p.id_producto = v.id_producto
			 WHERE ci.id_carrito = :id'
		);
		$sth->execute([':id' => $idCarrito]);
		$items = $sth->fetchAll();
		if (empty($items)) {
			$con->rollBack();
			return ['status' => 'error', 'message' => 'Tu bolsa está vacía'];
		}

		foreach ($items as $item) {
			if ($item['estado_producto'] !== 'activo') {
				$con->rollBack();
				return ['status' => 'error', 'message' => "La prenda {$item['nombre_producto']} ya no está disponible. Retírala de tu bolsa."];
			}
			if ((int) $item['stock_variante'] < (int) $item['cantidad_carrito_item']) {
				$con->rollBack();
				return ['status' => 'error', 'message' => "Ya no hay stock suficiente de {$item['nombre_producto']}"];
			}
		}

		$subtotal = 0.0;
		foreach ($items as $item) {
			$subtotal += (float) $item['precio_producto'] * (int) $item['cantidad_carrito_item'];
		}
		$total = $subtotal; // envío gratis, sin impuestos adicionales en este simulador

			if ($idDireccionSolicitada > 0) {
				$sth = $con->prepare('SELECT id_direccion_envio FROM direcciones_envio_clientes WHERE id_direccion_envio = ? AND id_usuario_sistema = ? AND activa_envio_cliente = 1');
				$sth->execute([$idDireccionSolicitada, $userId]);
				$idDireccion = (int) $sth->fetchColumn();
				if (!$idDireccion) throw new \RuntimeException('Dirección de envío no disponible');
			} else {
				$sth = $con->prepare(
					'INSERT INTO direcciones_envio_clientes (id_usuario_sistema, nombre_direccion_cliente, direccion_envio_cliente, ciudad_envio_cliente, departamento_envio_cliente, referencia_envio_cliente)
						 VALUES (:id, :nombre, :direccion, :ciudad, :departamento, :referencia)'
				);
				$sth->execute([
					':id' => $userId,
					':nombre' => 'Entrega',
					':direccion' => $direccion,
					':ciudad' => $ciudad,
					':departamento' => $departamentoManual,
					':referencia' => $referencia !== '' ? $referencia : null,
				]);
				$idDireccion = (int) $con->lastInsertId();
			}

			$sth = $con->prepare(
				"INSERT INTO pedidos (id_usuario_sistema, id_direccion_envio, estado_pedido, subtotal_pedido, total_pedido, fecha_pago_aprobado, entrega_estimada_pedido)
				 VALUES (:id, :direccion, 'pagado', :subtotal, :total, NOW(), DATE_ADD(CURDATE(), INTERVAL 5 DAY))"
			);
			$sth->execute([
				':id' => $userId,
				':direccion' => $idDireccion,
				':subtotal' => number_format($subtotal, 2, '.', ''),
				':total' => number_format($total, 2, '.', ''),
			]);
			$idPedido = (int) $con->lastInsertId();

			$insertItem = $con->prepare(
				'INSERT INTO pedidos_items (id_pedido, id_variante, cantidad_pedido_item, precio_unitario_pedido_item,
				 nombre_producto_compra,nombre_color_compra,talla_compra,corte_compra,sku_compra)
				 VALUES (:pedido, :variante, :cantidad, :precio, :nombre, :color, :talla, :corte, :sku)'
			);
			$descontarStock = $con->prepare(
				'UPDATE productos_variantes SET stock_variante = stock_variante - :cantidad WHERE id_variante = :variante AND stock_variante >= :minimo'
			);
			foreach ($items as $item) {
				$descontarStock->execute([
					':cantidad' => $item['cantidad_carrito_item'],
					':variante' => $item['id_variante'],
					':minimo' => $item['cantidad_carrito_item'],
				]);
				if ($descontarStock->rowCount() !== 1) throw new \RuntimeException('Stock insuficiente al confirmar');
				$stockSth = $con->prepare('SELECT stock_variante FROM productos_variantes WHERE id_variante=?');
				$stockSth->execute([(int)$item['id_variante']]);
				if ((int)$stockSth->fetchColumn() === 0) {
					$con->prepare("INSERT INTO inventario_eventos (id_variante,tipo_evento,id_usuario_sistema) VALUES (?,'sin_stock',?)")
						->execute([(int)$item['id_variante'],$userId]);
				}
				$insertItem->execute([
					':pedido' => $idPedido,
					':variante' => $item['id_variante'],
					':cantidad' => $item['cantidad_carrito_item'],
					':precio' => $item['precio_producto'],
					':nombre' => $item['nombre_producto'],
					':color' => $item['nombre_color'],
					':talla' => $item['talla_variante'],
					':corte' => $item['corte_variante'],
					':sku' => $item['sku_variante'],
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
			$pref = $con->prepare('SELECT avisos_pedidos_usuario FROM sistema_usuarios WHERE id_usuario_sistema=?');
			$pref->execute([$userId]);
			if ($pref->fetchColumn()) $con->prepare('INSERT INTO notificaciones (id_usuario_sistema,id_pedido,mensaje_notificacion) VALUES (?,?,?)')->execute([$userId,$idPedido,'Tu pago fue aprobado y tu pedido está confirmado.']);

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
				'id_direccion_envio' => $idDireccion,
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
			        p.subtotal_pedido, p.id_direccion_envio, p.transportista_pedido, p.seguimiento_pedido, p.entrega_estimada_pedido,
			        d.direccion_envio_cliente, d.ciudad_envio_cliente, d.referencia_envio_cliente,
			        pg.marca_pago, pg.ultimos_digitos_pago
			 FROM pedidos p
			 INNER JOIN direcciones_envio_clientes d ON d.id_direccion_envio = p.id_direccion_envio
			 LEFT JOIN pagos pg ON pg.id_pedido = p.id_pedido
			 WHERE p.id_usuario_sistema = :id
			 ORDER BY p.fecha_creacion DESC"
		);
		$sth->execute([':id' => $userId]);
		$pedidos = $sth->fetchAll();

		$itemsSth = $con->prepare(
			'SELECT pi.id_variante,pi.cantidad_pedido_item, pi.precio_unitario_pedido_item,
			        COALESCE(pi.talla_compra,v.talla_variante) AS talla_variante,
			        COALESCE(pi.corte_compra,v.corte_variante) AS corte_variante,
			        COALESCE(pi.nombre_color_compra,c.nombre_color) AS nombre_color,
			        COALESCE(pi.sku_compra,v.sku_variante) AS sku_variante,
			        pr.id_producto,COALESCE(pi.nombre_producto_compra,pr.nombre_producto) AS nombre_producto,pr.url_producto,
			        (SELECT i.url_imagen FROM productos_imagenes i WHERE i.id_color=v.id_color ORDER BY i.principal_imagen DESC,i.orden_imagen,i.id_imagen LIMIT 1) AS url_imagen
			 FROM pedidos_items pi
			 INNER JOIN productos_variantes v ON v.id_variante = pi.id_variante
			 INNER JOIN productos_colores c ON c.id_color = v.id_color
			 INNER JOIN productos pr ON pr.id_producto = v.id_producto
			 WHERE pi.id_pedido = :id'
		);

		foreach ($pedidos as &$pedido) {
			$itemsSth->execute([':id' => $pedido['id_pedido']]);
			$pedido['ref'] = self::ref((int) $pedido['id_pedido']);
			$pedido['items'] = $itemsSth->fetchAll();
			foreach ($pedido['items'] as &$item) {
				$item['url_imagen'] = $item['url_imagen'] ? IMGS . 'productos/' . $item['url_imagen'] : null;
			}
			unset($item);
		}
		unset($pedido);

		return ['status' => 'success', 'pedidos' => $pedidos];
	}

	public static function detalle(): array
	{
		$userId = JwtHelper::bearerUserId();
		if (!$userId) {
			http_response_code(401);
			return ['status' => 'error', 'message' => 'No autorizado'];
		}
		$id = (int) ($_GET['id'] ?? 0);
		$con = Conexion::getInstance();
		$sth = $con->prepare('SELECT p.id_pedido, p.estado_pedido, p.subtotal_pedido, p.total_pedido, p.fecha_creacion, p.transportista_pedido, p.seguimiento_pedido, p.entrega_estimada_pedido, d.direccion_envio_cliente, d.ciudad_envio_cliente, d.referencia_envio_cliente, pg.marca_pago, pg.ultimos_digitos_pago FROM pedidos p INNER JOIN direcciones_envio_clientes d ON d.id_direccion_envio = p.id_direccion_envio LEFT JOIN pagos pg ON pg.id_pedido = p.id_pedido WHERE p.id_pedido = ? AND p.id_usuario_sistema = ?');
		$sth->execute([$id, $userId]);
		$pedido = $sth->fetch();
		if (!$pedido) {
			http_response_code(404);
			return ['status' => 'error', 'message' => 'Pedido no encontrado'];
		}
		$pedido['ref'] = self::ref($id);
		$sth = $con->prepare('SELECT pi.id_variante,pi.cantidad_pedido_item,pi.precio_unitario_pedido_item, COALESCE(pi.talla_compra,v.talla_variante) AS talla_variante,COALESCE(pi.corte_compra,v.corte_variante) AS corte_variante,COALESCE(pi.nombre_color_compra,c.nombre_color) AS nombre_color,COALESCE(pi.sku_compra,v.sku_variante) AS sku_variante,pr.id_producto,COALESCE(pi.nombre_producto_compra,pr.nombre_producto) AS nombre_producto,pr.url_producto,(SELECT i.url_imagen FROM productos_imagenes i WHERE i.id_color=v.id_color ORDER BY i.principal_imagen DESC,i.orden_imagen,i.id_imagen LIMIT 1) AS url_imagen FROM pedidos_items pi INNER JOIN productos_variantes v ON v.id_variante=pi.id_variante INNER JOIN productos_colores c ON c.id_color=v.id_color INNER JOIN productos pr ON pr.id_producto=v.id_producto WHERE pi.id_pedido=?');
		$sth->execute([$id]);
		$pedido['items'] = $sth->fetchAll();
		foreach ($pedido['items'] as &$item) $item['url_imagen'] = $item['url_imagen'] ? IMGS . 'productos/' . $item['url_imagen'] : null;
		unset($item);
		$sth = $con->prepare('SELECT estado_historial_estado_pedido AS estado, comentario_historial_estado_pedido AS comentario, fecha_creacion AS fecha FROM historial_estados_pedidos WHERE id_pedido = ? ORDER BY fecha_creacion ASC, id_historial_estado_pedido ASC');
		$sth->execute([$id]);
		$pedido['historial'] = $sth->fetchAll();
		return ['status' => 'success', 'pedido' => $pedido];
	}

	public static function codigoEntrega(): array
	{
		$userId = JwtHelper::bearerUserId();
		if (!$userId) { http_response_code(401); return ['status'=>'error','message'=>'No autorizado']; }
		$id = (int) ($_GET['id'] ?? 0);
		$sth = Conexion::getInstance()->prepare('SELECT estado_pedido,codigo_entrega_cifrado FROM pedidos WHERE id_pedido=? AND id_usuario_sistema=?');
		$sth->execute([$id,$userId]);
		$pedido = $sth->fetch();
		if (!$pedido) { http_response_code(404); return ['status'=>'error','message'=>'Pedido no encontrado']; }
		return ['status'=>'success','codigo'=>$pedido['estado_pedido'] === 'en_reparto' ? CodigoEntrega::abrir($pedido['codigo_entrega_cifrado']) : null];
	}

	public static function cancelar(): array
	{
		$userId = JwtHelper::bearerUserId();
		if (!$userId) { http_response_code(401); return ['status' => 'error', 'message' => 'No autorizado']; }
		$id = (int) ($_POST['id'] ?? 0);
		$con = Conexion::getInstance();
		$con->beginTransaction();
		try {
			$sth = $con->prepare('SELECT estado_pedido FROM pedidos WHERE id_pedido = ? AND id_usuario_sistema = ? FOR UPDATE');
			$sth->execute([$id, $userId]);
			$estado = $sth->fetchColumn();
			if (!$estado) { $con->rollBack(); http_response_code(404); return ['status' => 'error', 'message' => 'Pedido no encontrado']; }
			if ($estado === 'cancelado') { $con->commit(); return ['status'=>'success','message'=>'El pedido ya estaba cancelado']; }
			if (!in_array($estado, ['pagado', 'en_preparacion'], true)) {
				$con->rollBack(); http_response_code(422);
				return ['status' => 'error', 'message' => 'Este pedido ya no se puede cancelar desde tu cuenta.'];
			}
			$con->prepare("UPDATE pedidos SET estado_pedido = 'cancelado' WHERE id_pedido = ?")->execute([$id]);
			$con->prepare('UPDATE productos_variantes v INNER JOIN pedidos_items pi ON pi.id_variante = v.id_variante SET v.stock_variante = v.stock_variante + pi.cantidad_pedido_item WHERE pi.id_pedido = ?')->execute([$id]);
			$con->prepare("INSERT INTO historial_estados_pedidos (id_pedido, id_usuario_sistema, estado_anterior_historial, estado_historial_estado_pedido, comentario_historial_estado_pedido) VALUES (?, ?, ?, 'cancelado', 'Cancelado por el cliente; pago simulado sin reembolso registrado')")->execute([$id, $userId, $estado]);
			$pref = $con->prepare('SELECT avisos_pedidos_usuario FROM sistema_usuarios WHERE id_usuario_sistema=?');
			$pref->execute([$userId]);
			if ($pref->fetchColumn()) $con->prepare('INSERT INTO notificaciones (id_usuario_sistema,id_pedido,mensaje_notificacion) VALUES (?, ?, ?)')->execute([$userId,$id,'Tu pedido fue cancelado. El pago simulado permanece aprobado; no se ha registrado un reembolso.']);
			$con->commit();
			return ['status' => 'success', 'message' => 'Pedido cancelado. El pago simulado permanece aprobado; no se registró un reembolso.'];
		} catch (\Throwable $e) {
			$con->rollBack(); error_log('Kloset · cancelar pedido: ' . $e->getMessage());
			return ['status' => 'error', 'message' => 'No se pudo cancelar el pedido'];
		}
	}
}
