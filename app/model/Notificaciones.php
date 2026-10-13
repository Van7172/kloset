<?php

namespace Develoweb\App\Model;

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
		$seguimiento = $con->query("SELECT id_pedido,estado_pedido FROM pedidos WHERE estado_pedido IN ('enviado','en_reparto') AND (seguimiento_pedido IS NULL OR seguimiento_pedido='') ORDER BY fecha_creacion DESC LIMIT 20")->fetchAll();
		$cancelaciones = $con->query("SELECT id_pedido,fecha_creacion FROM historial_estados_pedidos WHERE estado_historial_estado_pedido='cancelado' ORDER BY fecha_creacion DESC LIMIT 10")->fetchAll();
		return ['stock'=>$stock,'seguimiento'=>$seguimiento,'cancelaciones'=>$cancelaciones];
	}

	public static function get(): array
	{
		$con = Conexion::getInstance();
		$sth = $con->query(
			"SELECT n.id_notificacion, n.id_usuario_sistema, n.id_pedido, n.id_contacto,
			        n.mensaje_notificacion, n.leido_notificacion, n.fecha_creacion,
			        COALESCE(c.nombre_contacto, u.nombre_usuario_sistema) AS cliente,
			        c.tema_contacto, c.correo_contacto, c.telefono_contacto, c.mensaje_contacto
			 FROM notificaciones n
			 INNER JOIN sistema_usuarios u ON u.id_usuario_sistema = n.id_usuario_sistema
			 LEFT JOIN contactos c ON c.id_contacto = n.id_contacto
			 ORDER BY n.fecha_creacion DESC"
		);
		return $sth->fetchAll();
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
