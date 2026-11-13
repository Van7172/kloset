<?php

namespace Develoweb\App\Model;

use Develoweb\App\Utilities\JwtHelper;
use Develoweb\App\Utilities\Correo;

final class Postventa
{
	private const ESTADOS = ['pendiente_revision', 'en_revision', 'aprobada', 'en_proceso', 'completada', 'rechazada', 'reembolso_en_proceso', 'reembolso_realizado', 'cambio_en_preparacion', 'cambio_enviado', 'cambio_en_reparto'];
	private const TIPOS = ['reembolso', 'cambio'];

	private static function notificar(\PDO $con, int $usuarioId, int $pedidoId, string $mensaje): void
	{
		$pref = $con->prepare('SELECT avisos_pedidos_usuario FROM sistema_usuarios WHERE id_usuario_sistema=?');
		$pref->execute([$usuarioId]);
		if ($pref->fetchColumn()) {
			$con->prepare('INSERT INTO notificaciones (id_usuario_sistema,id_pedido,mensaje_notificacion) VALUES (?,?,?)')
				->execute([$usuarioId, $pedidoId, $mensaje]);
		}
	}

	private static function usuarioId(): int
	{
		return (int) (JwtHelper::bearerUserId() ?: 0);
	}

	private static function guardarSoloAdministrador(): ?array
	{
		return (string) Acl::usuario()->getRol() === 'Administrador'
			? null
			: ['status' => 'error', 'message' => 'Solo el administrador puede gestionar incidencias'];
	}

	private static function validarItems(\PDO $con, int $usuarioId, int $pedidoId, array $ids): array
	{
		if ($pedidoId <= 0 || count($ids) < 1 || count($ids) > 20) {
			throw new \DomainException('Selecciona un pedido y al menos una prenda');
		}
		$placeholders = implode(',', array_fill(0, count($ids), '?'));
		$params = array_merge([$pedidoId, $usuarioId], $ids);
		$sth = $con->prepare(
			"SELECT pi.id_pedido_item, pi.id_variante, pi.cantidad_pedido_item,
			        pi.precio_unitario_pedido_item, pi.nombre_producto_compra, pi.nombre_color_compra,
			        pi.talla_compra, pi.corte_compra, pi.sku_compra, p.estado_pedido
			 FROM pedidos_items pi
			 INNER JOIN pedidos p ON p.id_pedido = pi.id_pedido
			 WHERE pi.id_pedido = ? AND p.id_usuario_sistema = ? AND pi.id_pedido_item IN ($placeholders)"
		);
		$sth->execute($params);
		$items = $sth->fetchAll();
		if (count($items) !== count(array_unique($ids))) throw new \DomainException('Una prenda no pertenece a ese pedido');
		foreach ($items as $item) {
			if ($item['estado_pedido'] === 'cancelado') throw new \DomainException('No se puede solicitar sobre un pedido cancelado');
		}
		return $items;
	}

