<?php

namespace Develoweb\App\Model;

class Notificaciones
{
	private const SECCION = 'notificaciones';

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
