<?php

namespace Develoweb\App\Model;

use Develoweb\App\Utilities\JwtHelper;
use Develoweb\App\Utilities\Correo;

class Pedidos
{
	private static function siguienteCodigoPedido(\PDO $con): string
	{
		$con->exec(
			'INSERT INTO secuencias_pedidos (anio, ultimo_secuencial)
			 VALUES (YEAR(CURRENT_TIMESTAMP), LAST_INSERT_ID(1))
			 ON DUPLICATE KEY UPDATE ultimo_secuencial = LAST_INSERT_ID(ultimo_secuencial + 1)'
		);
		$secuencia = (int) $con->lastInsertId();
		$anio = (int) $con->query('SELECT YEAR(CURRENT_TIMESTAMP)')->fetchColumn();
		if ($secuencia < 1 || $anio < 1) throw new \RuntimeException('No se pudo asignar el código del pedido');
		return sprintf('PED-%04d-%04d', $anio, $secuencia);
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

			[$codigoEntregaHash, $codigoEntregaCifrado] = CodigoEntrega::crear();
			[$codigoAnulacionHash, $codigoAnulacionCifrado] = CodigoEntrega::crear();
			$sth = $con->prepare(
				"INSERT INTO pedidos (
					id_usuario_sistema, id_direccion_envio, estado_pedido, subtotal_pedido, total_pedido,
					fecha_pago_aprobado, entrega_estimada_pedido, codigo_entrega_hash, codigo_entrega_cifrado,
					codigo_anulacion_hash, codigo_anulacion_cifrado
				) VALUES (
					:id, :direccion, 'pagado', :subtotal, :total,
					NOW(), DATE_ADD(CURDATE(), INTERVAL 5 DAY), :entrega_hash, :entrega_cifrado,
					:anulacion_hash, :anulacion_cifrado
				)"
			);
			$sth->execute([
				':id' => $userId,
				':direccion' => $idDireccion,
				':subtotal' => number_format($subtotal, 2, '.', ''),
				':total' => number_format($total, 2, '.', ''),
				':entrega_hash' => $codigoEntregaHash,
				':entrega_cifrado' => $codigoEntregaCifrado,
				':anulacion_hash' => $codigoAnulacionHash,
				':anulacion_cifrado' => $codigoAnulacionCifrado,
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
			$transaccionCreada = false;
			for ($intento = 0; $intento < 5; $intento++) {
				$transaccion = 'SIM-' . strtoupper(bin2hex(random_bytes(6)));
				try {
					$sth->execute([
						':pedido' => $idPedido,
						':marca' => $marcaTarjeta,
						':ultimos' => $ultimosDigitos !== '' ? $ultimosDigitos : null,
						':monto' => number_format($total, 2, '.', ''),
						':txn' => $transaccion,
					]);
					$transaccionCreada = true;
					break;
				} catch (\PDOException $e) {
					if ($e->getCode() !== '23000') throw $e;
					$duplicada = $con->prepare('SELECT 1 FROM pagos WHERE id_transaccion_pasarela_pago=?');
					$duplicada->execute([$transaccion]);
					if (!$duplicada->fetchColumn() || $intento === 4) throw $e;
				}
			}
			if (!$transaccionCreada) throw new \RuntimeException('No se pudo generar un identificador de transacción único');

			$codigoPedido = self::siguienteCodigoPedido($con);
			$con->prepare('UPDATE pedidos SET codigo_pedido=? WHERE id_pedido=?')->execute([$codigoPedido, $idPedido]);

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

		$usuarioSth = $con->prepare('SELECT nombre_usuario_sistema, correo_usuario_sistema FROM sistema_usuarios WHERE id_usuario_sistema = ?');
		$usuarioSth->execute([$userId]);
		$usuario = $usuarioSth->fetch();
		$direccionSth = $con->prepare('SELECT direccion_envio_cliente, ciudad_envio_cliente, referencia_envio_cliente FROM direcciones_envio_clientes WHERE id_direccion_envio = ?');
		$direccionSth->execute([$idDireccion]);
		$direccionRegistrada = $direccionSth->fetch() ?: [];
		$correoEnviado = is_array($usuario) && Correo::enviarDetallePedido(
			(string) ($usuario['correo_usuario_sistema'] ?? ''),
			(string) ($usuario['nombre_usuario_sistema'] ?? ''),
			[
				'ref' => $codigoPedido,
				'total' => $total,
				'items' => array_map(static fn ($it) => [
					'nombre_producto' => $it['nombre_producto'],
					'talla' => $it['talla_variante'],
					'corte' => $it['corte_variante'],
					'cantidad' => (int) $it['cantidad_carrito_item'],
					'precio' => (float) $it['precio_producto'],
				], $items),
				'direccion' => $direccionRegistrada['direccion_envio_cliente'] ?? '',
				'ciudad' => $direccionRegistrada['ciudad_envio_cliente'] ?? '',
				'referencia' => $direccionRegistrada['referencia_envio_cliente'] ?? '',
				'entrega_estimada' => date('d/m/Y', strtotime('+5 days')),
				'codigo_entrega' => CodigoEntrega::abrir($codigoEntregaCifrado),
				'codigo_anulacion' => CodigoEntrega::abrir($codigoAnulacionCifrado),
				'marca_tarjeta' => $marcaTarjeta,
				'ultimos_digitos' => $ultimosDigitos,
			],
		);

		return [
			'status' => 'success',
			'correo_enviado' => $correoEnviado,
			'message' => $correoEnviado ? 'Pedido registrado y detalle enviado por correo.' : 'Pedido registrado. No pudimos enviar el detalle por correo; revisa la configuración SMTP.',
			'pedido' => [
				'id_pedido' => $idPedido,
				'ref' => $codigoPedido,
				'fecha' => date('Y-m-d H:i:s'),
				'estado' => 'pagado',
				'estado_pedido' => 'pagado',
				'total' => (float) $total,
				'codigo_entrega' => CodigoEntrega::abrir($codigoEntregaCifrado),
				'codigo_anulacion' => CodigoEntrega::abrir($codigoAnulacionCifrado),
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
			"SELECT p.id_pedido, p.codigo_pedido AS ref, p.estado_pedido, p.total_pedido, p.fecha_creacion,
			        p.subtotal_pedido, p.id_direccion_envio, p.transportista_pedido, p.entrega_estimada_pedido,
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
			$pedido['items'] = $itemsSth->fetchAll();
			foreach ($pedido['items'] as &$item) {
				$item['url_imagen'] = $item['url_imagen'] ? Productos::urlImagenPublica($item['url_imagen']) : null;
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
		$sth = $con->prepare('SELECT p.id_pedido, p.codigo_pedido AS ref, p.estado_pedido, p.subtotal_pedido, p.total_pedido, p.fecha_creacion, p.transportista_pedido, p.entrega_estimada_pedido, p.codigo_entrega_hash, p.codigo_entrega_cifrado, p.codigo_anulacion_hash, p.codigo_anulacion_cifrado, d.direccion_envio_cliente, d.ciudad_envio_cliente, d.referencia_envio_cliente, pg.marca_pago, pg.ultimos_digitos_pago FROM pedidos p INNER JOIN direcciones_envio_clientes d ON d.id_direccion_envio = p.id_direccion_envio LEFT JOIN pagos pg ON pg.id_pedido = p.id_pedido WHERE p.id_pedido = ? AND p.id_usuario_sistema = ?');
		$sth->execute([$id, $userId]);
		$pedido = $sth->fetch();
		if (!$pedido) {
			http_response_code(404);
			return ['status' => 'error', 'message' => 'Pedido no encontrado'];
		}
		if (in_array($pedido['estado_pedido'], ['pagado', 'en_preparacion', 'enviado', 'en_reparto', 'entregado', 'cancelado'], true)
			&& (!$pedido['codigo_entrega_hash'] || !$pedido['codigo_entrega_cifrado']
				|| !$pedido['codigo_anulacion_hash'] || !$pedido['codigo_anulacion_cifrado'])) {
			$con->beginTransaction();
			try {
				$lock = $con->prepare('SELECT estado_pedido,codigo_entrega_hash,codigo_entrega_cifrado,codigo_anulacion_hash,codigo_anulacion_cifrado FROM pedidos WHERE id_pedido=? AND id_usuario_sistema=? FOR UPDATE');
				$lock->execute([$id, $userId]);
				$codigos = $lock->fetch();
				if ($codigos && in_array($codigos['estado_pedido'], ['pagado', 'en_preparacion', 'enviado', 'en_reparto', 'entregado', 'cancelado'], true)) {
					foreach (['entrega', 'anulacion'] as $tipo) {
						if (!$codigos["codigo_{$tipo}_hash"] || !$codigos["codigo_{$tipo}_cifrado"]) {
							[$codigos["codigo_{$tipo}_hash"], $codigos["codigo_{$tipo}_cifrado"]] = CodigoEntrega::crear();
						}
					}
					$con->prepare('UPDATE pedidos SET codigo_entrega_hash=?,codigo_entrega_cifrado=?,codigo_anulacion_hash=?,codigo_anulacion_cifrado=? WHERE id_pedido=?')
						->execute([$codigos['codigo_entrega_hash'], $codigos['codigo_entrega_cifrado'], $codigos['codigo_anulacion_hash'], $codigos['codigo_anulacion_cifrado'], $id]);
					$pedido['estado_pedido'] = $codigos['estado_pedido'];
					$pedido['codigo_entrega_hash'] = $codigos['codigo_entrega_hash'];
					$pedido['codigo_entrega_cifrado'] = $codigos['codigo_entrega_cifrado'];
					$pedido['codigo_anulacion_hash'] = $codigos['codigo_anulacion_hash'];
					$pedido['codigo_anulacion_cifrado'] = $codigos['codigo_anulacion_cifrado'];
				}
				$con->commit();
			} catch (\Throwable $e) {
				if ($con->inTransaction()) $con->rollBack();
				throw $e;
			}
		}
		$pedido['codigo_entrega'] = in_array($pedido['estado_pedido'], ['pagado', 'en_preparacion', 'enviado', 'en_reparto'], true)
			? CodigoEntrega::abrir($pedido['codigo_entrega_cifrado'])
			: null;
		$pedido['codigo_anulacion'] = CodigoEntrega::abrir($pedido['codigo_anulacion_cifrado']);
		unset($pedido['codigo_entrega_hash'], $pedido['codigo_entrega_cifrado'], $pedido['codigo_anulacion_hash'], $pedido['codigo_anulacion_cifrado']);
		$sth = $con->prepare('SELECT pi.id_pedido_item,pi.id_variante,pi.cantidad_pedido_item,pi.precio_unitario_pedido_item, COALESCE(pi.talla_compra,v.talla_variante) AS talla_variante,COALESCE(pi.corte_compra,v.corte_variante) AS corte_variante,COALESCE(pi.nombre_color_compra,c.nombre_color) AS nombre_color,COALESCE(pi.sku_compra,v.sku_variante) AS sku_variante,pr.id_producto,COALESCE(pi.nombre_producto_compra,pr.nombre_producto) AS nombre_producto,pr.url_producto,(SELECT i.url_imagen FROM productos_imagenes i WHERE i.id_color=v.id_color ORDER BY i.principal_imagen DESC,i.orden_imagen,i.id_imagen LIMIT 1) AS url_imagen FROM pedidos_items pi INNER JOIN productos_variantes v ON v.id_variante=pi.id_variante INNER JOIN productos_colores c ON c.id_color=v.id_color INNER JOIN productos pr ON pr.id_producto=v.id_producto WHERE pi.id_pedido=?');
		$sth->execute([$id]);
		$pedido['items'] = $sth->fetchAll();
		foreach ($pedido['items'] as &$item) $item['url_imagen'] = $item['url_imagen'] ? Productos::urlImagenPublica($item['url_imagen']) : null;
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
		return ['status'=>'success','codigo'=>in_array($pedido['estado_pedido'], ['pagado','en_preparacion','enviado','en_reparto'], true) ? CodigoEntrega::abrir($pedido['codigo_entrega_cifrado']) : null];
	}

	public static function cancelar(): array
	{
		$userId = JwtHelper::bearerUserId();
		if (!$userId) { http_response_code(401); return ['status' => 'error', 'message' => 'No autorizado']; }
		$id = (int) ($_POST['id'] ?? 0);
		$con = Conexion::getInstance();
		$con->beginTransaction();
		try {
			$sth = $con->prepare('SELECT estado_pedido,codigo_anulacion_hash,codigo_anulacion_cifrado FROM pedidos WHERE id_pedido = ? AND id_usuario_sistema = ? FOR UPDATE');
			$sth->execute([$id, $userId]);
			$pedido = $sth->fetch();
			if (!$pedido) { $con->rollBack(); http_response_code(404); return ['status' => 'error', 'message' => 'Pedido no encontrado']; }
			$estado = $pedido['estado_pedido'];
			if ($estado === 'cancelado') {
				$con->commit();
				return ['status'=>'success','message'=>'El pedido ya estaba cancelado','codigo_anulacion'=>CodigoEntrega::abrir($pedido['codigo_anulacion_cifrado'])];
			}
			if (!in_array($estado, ['pagado', 'en_preparacion'], true)) {
				$con->rollBack(); http_response_code(422);
				return ['status' => 'error', 'message' => 'Este pedido ya no se puede cancelar desde tu cuenta.'];
			}
			[$codigoHash, $codigoCifrado] = CodigoEntrega::crear();
			if (!$pedido['codigo_anulacion_hash'] || !$pedido['codigo_anulacion_cifrado']) {
				[$codigoHash, $codigoCifrado] = CodigoEntrega::crear();
			} else {
				$codigoHash = $pedido['codigo_anulacion_hash'];
				$codigoCifrado = $pedido['codigo_anulacion_cifrado'];
			}
			$con->prepare("UPDATE pedidos SET estado_pedido = 'cancelado', codigo_anulacion_hash=?, codigo_anulacion_cifrado=?, codigo_anulacion_intentos=0, codigo_anulacion_usado_en=NULL WHERE id_pedido = ?")
				->execute([$codigoHash, $codigoCifrado, $id]);
			$con->prepare('UPDATE productos_variantes v INNER JOIN pedidos_items pi ON pi.id_variante = v.id_variante SET v.stock_variante = v.stock_variante + pi.cantidad_pedido_item WHERE pi.id_pedido = ?')->execute([$id]);
			$con->prepare(
				"INSERT INTO inventario_eventos (id_variante,tipo_evento,id_usuario_sistema)
				 SELECT pi.id_variante,'devolucion_cancelacion',? FROM pedidos_items pi WHERE pi.id_pedido=?"
			)->execute([$userId,$id]);
			Pagos::reembolsarPedido($con, $id);
			$con->prepare("INSERT INTO historial_estados_pedidos (id_pedido, id_usuario_sistema, estado_anterior_historial, estado_historial_estado_pedido, comentario_historial_estado_pedido) VALUES (?, ?, ?, 'cancelado', 'Cancelado por el cliente; reembolso simulado registrado')")->execute([$id, $userId, $estado]);
			$pref = $con->prepare('SELECT avisos_pedidos_usuario FROM sistema_usuarios WHERE id_usuario_sistema=?');
			$pref->execute([$userId]);
			if ($pref->fetchColumn()) $con->prepare('INSERT INTO notificaciones (id_usuario_sistema,id_pedido,mensaje_notificacion) VALUES (?, ?, ?)')->execute([$userId,$id,'Tu pedido fue cancelado y el pago simulado fue reembolsado.']);
			$con->commit();
			return ['status' => 'success', 'message' => 'Pedido cancelado y reembolso simulado registrado.', 'codigo_anulacion' => CodigoEntrega::abrir($codigoCifrado)];
		} catch (\Throwable $e) {
			$con->rollBack(); error_log('Kloset · cancelar pedido: ' . $e->getMessage());
			return ['status' => 'error', 'message' => 'No se pudo cancelar el pedido'];
		}
	}
}