	private static function guardarEvidencias(\PDO $con, int $idSolicitud): array
	{
		$files = $_FILES['evidencias'] ?? null;
		if (!$files || !isset($files['name'])) throw new \DomainException('Adjunta al menos una fotografía como evidencia');
		$names = is_array($files['name']) ? $files['name'] : [$files['name']];
		$tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
		$errors = is_array($files['error']) ? $files['error'] : [$files['error']];
		$sizes = is_array($files['size']) ? $files['size'] : [$files['size']];
		if (count($names) < 1) throw new \DomainException('Adjunta al menos una fotografía como evidencia');
		if (count($names) > 5) throw new \DomainException('Puedes adjuntar hasta 5 fotografías');
		$dir = HOST . 'app/public_root/uploads/postventa/';
		if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) throw new \RuntimeException('No se pudo preparar el almacenamiento de evidencias');
		$insert = $con->prepare('INSERT INTO solicitudes_postventa_evidencias (id_solicitud_postventa,ruta_evidencia,tipo_mime_evidencia) VALUES (?,?,?)');
		$rutas = [];
		foreach ($names as $i => $name) {
			if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($sizes[$i] ?? 0) < 1 || ($sizes[$i] ?? 0) > 5 * 1024 * 1024 || !is_uploaded_file($tmpNames[$i] ?? '')) {
				throw new \DomainException('Cada fotografía debe pesar menos de 5 MB');
			}
			$finfo = new \finfo(FILEINFO_MIME_TYPE);
			$mime = (string) $finfo->file($tmpNames[$i]);
			$extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
			if (!$extension) throw new \DomainException('Solo se aceptan fotografías JPG, PNG o WEBP');
			$filename = bin2hex(random_bytes(16)) . '.' . $extension;
			if (!move_uploaded_file($tmpNames[$i], $dir . $filename)) throw new \RuntimeException('No se pudo guardar una fotografía');
			$ruta = PUBLIC_ROOT . 'uploads/postventa/' . $filename;
			$insert->execute([$idSolicitud, $ruta, $mime]);
			$rutas[] = $dir . $filename;
		}
		return $rutas;
	}

	public static function crear(): array
	{
		return ['status' => 'error', 'message' => 'Las solicitudes postventa solo pueden registrarse desde el panel administrativo'];
		/*
		$usuarioId = self::usuarioId();
		if (!$usuarioId) return ['status' => 'error', 'message' => 'No autorizado'];
		$tipo = trim((string) ($_POST['tipo'] ?? ''));
		$pedidoId = (int) ($_POST['id_pedido'] ?? 0);
		$motivo = trim((string) ($_POST['motivo'] ?? ''));
		$descripcion = trim((string) ($_POST['descripcion'] ?? ''));
		$ids = json_decode((string) ($_POST['items'] ?? '[]'), true);
		$ids = is_array($ids) ? array_values(array_unique(array_filter(array_map('intval', $ids)))) : [];
		if (!in_array($tipo, self::TIPOS, true)) return ['status' => 'error', 'message' => 'Tipo de solicitud no válido'];
		if ($motivo === '' || mb_strlen($motivo) > 120 || mb_strlen($descripcion) < 10 || mb_strlen($descripcion) > 5000) {
			return ['status' => 'error', 'message' => 'Revisa el motivo y la descripción'];
		}

		$con = Conexion::getInstance();
		$archivosGuardados = [];
		try {
			$con->beginTransaction();
			$items = self::validarItems($con, $usuarioId, $pedidoId, $ids);
			if ($tipo === 'reembolso') {
				$codigo = preg_replace('/\D/', '', (string) ($_POST['codigo_cancelacion'] ?? ''));
				$pedidoSth = $con->prepare('SELECT codigo_anulacion_hash,codigo_anulacion_intentos FROM pedidos WHERE id_pedido=? AND id_usuario_sistema=? FOR UPDATE');
				$pedidoSth->execute([$pedidoId, $usuarioId]);
				$pedidoCodigo = $pedidoSth->fetch();
				if (!$pedidoCodigo || (int) $pedidoCodigo['codigo_anulacion_intentos'] >= 5) {
					throw new \DomainException('El código de cancelación está bloqueado; contacta soporte');
				}
				if (!preg_match('/^\d{8}$/', $codigo) || !$pedidoCodigo['codigo_anulacion_hash'] || !password_verify($codigo, $pedidoCodigo['codigo_anulacion_hash'])) {
					$con->prepare('UPDATE pedidos SET codigo_anulacion_intentos=codigo_anulacion_intentos+1 WHERE id_pedido=?')->execute([$pedidoId]);
					throw new \DomainException('El código de cancelación no coincide');
				}
			}
			$dup = $con->prepare(
				"SELECT 1 FROM solicitudes_postventa s
				 INNER JOIN solicitudes_postventa_items si ON si.id_solicitud_postventa=s.id_solicitud_postventa
				 WHERE s.id_usuario_sistema=? AND s.id_pedido=? AND si.id_pedido_item IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
				   AND s.estado_solicitud NOT IN ('completada','rechazada') LIMIT 1"
			);
			$dup->execute(array_merge([$usuarioId, $pedidoId], $ids));
			if ($dup->fetchColumn()) throw new \DomainException('Ya existe una solicitud activa para una de esas prendas');

			$con->prepare(
				'INSERT INTO solicitudes_postventa (id_usuario_sistema,id_pedido,tipo_solicitud,motivo_solicitud,descripcion_solicitud) VALUES (?,?,?,?,?)'
			)->execute([$usuarioId, $pedidoId, $tipo, $motivo, $descripcion]);
			$idSolicitud = (int) $con->lastInsertId();
			$insert = $con->prepare(
				'INSERT INTO solicitudes_postventa_items (id_solicitud_postventa,id_pedido_item,id_variante,cantidad,importe_item,nombre_producto_compra,nombre_color_compra,talla_compra,corte_compra,sku_compra) VALUES (?,?,?,?,?,?,?,?,?,?)'
			);
			foreach ($items as $item) {
				$importe = (float) $item['precio_unitario_pedido_item'] * (int) $item['cantidad_pedido_item'];
				$insert->execute([$idSolicitud, $item['id_pedido_item'], $item['id_variante'], $item['cantidad_pedido_item'], $importe, $item['nombre_producto_compra'], $item['nombre_color_compra'], $item['talla_compra'], $item['corte_compra'], $item['sku_compra']]);
			}
			$archivosGuardados = self::guardarEvidencias($con, $idSolicitud);
			$con->prepare('INSERT INTO historial_solicitudes_postventa (id_solicitud_postventa,id_usuario_sistema,estado_nuevo,comentario) VALUES (?,?,?,?)')->execute([$idSolicitud, $usuarioId, 'pendiente_revision', 'Solicitud registrada por el cliente']);
			self::notificar($con, $usuarioId, $pedidoId, 'Registramos tu solicitud de ' . ($tipo === 'reembolso' ? 'reembolso' : 'cambio de prenda') . '. Está pendiente de revisión.');
			$con->commit();
			return ['status' => 'success', 'id_solicitud' => $idSolicitud, 'message' => 'Solicitud registrada para revisión'];
		} catch (\DomainException $e) {
			if ($con->inTransaction()) $con->rollBack();
			foreach ($archivosGuardados as $archivo) if (is_file($archivo)) @unlink($archivo);
			return ['status' => 'error', 'message' => $e->getMessage()];
		} catch (\Throwable $e) {
			if ($con->inTransaction()) $con->rollBack();
			foreach ($archivosGuardados as $archivo) if (is_file($archivo)) @unlink($archivo);
			error_log('Kloset · postventa crear: ' . $e->getMessage());
			return ['status' => 'error', 'message' => 'No se pudo registrar la solicitud'];
		}
		*/
	}

	public static function registrarAdmin(): array
	{
		if ($error = Acl::guard('pedidos')) return $error;
		if ($error = self::guardarSoloAdministrador()) return $error;
		$adminId = (int) Acl::usuario()->getId();
		$pedidoId = (int) ($_POST['id_pedido'] ?? 0);
		$tipo = trim((string) ($_POST['tipo'] ?? ''));
		$motivo = trim((string) ($_POST['motivo'] ?? ''));
		$descripcion = trim((string) ($_POST['descripcion'] ?? ''));
		$codigoCancelacion = preg_replace('/\D/', '', (string) ($_POST['codigo_cancelacion'] ?? ''));
		$ids = json_decode((string) ($_POST['items'] ?? '[]'), true);
		$ids = is_array($ids) ? array_values(array_unique(array_filter(array_map('intval', $ids)))) : [];
		if ($pedidoId <= 0 || !in_array($tipo, self::TIPOS, true) || !$ids) {
			return ['status' => 'error', 'message' => 'Selecciona el pedido, la solución y al menos un producto'];
		}
		if ($motivo === '' || mb_strlen($motivo) > 120 || mb_strlen($descripcion) < 10 || mb_strlen($descripcion) > 5000) {
			return ['status' => 'error', 'message' => 'Revisa el motivo y la descripción'];
		}

		$con = Conexion::getInstance();
		$archivosGuardados = [];
		try {
			$con->beginTransaction();
			$pedidoSth = $con->prepare('SELECT id_usuario_sistema, estado_pedido FROM pedidos WHERE id_pedido=? FOR UPDATE');
			$pedidoSth->execute([$pedidoId]);
			$pedido = $pedidoSth->fetch();
			if (!$pedido) throw new \DomainException('Pedido no encontrado');
			$existente = $con->prepare('SELECT id_solicitud_postventa FROM solicitudes_postventa WHERE id_pedido=? LIMIT 1');
			$existente->execute([$pedidoId]);
			if ($existente->fetchColumn()) {
				throw new \DomainException('El pedido ya cuenta con una incidencia registrada');
			}
			if ($pedido['estado_pedido'] !== 'en_reparto') {
				throw new \DomainException('Solo se puede registrar una incidencia cuando el pedido está en reparto');
			}
			$items = self::validarItems($con, (int) $pedido['id_usuario_sistema'], $pedidoId, $ids);
			if ($tipo === 'reembolso') {
				$codigoSth = $con->prepare('SELECT codigo_anulacion_hash,codigo_anulacion_intentos FROM pedidos WHERE id_pedido=? FOR UPDATE');
				$codigoSth->execute([$pedidoId]);
				$codigo = $codigoSth->fetch();
				if (!$codigo || (int) $codigo['codigo_anulacion_intentos'] >= 5) {
					throw new \DomainException('El código de cancelación está bloqueado; contacta soporte');
				}
				if (!preg_match('/^\d{8}$/', $codigoCancelacion) || !$codigo['codigo_anulacion_hash'] || !password_verify($codigoCancelacion, $codigo['codigo_anulacion_hash'])) {
					$con->prepare('UPDATE pedidos SET codigo_anulacion_intentos=codigo_anulacion_intentos+1 WHERE id_pedido=?')->execute([$pedidoId]);
					$con->commit();
					return ['status' => 'error', 'message' => 'El código de cancelación no coincide'];
				}
			}
			$con->prepare('INSERT INTO solicitudes_postventa (id_usuario_sistema,id_pedido,tipo_solicitud,motivo_solicitud,descripcion_solicitud) VALUES (?,?,?,?,?)')
				->execute([(int) $pedido['id_usuario_sistema'], $pedidoId, $tipo, $motivo, $descripcion]);
			$idSolicitud = (int) $con->lastInsertId();
			$insert = $con->prepare('INSERT INTO solicitudes_postventa_items (id_solicitud_postventa,id_pedido_item,id_variante,cantidad,importe_item,nombre_producto_compra,nombre_color_compra,talla_compra,corte_compra,sku_compra) VALUES (?,?,?,?,?,?,?,?,?,?)');
			foreach ($items as $item) {
				$importe = (float) $item['precio_unitario_pedido_item'] * (int) $item['cantidad_pedido_item'];
				$insert->execute([$idSolicitud, $item['id_pedido_item'], $item['id_variante'], $item['cantidad_pedido_item'], $importe, $item['nombre_producto_compra'], $item['nombre_color_compra'], $item['talla_compra'], $item['corte_compra'], $item['sku_compra']]);
			}
			$archivosGuardados = self::guardarEvidencias($con, $idSolicitud);
			$con->prepare('INSERT INTO historial_solicitudes_postventa (id_solicitud_postventa,id_usuario_sistema,estado_nuevo,comentario) VALUES (?,?,?,?)')
				->execute([$idSolicitud, $adminId, 'pendiente_revision', 'Incidencia registrada desde el detalle del pedido']);
			self::notificar($con, (int) $pedido['id_usuario_sistema'], $pedidoId, 'Registramos tu incidencia. Está pendiente de revisión.');
			$con->commit();
			return ['status' => 'success', 'id_solicitud' => $idSolicitud, 'message' => 'Incidencia registrada en Devoluciones e incidencias'];
		} catch (\DomainException $e) {
			if ($con->inTransaction()) $con->rollBack();
			foreach ($archivosGuardados as $archivo) if (is_file($archivo)) @unlink($archivo);
			return ['status' => 'error', 'message' => $e->getMessage()];
		} catch (\Throwable $e) {
			if ($con->inTransaction()) $con->rollBack();
			foreach ($archivosGuardados as $archivo) if (is_file($archivo)) @unlink($archivo);
			error_log('Kloset · postventa registrar admin: ' . $e->getMessage());
			return ['status' => 'error', 'message' => 'No se pudo registrar la incidencia'];
		}
	}

	public static function mias(): array
	{
		$usuarioId = self::usuarioId();
		if (!$usuarioId) return ['status' => 'error', 'message' => 'No autorizado'];
		$sth = Conexion::getInstance()->prepare(
			'SELECT s.id_solicitud_postventa,s.id_pedido,p.codigo_pedido,s.tipo_solicitud,s.estado_solicitud,s.motivo_solicitud,s.importe_aprobado,s.fecha_creacion,s.fecha_actualizacion,pg.metodo_pago,pg.marca_pago
			 FROM solicitudes_postventa s INNER JOIN pedidos p ON p.id_pedido=s.id_pedido LEFT JOIN pagos pg ON pg.id_pedido=s.id_pedido
			 WHERE s.id_usuario_sistema=? ORDER BY s.fecha_creacion DESC'
		);
		$sth->execute([$usuarioId]);
		return ['status' => 'success', 'solicitudes' => $sth->fetchAll()];
	}

	public static function detalleMio(): array
	{
		$usuarioId = self::usuarioId();
		$id = (int) ($_GET['id'] ?? 0);
		if (!$usuarioId || $id <= 0) return ['status' => 'error', 'message' => 'Solicitud no válida'];
		$con = Conexion::getInstance();
		$sth = $con->prepare(
			'SELECT s.*,p.codigo_pedido,pg.metodo_pago,pg.marca_pago FROM solicitudes_postventa s INNER JOIN pedidos p ON p.id_pedido=s.id_pedido LEFT JOIN pagos pg ON pg.id_pedido=s.id_pedido WHERE s.id_solicitud_postventa=? AND s.id_usuario_sistema=?'
		);
		$sth->execute([$id, $usuarioId]);
		$solicitud = $sth->fetch();
		if (!$solicitud) return ['status' => 'error', 'message' => 'Solicitud no encontrada'];
		$solicitud['codigo_confirmacion'] = $solicitud['estado_solicitud'] === 'cambio_en_reparto'
			? CodigoEntrega::abrir($solicitud['codigo_confirmacion_cifrado'] ?? null)
			: null;
		$codigoConfirmacion = $solicitud['codigo_confirmacion'];
		unset($solicitud['codigo_confirmacion_hash'], $solicitud['codigo_confirmacion_cifrado'], $solicitud['codigo_confirmacion_intentos'], $solicitud['codigo_confirmacion_usado_en']);
		$items = $con->prepare('SELECT * FROM solicitudes_postventa_items WHERE id_solicitud_postventa=? ORDER BY id_item_postventa');
		$items->execute([$id]);
		$cambios = $con->prepare('SELECT c.* FROM cambios_postventa c WHERE c.id_solicitud_postventa=?');
		$cambios->execute([$id]);
		$cambiosData = $cambios->fetchAll();
		foreach ($cambiosData as &$cambio) {
			$cambio['codigo_confirmacion'] = $codigoConfirmacion;
			unset($cambio['codigo_confirmacion_hash'], $cambio['codigo_confirmacion_cifrado']);
		}
		$historial = $con->prepare('SELECT estado_anterior,estado_nuevo,comentario,fecha_creacion FROM historial_solicitudes_postventa WHERE id_solicitud_postventa=? ORDER BY fecha_creacion');
		$historial->execute([$id]);
		$evidencias = $con->prepare('SELECT ruta_evidencia,tipo_mime_evidencia,fecha_creacion FROM solicitudes_postventa_evidencias WHERE id_solicitud_postventa=? ORDER BY fecha_creacion');
		$evidencias->execute([$id]);
		return ['status' => 'success', 'solicitud' => $solicitud, 'items' => $items->fetchAll(), 'cambios' => $cambiosData, 'historial' => $historial->fetchAll(), 'evidencias' => $evidencias->fetchAll()];
	}

	public static function detalle(): array
	{
		if ($error = Acl::guard('postventa')) return $error;
		$id = (int) ($_POST['id'] ?? 0);
		if ($id <= 0) return ['status' => 'error', 'message' => 'Solicitud no válida'];
		$con = Conexion::getInstance();
		$sth = $con->prepare(
			'SELECT s.*,p.codigo_pedido,u.nombre_usuario_sistema AS cliente,u.correo_usuario_sistema AS correo FROM solicitudes_postventa s INNER JOIN pedidos p ON p.id_pedido=s.id_pedido INNER JOIN sistema_usuarios u ON u.id_usuario_sistema=s.id_usuario_sistema WHERE s.id_solicitud_postventa=?'
		);
		$sth->execute([$id]);
		$solicitud = $sth->fetch();
		if (!$solicitud) return ['status' => 'error', 'message' => 'Solicitud no encontrada'];
		unset($solicitud['codigo_confirmacion_hash'], $solicitud['codigo_confirmacion_cifrado'], $solicitud['codigo_confirmacion_intentos'], $solicitud['codigo_confirmacion_usado_en']);
		$items = $con->prepare(
			'SELECT si.*,v.id_producto AS producto_original,
			        p.nombre_producto AS producto_reemplazo,
			        c.nombre_color AS color_reemplazo,
			        v.talla_variante AS talla_reemplazo,v.corte_variante AS corte_reemplazo
			 FROM solicitudes_postventa_items si
			 INNER JOIN productos_variantes v ON v.id_variante=si.id_variante
			 INNER JOIN productos p ON p.id_producto=v.id_producto
			 INNER JOIN productos_colores c ON c.id_color=v.id_color
			 WHERE si.id_solicitud_postventa=? ORDER BY si.id_item_postventa'
		);
		$items->execute([$id]);
		$itemsData = $items->fetchAll();
		$opciones = [];
		$variantes = $con->prepare(
			'SELECT v.id_variante,v.id_producto,v.id_color,v.talla_variante,v.corte_variante,v.sku_variante,
			        v.stock_variante,c.nombre_color,p.nombre_producto
			 FROM productos_variantes v
			 INNER JOIN productos_colores c ON c.id_color=v.id_color
			 INNER JOIN productos p ON p.id_producto=v.id_producto
			 WHERE v.id_producto=? ORDER BY c.nombre_color,v.talla_variante,v.corte_variante'
		);
		foreach ($itemsData as $item) {
			$variantes->execute([(int) $item['producto_original']]);
			$opciones[$item['id_item_postventa']] = $variantes->fetchAll();
		}
		$cambios = $con->prepare('SELECT * FROM cambios_postventa WHERE id_solicitud_postventa=? ORDER BY id_cambio_postventa');
		$cambios->execute([$id]);
		$cambiosData = $cambios->fetchAll();
		foreach ($cambiosData as &$cambio) {
			$cambio['codigo_confirmacion'] = null;
			unset($cambio['codigo_confirmacion_hash'], $cambio['codigo_confirmacion_cifrado']);
		}
		$historial = $con->prepare('SELECT h.*,u.nombre_usuario_sistema AS autor FROM historial_solicitudes_postventa h LEFT JOIN sistema_usuarios u ON u.id_usuario_sistema=h.id_usuario_sistema WHERE h.id_solicitud_postventa=? ORDER BY h.fecha_creacion');
		$historial->execute([$id]);
		$evidencias = $con->prepare('SELECT * FROM solicitudes_postventa_evidencias WHERE id_solicitud_postventa=? ORDER BY fecha_creacion');
		$evidencias->execute([$id]);
		return ['status' => 'success', 'solicitud' => $solicitud, 'items' => $itemsData, 'opciones_reemplazo' => $opciones, 'cambios' => $cambiosData, 'historial' => $historial->fetchAll(), 'evidencias' => $evidencias->fetchAll()];
	}

	public static function get(): array
	{
		if ($error = Acl::guard('postventa')) return $error;
		$con = Conexion::getInstance();
		$sth = $con->query(
			'SELECT s.id_solicitud_postventa,s.id_pedido,p.codigo_pedido,s.tipo_solicitud,s.estado_solicitud,s.motivo_solicitud,s.descripcion_solicitud,s.importe_aprobado,s.motivo_rechazo,s.fecha_creacion,s.fecha_actualizacion,u.nombre_usuario_sistema AS cliente,u.correo_usuario_sistema AS correo
			 FROM solicitudes_postventa s INNER JOIN pedidos p ON p.id_pedido=s.id_pedido INNER JOIN sistema_usuarios u ON u.id_usuario_sistema=s.id_usuario_sistema
			 ORDER BY s.fecha_creacion DESC'
		);
		return ['status' => 'success', 'solicitudes' => $sth->fetchAll()];
	}

	public static function cambiarEstado(): array
	{
		if ($error = Acl::guard('postventa')) return $error;
		if ($error = self::guardarSoloAdministrador()) return $error;
		$id = (int) ($_POST['id'] ?? 0);
		$estado = trim((string) ($_POST['estado'] ?? ''));
		$comentario = trim((string) ($_POST['comentario'] ?? ''));
		$rechazo = trim((string) ($_POST['motivo_rechazo'] ?? ''));
		$codigo = preg_replace('/\D/', '', (string) ($_POST['codigo_confirmacion'] ?? ''));
		$reemplazos = json_decode((string) ($_POST['reemplazos'] ?? '{}'), true);
		$reemplazos = is_array($reemplazos) ? array_map('intval', $reemplazos) : [];
		if ($id <= 0 || !in_array($estado, self::ESTADOS, true)) return ['status' => 'error', 'message' => 'Estado no válido'];
		if ($estado === 'rechazada' && mb_strlen($rechazo) < 10) return ['status' => 'error', 'message' => 'Indica el motivo del rechazo'];
		$con = Conexion::getInstance();
		try {
			$con->beginTransaction();
			$sth = $con->prepare('SELECT * FROM solicitudes_postventa WHERE id_solicitud_postventa=? FOR UPDATE');
			$sth->execute([$id]);
			$solicitud = $sth->fetch();
			if (!$solicitud) throw new \DomainException('Solicitud no encontrada');
			if (in_array($solicitud['estado_solicitud'], ['completada', 'rechazada'], true)) {
				throw new \DomainException('La solicitud ya fue resuelta');
			}
			if ($estado === 'rechazada' && $solicitud['estado_solicitud'] !== 'en_revision') {
				throw new \DomainException('Solo se puede rechazar una solicitud que está en revisión');
			}
			$transiciones = $solicitud['tipo_solicitud'] === 'cambio'
				? [
					'pendiente_revision' => ['en_revision'],
					'en_revision' => ['aprobada', 'rechazada'],
					'aprobada' => ['cambio_en_preparacion'],
					'cambio_en_preparacion' => ['cambio_enviado'],
					'cambio_enviado' => ['cambio_en_reparto'],
					'cambio_en_reparto' => ['completada'],
				]
				: [
					'pendiente_revision' => ['en_revision'],
					'en_revision' => ['aprobada', 'rechazada'],
					'aprobada' => ['reembolso_en_proceso'],
					'reembolso_en_proceso' => ['reembolso_realizado'],
					'reembolso_realizado' => ['completada'],
				];
			if (!in_array($estado, $transiciones[$solicitud['estado_solicitud']] ?? [], true)) {
				throw new \DomainException('La transición solicitada no está permitida');
			}
			if ($estado === 'aprobada' && $solicitud['tipo_solicitud'] === 'cambio') {
				$item = $con->prepare('SELECT id_item_postventa,id_variante,cantidad FROM solicitudes_postventa_items WHERE id_solicitud_postventa=?');
				$item->execute([$id]);
				$stock = $con->prepare('SELECT stock_variante FROM productos_variantes WHERE id_variante=? FOR UPDATE');
				$existentes = $con->prepare('SELECT id_item_postventa,id_variante_original,id_variante_reemplazo,cantidad FROM cambios_postventa WHERE id_solicitud_postventa=? FOR UPDATE');
				$existentes->execute([$id]);
				$mapaExistentes = [];
				while ($existente = $existentes->fetch()) $mapaExistentes[(int) $existente['id_item_postventa']] = $existente;
				$lineas = [];
				while ($linea = $item->fetch()) $lineas[] = $linea;
				if ($mapaExistentes && count($mapaExistentes) !== count($lineas)) {
					throw new \DomainException('Integridad inconsistente: cambios parciales registrados');
				}
				$insertCambio = $con->prepare('INSERT INTO cambios_postventa (id_solicitud_postventa,id_item_postventa,id_variante_original,id_variante_reemplazo,cantidad) VALUES (?,?,?,?,?)');
				$movimiento = $con->prepare("INSERT INTO inventario_eventos (id_variante,tipo_evento,id_usuario_sistema) VALUES (?,'salida_cambio',?)");
				foreach ($lineas as $linea) {
					$idReemplazo = (int) ($reemplazos[(string) $linea['id_item_postventa']] ?? $reemplazos[(int) $linea['id_item_postventa']] ?? 0);
					if ($idReemplazo <= 0) throw new \DomainException('Selecciona una variante de reemplazo para cada producto');
					$existente = $mapaExistentes[(int) $linea['id_item_postventa']] ?? null;
					if ($existente) {
						if ((int) $existente['id_variante_original'] !== (int) $linea['id_variante'] || (int) $existente['id_variante_reemplazo'] !== $idReemplazo || (int) $existente['cantidad'] !== (int) $linea['cantidad']) {
							throw new \DomainException('Integridad inconsistente: cambio registrado con datos diferentes');
						}
						continue;
					}
					$validarReemplazo = $con->prepare('SELECT v.stock_variante FROM productos_variantes v INNER JOIN productos_variantes vo ON vo.id_variante=? WHERE v.id_variante=? AND v.id_producto=vo.id_producto FOR UPDATE');
					$validarReemplazo->execute([(int) $linea['id_variante'], $idReemplazo]);
					$stockDisponible = $validarReemplazo->fetchColumn();
					if ($stockDisponible === false) throw new \DomainException('La variante de reemplazo no pertenece al mismo producto');
					$stock->execute([$idReemplazo]);
					if ((int) $stock->fetchColumn() < (int) $linea['cantidad']) throw new \DomainException('No hay stock para aprobar uno de los cambios');
					$insertCambio->execute([$id, $linea['id_item_postventa'], $linea['id_variante'], $idReemplazo, $linea['cantidad']]);
					$descontar = $con->prepare('UPDATE productos_variantes SET stock_variante=stock_variante-? WHERE id_variante=? AND stock_variante>=?');
					$descontar->execute([(int) $linea['cantidad'], $idReemplazo, (int) $linea['cantidad']]);
					if ($descontar->rowCount() !== 1) throw new \DomainException('El stock cambió mientras se aprobaba el cambio');
					$movimiento->execute([$idReemplazo, Acl::usuario()->getId()]);
				}

			}
			if ($estado === 'aprobada' && $solicitud['tipo_solicitud'] === 'reembolso') {
				$importe = $con->prepare('SELECT COALESCE(SUM(importe_item),0) FROM solicitudes_postventa_items WHERE id_solicitud_postventa=?');
				$importe->execute([$id]);
				$importeAprobado = (float) $importe->fetchColumn();
				if ($importeAprobado <= 0) throw new \DomainException('La solicitud no tiene productos con importe válido');
				$con->prepare('UPDATE solicitudes_postventa SET importe_aprobado=? WHERE id_solicitud_postventa=? AND importe_aprobado IS NULL')
					->execute([$importeAprobado, $id]);
				$solicitud['importe_aprobado'] = $importeAprobado;
			}
			if ($estado === 'cambio_en_preparacion' && $solicitud['tipo_solicitud'] === 'cambio') {
				$con->prepare("UPDATE cambios_postventa SET estado_cambio='en_proceso',fecha_preparacion=NOW() WHERE id_solicitud_postventa=?")
					->execute([$id]);
			}
			if ($estado === 'cambio_en_reparto' && $solicitud['tipo_solicitud'] === 'cambio') {
				if (!$solicitud['codigo_confirmacion_hash'] || !$solicitud['codigo_confirmacion_cifrado']) {
					$pedidoCodigo = $con->prepare('SELECT codigo_entrega_cifrado FROM pedidos WHERE id_pedido=?');
					$pedidoCodigo->execute([(int) $solicitud['id_pedido']]);
					$codigoOriginal = CodigoEntrega::abrir($pedidoCodigo->fetchColumn() ?: null);
					do {
						[$hash, $cifrado] = CodigoEntrega::crear();
						$codigoNuevo = CodigoEntrega::abrir($cifrado);
					} while ($codigoOriginal !== null && $codigoNuevo === $codigoOriginal);
					$con->prepare('UPDATE solicitudes_postventa SET codigo_confirmacion_hash=?,codigo_confirmacion_cifrado=?,codigo_confirmacion_intentos=0 WHERE id_solicitud_postventa=?')
						->execute([$hash, $cifrado, $id]);
				}
				$con->prepare('UPDATE cambios_postventa SET fecha_reparto=NOW() WHERE id_solicitud_postventa=? AND fecha_reparto IS NULL')->execute([$id]);
			}
			if ($estado === 'completada' && $solicitud['tipo_solicitud'] === 'cambio') {
				if (!preg_match('/^\d{8}$/', $codigo)) throw new \DomainException('Introduce el código de recepción del cambio');
				if ((int) $solicitud['codigo_confirmacion_intentos'] >= 5) throw new \DomainException('Código de recepción bloqueado; contacta soporte');
				if (!$solicitud['codigo_confirmacion_hash'] || !password_verify($codigo, $solicitud['codigo_confirmacion_hash'])) {
					$con->prepare('UPDATE solicitudes_postventa SET codigo_confirmacion_intentos=codigo_confirmacion_intentos+1 WHERE id_solicitud_postventa=?')->execute([$id]);
					throw new \DomainException('El código de recepción del cambio no coincide');
				}
				$con->prepare('UPDATE solicitudes_postventa SET codigo_confirmacion_usado_en=NOW() WHERE id_solicitud_postventa=? AND codigo_confirmacion_usado_en IS NULL')->execute([$id]);
				$con->prepare('UPDATE cambios_postventa SET estado_cambio="completado",fecha_completado=NOW() WHERE id_solicitud_postventa=? AND estado_cambio<>"completado"')->execute([$id]);
			}
			if ($estado === 'reembolso_en_proceso' && $solicitud['tipo_solicitud'] === 'reembolso') {
				Pagos::procesarReembolsoPostventa($con, $id, (float) $solicitud['importe_aprobado']);
			}
			if ($estado === 'completada' && $solicitud['tipo_solicitud'] === 'reembolso') {
				$reembolso = $con->prepare('SELECT referencia_reembolso FROM reembolsos_postventa WHERE id_solicitud_postventa=? AND estado_reembolso="completado" FOR UPDATE');
				$reembolso->execute([$id]);
				if (!$reembolso->fetchColumn()) throw new \DomainException('El reembolso aún no está registrado en el simulador');
			}
			if ($estado === 'reembolso_realizado' && $solicitud['tipo_solicitud'] === 'reembolso') {
				$reembolso = $con->prepare('SELECT referencia_reembolso FROM reembolsos_postventa WHERE id_solicitud_postventa=? AND estado_reembolso="en_proceso" FOR UPDATE');
				$reembolso->execute([$id]);
				if (!$reembolso->fetchColumn()) throw new \DomainException('El reembolso aún no está en proceso');
				$con->prepare("UPDATE reembolsos_postventa SET estado_reembolso='completado',fecha_completado=NOW() WHERE id_solicitud_postventa=? AND estado_reembolso='en_proceso'")->execute([$id]);
			}
			$con->prepare('UPDATE solicitudes_postventa SET estado_solicitud=?,motivo_rechazo=?,id_admin_resolucion=?,fecha_resolucion=IF(? IN ("aprobada","rechazada"),NOW(),fecha_resolucion) WHERE id_solicitud_postventa=?')
				->execute([$estado, $estado === 'rechazada' ? $rechazo : null, Acl::usuario()->getId(), $estado, $id]);
			$con->prepare('INSERT INTO historial_solicitudes_postventa (id_solicitud_postventa,id_usuario_sistema,estado_anterior,estado_nuevo,comentario) VALUES (?,?,?,?,?)')->execute([$id, Acl::usuario()->getId(), $solicitud['estado_solicitud'], $estado, $comentario !== '' ? $comentario : null]);
			$mensajes = [
				'en_revision' => 'Tu solicitud postventa está en revisión.',
				'aprobada' => $solicitud['tipo_solicitud'] === 'cambio' ? 'Aprobamos tu cambio de prenda.' : 'Aprobamos tu solicitud de reembolso.',
				'rechazada' => 'Tu solicitud postventa fue rechazada: ' . $rechazo,
				'cambio_en_preparacion' => 'Comenzamos a preparar tu prenda de reemplazo.',
				'cambio_en_reparto' => 'Tu prenda de reemplazo salió a reparto.',
				'completada' => $solicitud['tipo_solicitud'] === 'cambio' ? 'Completamos tu cambio de prenda.' : 'Completamos tu reembolso.',
				'reembolso_en_proceso' => 'Tu reembolso está en proceso.',
				'reembolso_realizado' => 'El reembolso fue procesado.',
			];
			if (isset($mensajes[$estado])) self::notificar($con, (int) $solicitud['id_usuario_sistema'], (int) $solicitud['id_pedido'], $mensajes[$estado]);
			if ($solicitud['tipo_solicitud'] === 'reembolso' && $estado === 'aprobada') {
				$reembolso = $con->prepare('SELECT importe_reembolso FROM reembolsos_postventa WHERE id_solicitud_postventa=? FOR UPDATE');
				$reembolso->execute([$id]);
				$importeExistente = $reembolso->fetchColumn();
				if ($importeExistente !== false) {
					if ((float) $importeExistente !== (float) $solicitud['importe_aprobado']) {
						throw new \DomainException('Integridad inconsistente: el importe del reembolso no coincide');
					}
				} else {
					$con->prepare('INSERT INTO reembolsos_postventa (id_solicitud_postventa,importe_reembolso) VALUES (?,?)')
						->execute([$id, $solicitud['importe_aprobado']]);
				}
			}
			$con->commit();
			if ($estado === 'aprobada' && $solicitud['tipo_solicitud'] === 'cambio') {
				$cliente = $con->prepare('SELECT u.nombre_usuario_sistema,u.correo_usuario_sistema,p.codigo_pedido FROM solicitudes_postventa s INNER JOIN sistema_usuarios u ON u.id_usuario_sistema=s.id_usuario_sistema INNER JOIN pedidos p ON p.id_pedido=s.id_pedido WHERE s.id_solicitud_postventa=?');
				$cliente->execute([$id]);
				$datos = $cliente->fetch();
				if ($datos) Correo::enviarAprobacionCambio((string) $datos['correo_usuario_sistema'], (string) $datos['nombre_usuario_sistema'], ['codigo_pedido' => $datos['codigo_pedido']]);
			}
			return ['status' => 'success', 'message' => 'Estado actualizado'];
		} catch (\DomainException $e) {
			if ($con->inTransaction()) $con->rollBack();
			return ['status' => 'error', 'message' => $e->getMessage()];
		} catch (\Throwable $e) {
			if ($con->inTransaction()) $con->rollBack();
			error_log('Kloset · postventa estado: ' . $e->getMessage());
			return ['status' => 'error', 'message' => 'No se pudo actualizar la solicitud'];
		}
	}

	public static function cambiarSolucion(): array
	{
		if ($error = Acl::guard('postventa')) return $error;
		if ($error = self::guardarSoloAdministrador()) return $error;
		$id = (int) ($_POST['id'] ?? 0);
		$motivo = trim((string) ($_POST['motivo'] ?? ''));
		if ($id <= 0 || mb_strlen($motivo) < 10 || mb_strlen($motivo) > 1000) {
			return ['status' => 'error', 'message' => 'Indica un motivo de al menos 10 caracteres'];
		}
		$con = Conexion::getInstance();
		try {
			$con->beginTransaction();
			$sth = $con->prepare('SELECT * FROM solicitudes_postventa WHERE id_solicitud_postventa=? FOR UPDATE');
			$sth->execute([$id]);
			$solicitud = $sth->fetch();
			if (!$solicitud || $solicitud['tipo_solicitud'] !== 'cambio') throw new \DomainException('La incidencia no es un cambio');
			if (!in_array($solicitud['estado_solicitud'], ['aprobada', 'cambio_en_preparacion'], true)) {
				throw new \DomainException('Solo se puede cambiar la solución antes del envío');
			}
			$cambios = $con->prepare('SELECT id_variante_reemplazo,cantidad FROM cambios_postventa WHERE id_solicitud_postventa=?');
			$cambios->execute([$id]);
			$devolucion = $con->prepare("INSERT INTO inventario_eventos (id_variante,tipo_evento,id_usuario_sistema) VALUES (?,'entrada_cambio_cancelado',?)");
			foreach ($cambios->fetchAll() as $cambio) {
				$con->prepare('UPDATE productos_variantes SET stock_variante=stock_variante+? WHERE id_variante=?')
					->execute([(int) $cambio['cantidad'], (int) $cambio['id_variante_reemplazo']]);
				$devolucion->execute([(int) $cambio['id_variante_reemplazo'], Acl::usuario()->getId()]);
			}
			$importe = $con->prepare('SELECT COALESCE(SUM(importe_item),0) FROM solicitudes_postventa_items WHERE id_solicitud_postventa=?');
			$importe->execute([$id]);
			$importeAprobado = (float) $importe->fetchColumn();
			if ($importeAprobado <= 0) throw new \DomainException('La incidencia no tiene un importe válido');
			$con->prepare(
				"UPDATE solicitudes_postventa
				 SET tipo_solicitud='reembolso',estado_solicitud='aprobada',importe_aprobado=?,motivo_rechazo=NULL,
				     id_admin_resolucion=?,fecha_resolucion=NOW()
				 WHERE id_solicitud_postventa=?"
			)->execute([$importeAprobado, Acl::usuario()->getId(), $id]);
			$con->prepare('INSERT INTO reembolsos_postventa (id_solicitud_postventa,importe_reembolso) VALUES (?,?)')->execute([$id, $importeAprobado]);
			$con->prepare('INSERT INTO historial_solicitudes_postventa (id_solicitud_postventa,id_usuario_sistema,estado_anterior,estado_nuevo,comentario) VALUES (?,?,?,?,?)')
				->execute([$id, Acl::usuario()->getId(), $solicitud['estado_solicitud'], 'aprobada', 'Cambio de solución a reembolso: ' . $motivo]);
			$con->commit();
			return ['status' => 'success', 'message' => 'La incidencia continuará como reembolso'];
		} catch (\DomainException $e) {
			if ($con->inTransaction()) $con->rollBack();
			return ['status' => 'error', 'message' => $e->getMessage()];
		} catch (\Throwable $e) {
			if ($con->inTransaction()) $con->rollBack();
			error_log('KLOSET · postventa cambiar solución: ' . $e->getMessage());
			return ['status' => 'error', 'message' => 'No se pudo cambiar la solución'];
		}
	}
}
