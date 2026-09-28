<?php

namespace Develoweb\App\Model;

class Clientes
{
	private const SECCION = 'clientes';

	public static function get(): array
	{
		$con = Conexion::getInstance();
		$sth = $con->query(
			"SELECT u.id_usuario_sistema, u.nombre_usuario_sistema, u.correo_usuario_sistema, u.estado_usuario_sistema,
			        pc.talla_recomendada_perfil_corporal,
			        (pc.id_perfil_corporal IS NOT NULL) AS tiene_perfil,
			        (SELECT COUNT(*) FROM pedidos p WHERE p.id_usuario_sistema = u.id_usuario_sistema) AS total_pedidos
			 FROM sistema_usuarios u
			 INNER JOIN sistema_roles r ON r.id_rol = u.id_rol
			 LEFT JOIN perfiles_corporales pc ON pc.id_usuario_sistema = u.id_usuario_sistema
			 WHERE r.nombre_rol = 'Cliente'
			 ORDER BY u.id_usuario_sistema DESC"
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
			"SELECT u.id_usuario_sistema, u.nombre_usuario_sistema, u.correo_usuario_sistema,
			        u.estado_usuario_sistema, u.fecha_creacion
			 FROM sistema_usuarios u
			 INNER JOIN sistema_roles r ON r.id_rol = u.id_rol
			 WHERE u.id_usuario_sistema = :id AND r.nombre_rol = 'Cliente'"
		);
		$sth->execute([':id' => $id]);
		$cliente = $sth->fetch();
		if (!$cliente) {
			return ['status' => 'error', 'message' => 'Cliente no encontrado'];
		}

		$sth = $con->prepare('SELECT * FROM perfiles_corporales WHERE id_usuario_sistema = :id');
		$sth->execute([':id' => $id]);
		$perfil = $sth->fetch() ?: null;

		$sth = $con->prepare('SELECT url_modelo_base_avatar_3d FROM avatares_3d WHERE id_usuario_sistema = :id');
		$sth->execute([':id' => $id]);
		$avatar = $sth->fetchColumn();

		$sth = $con->prepare(
			"SELECT id_pedido, estado_pedido, total_pedido, fecha_creacion
			 FROM pedidos WHERE id_usuario_sistema = :id ORDER BY fecha_creacion DESC LIMIT 5"
		);
		$sth->execute([':id' => $id]);
		$pedidos = $sth->fetchAll();

		return [
			'status' => 'success',
			'cliente' => $cliente,
			'perfil' => $perfil,
			'avatar' => $avatar ?: null,
			'pedidos' => $pedidos,
		];
	}
}
