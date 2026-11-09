<?php

namespace Develoweb\App\Model;

use Develoweb\App\Utilities\Correo;

class Notificaciones
{
	private const SECCION = 'notificaciones';

	public static function alertas(): array
	{
		$con = Conexion::getInstance();
		$umbral = Dashboard::umbralStock();
		$sth = $con->prepare('SELECT v.id_variante,v.stock_variante,v.sku_variante,p.nombre_producto,c.nombre_color FROM productos_variantes v JOIN productos p ON p.id_producto=v.id_producto JOIN productos_colores c ON c.id_color=v.id_color WHERE v.stock_variante<? ORDER BY v.stock_variante,v.id_variante LIMIT 30');
		$sth->execute([$umbral]);
		$stock = $sth->fetchAll();
		$cancelaciones = $con->query("SELECT id_pedido,fecha_creacion FROM historial_estados_pedidos WHERE estado_historial_estado_pedido='cancelado' ORDER BY fecha_creacion DESC LIMIT 10")->fetchAll();
		return ['stock'=>$stock,'cancelaciones'=>$cancelaciones];
	}

	public static function consultas(): array
	{
		$con = Conexion::getInstance();
		$sth = $con->query(
			"SELECT id_contacto, tema_contacto, nombre_contacto, correo_contacto,
			        telefono_contacto, mensaje_contacto, respuesta_contacto, fecha_respuesta,
			        estado_contacto, fecha_creacion
			 FROM contactos
			 ORDER BY fecha_creacion DESC, id_contacto DESC"
		);
		return $sth->fetchAll();
	}

	public static function marcarAtendida(): array
	{
		if ($error = Acl::guard(self::SECCION)) {
			return $error;
		}

		$id = (int) ($_POST['id'] ?? 0);
		if ($id <= 0) {
			return ['status' => 'error', 'message' => 'Consulta no válida'];
		}

		$con = Conexion::getInstance();
		$existe = $con->prepare('SELECT estado_contacto FROM contactos WHERE id_contacto = ?');
		$existe->execute([$id]);
		$estado = $existe->fetchColumn();
		if ($estado === false) return ['status'=>'error', 'message'=>'Consulta no encontrada'];
		if ($estado === 'atendida') return ['status'=>'success', 'message'=>'La consulta ya estaba atendida'];

		$sth = $con->prepare("UPDATE contactos SET estado_contacto = 'atendida' WHERE id_contacto = :id AND estado_contacto = 'pendiente'");
		$sth->execute([':id' => $id]);

		return ['status' => 'success', 'message' => 'Consulta marcada como atendida'];
	}

	public static function responderConsulta(): array
	{
		if ($error = Acl::guard(self::SECCION)) {
			return $error;
		}

		$id = (int) ($_POST['id'] ?? 0);
		$respuesta = trim((string) ($_POST['respuesta'] ?? ''));
		if ($id <= 0) return ['status' => 'error', 'message' => 'Consulta no válida'];
		if (mb_strlen($respuesta) < 10 || mb_strlen($respuesta) > 5000) {
			return ['status' => 'error', 'message' => 'La respuesta debe tener entre 10 y 5000 caracteres'];
		}

		$con = Conexion::getInstance();
		$consulta = $con->prepare(
			'SELECT tema_contacto, nombre_contacto, correo_contacto, mensaje_contacto, estado_contacto
			 FROM contactos WHERE id_contacto = ?'
		);
		$consulta->execute([$id]);
		$contacto = $consulta->fetch();
		if (!$contacto) return ['status' => 'error', 'message' => 'Consulta no encontrada'];
		if ($contacto['estado_contacto'] === 'atendida') return ['status' => 'error', 'message' => 'La consulta ya está atendida'];

		$tema = ['talla' => 'Duda de talla', 'pedido' => 'Mi pedido', 'otro' => 'Otro tema'][$contacto['tema_contacto']] ?? 'Consulta';
		if (!Correo::enviarRespuestaConsulta(
			(string) $contacto['correo_contacto'],
			(string) $contacto['nombre_contacto'],
			$tema,
			(string) $contacto['mensaje_contacto'],
			$respuesta,
		)) {
			http_response_code(503);
			return ['status' => 'error', 'message' => 'No se pudo enviar la respuesta. La consulta sigue pendiente.'];
		}

		$actualizar = $con->prepare(
			"UPDATE contactos SET respuesta_contacto = :respuesta, fecha_respuesta = NOW(),
			 estado_contacto = 'atendida'
			 WHERE id_contacto = :id AND estado_contacto = 'pendiente'"
		);
		$actualizar->execute([':respuesta' => $respuesta, ':id' => $id]);
		if ($actualizar->rowCount() !== 1) {
			error_log('Kloset · respuesta enviada pero no se pudo marcar la consulta ' . $id . ' como atendida.');
			return ['status' => 'error', 'message' => 'La respuesta fue enviada, pero no se pudo actualizar el estado.'];
		}

		return ['status' => 'success', 'message' => 'Respuesta enviada y consulta marcada como atendida'];
	}

	public static function marcarLeida(): array
	{
		if ($error = Acl::guard(self::SECCION)) {
			return $error;
		}

		$id = (int) ($_POST['id'] ?? 0);
		if ($id <= 0) {
			return ['status' => 'error', 'message' => 'Notificación no válida'];
		}

		$con = Conexion::getInstance();
		$existe = $con->prepare('SELECT 1 FROM notificaciones WHERE id_notificacion = ?');
		$existe->execute([$id]);
		if (!$existe->fetchColumn()) return ['status'=>'error', 'message'=>'Notificación no encontrada'];
		$sth = $con->prepare('UPDATE notificaciones SET leido_notificacion = 1 WHERE id_notificacion = :id');
		$sth->execute([':id' => $id]);

		return ['status' => 'success', 'message' => 'Notificación marcada como leída'];
	}

	public static function marcarTodasLeidas(): array
	{
		if ($error = Acl::guard(self::SECCION)) {
			return $error;
		}

		$con = Conexion::getInstance();
		$con->exec('UPDATE notificaciones SET leido_notificacion = 1 WHERE leido_notificacion = 0');

		return ['status' => 'success', 'message' => 'Todas las notificaciones se marcaron como leídas'];
	}
}
