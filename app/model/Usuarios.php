<?php

namespace Develoweb\App\Model;

class Usuarios
{
	private const SECCION = 'usuarios';

	public static function get(): array
	{
		$con = Conexion::getInstance();
		$sth = $con->query(
			'SELECT u.id_usuario_sistema, u.nombre_usuario_sistema, u.correo_usuario_sistema,
			        u.estado_usuario_sistema, r.id_rol, r.nombre_rol,
			        (SELECT COUNT(*) FROM usuarios_secciones us WHERE us.id_usuario_sistema = u.id_usuario_sistema) AS total_secciones
			 FROM sistema_usuarios u
			 INNER JOIN sistema_roles r ON r.id_rol = u.id_rol
			 ORDER BY u.id_usuario_sistema DESC'
		);
		return $sth->fetchAll();
	}

	/**
	 * Datos del usuario + matriz de acceso (usuarios_secciones).
	 */
	public static function getById(): array
	{
		if ($error = Acl::guard(self::SECCION)) {
			return $error;
		}

		$id = (int) ($_POST['id'] ?? 0);
		$con = Conexion::getInstance();

		$sth = $con->prepare(
			'SELECT u.id_usuario_sistema, u.nombre_usuario_sistema, u.correo_usuario_sistema,
			        u.estado_usuario_sistema, u.fecha_creacion, r.nombre_rol
			 FROM sistema_usuarios u
			 INNER JOIN sistema_roles r ON r.id_rol = u.id_rol
			 WHERE u.id_usuario_sistema = :id'
		);
		$sth->execute([':id' => $id]);
		$usuario = $sth->fetch();
		if (!$usuario) {
			return ['status' => 'error', 'message' => 'Usuario no encontrado'];
		}

		$asignadas = $con->prepare('SELECT id_seccion FROM usuarios_secciones WHERE id_usuario_sistema = :id');
		$asignadas->execute([':id' => $id]);
		$activas = array_map('intval', array_column($asignadas->fetchAll(), 'id_seccion'));

		$sth = $con->query(
			'SELECT s.id_seccion, s.nombre_seccion, s.url_seccion, m.nombre_modulo
			 FROM sistema_secciones s
			 INNER JOIN sistema_modulos m ON m.id_modulo = s.id_modulo
			 WHERE s.estado_seccion = \'activo\'
			 ORDER BY m.orden_modulo, s.orden_seccion'
		);

		$secciones = [];
		foreach ($sth->fetchAll() as $sec) {
			$secciones[] = [
				'id_seccion' => (int) $sec['id_seccion'],
				'nombre_seccion' => $sec['nombre_seccion'],
				'url_seccion' => $sec['url_seccion'],
				'nombre_modulo' => $sec['nombre_modulo'],
				'activa' => in_array((int) $sec['id_seccion'], $activas, true),
			];
		}

		return ['status' => 'success', 'usuario' => $usuario, 'secciones' => $secciones];
	}

	public static function store(): array
	{
		if ($error = Acl::guard(self::SECCION)) {
			return $error;
		}

		$nombre = trim((string) ($_POST['nombre'] ?? ''));
		$correo = trim((string) ($_POST['correo'] ?? ''));
		$password = (string) ($_POST['password'] ?? '');
		$idRol = (int) ($_POST['id_rol'] ?? 0);

		if ($nombre === '') {
			return ['status' => 'error', 'message' => 'El nombre es obligatorio'];
		}
		if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
			return ['status' => 'error', 'message' => 'El correo no es válido'];
		}
		if (strlen($password) < 8) {
			return ['status' => 'error', 'message' => 'La contraseña necesita 8 caracteres o más'];
		}
		if ($idRol <= 0) {
			return ['status' => 'error', 'message' => 'Selecciona un rol'];
		}

		$con = Conexion::getInstance();
		$sth = $con->prepare('SELECT COUNT(*) FROM sistema_usuarios WHERE correo_usuario_sistema = :correo');
		$sth->execute([':correo' => $correo]);
		if ((int) $sth->fetchColumn() > 0) {
			return ['status' => 'error', 'message' => 'Ese correo ya está registrado'];
		}

		$sth = $con->prepare(
			'INSERT INTO sistema_usuarios (id_rol, nombre_usuario_sistema, correo_usuario_sistema, contrasena_usuario_sistema)
			 VALUES (:rol, :nombre, :correo, :pass)'
		);
		$sth->execute([
			':rol' => $idRol,
			':nombre' => $nombre,
			':correo' => $correo,
			':pass' => password_hash($password, PASSWORD_DEFAULT),
		]);

		return ['status' => 'success', 'message' => 'Usuario creado', 'id' => (int) $con->lastInsertId()];
	}

	/**
	 * Reescribe usuarios_secciones para un usuario.
	 */
	public static function updateSecciones(): array
	{
		if ($error = Acl::guard(self::SECCION)) {
			return $error;
		}

		$id = (int) ($_POST['id'] ?? 0);
		if ($id <= 0) {
			return ['status' => 'error', 'message' => 'Usuario no válido'];
		}

		$secciones = array_map('intval', (array) ($_POST['secciones'] ?? []));
		$secciones = array_values(array_unique(array_filter($secciones, fn ($v) => $v > 0)));

		$con = Conexion::getInstance();

		// Evita que el administrador se deje a sí mismo sin acceso al ACL
		$actual = Acl::usuario();
		if ($actual && $actual->getId() === $id) {
			$sth = $con->prepare('SELECT id_seccion FROM sistema_secciones WHERE url_seccion = :url');
			$sth->execute([':url' => self::SECCION]);
			$propia = (int) $sth->fetchColumn();
			if ($propia > 0 && !in_array($propia, $secciones, true)) {
				return ['status' => 'error', 'message' => 'No puedes quitarte el acceso a Usuarios'];
			}
		}

		$con->beginTransaction();
		try {
			$con->prepare('DELETE FROM usuarios_secciones WHERE id_usuario_sistema = :id')->execute([':id' => $id]);
			$insert = $con->prepare('INSERT INTO usuarios_secciones (id_usuario_sistema, id_seccion) VALUES (:u, :s)');
			foreach ($secciones as $seccion) {
				$insert->execute([':u' => $id, ':s' => $seccion]);
			}
			$con->commit();
		} catch (\Throwable $e) {
			$con->rollBack();
			return ['status' => 'error', 'message' => 'No se pudo guardar el acceso'];
		}

		return [
			'status' => 'success',
			'message' => 'usuarios_secciones actualizada (' . count($secciones) . ' secciones)',
			'total' => count($secciones),
		];
	}
}
