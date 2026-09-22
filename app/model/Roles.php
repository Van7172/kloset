<?php

namespace Develoweb\App\Model;

class Roles
{
	private const SECCION = 'roles';

	public static function getAll()
	{
		$con = Conexion::getInstance();
		$sth = $con->prepare(
			"SELECT id_rol, nombre_rol FROM sistema_roles WHERE estado_rol = 'activo' ORDER BY id_rol"
		);
		$sth->execute();
		return $sth->fetchAll();
	}

	public static function get(): array
	{
		$con = Conexion::getInstance();
		$sth = $con->query(
			'SELECT r.id_rol, r.nombre_rol, r.descripcion_rol, r.estado_rol,
			        (SELECT COUNT(*) FROM sistema_usuarios u WHERE u.id_rol = r.id_rol) AS total_usuarios
			 FROM sistema_roles r
			 ORDER BY r.id_rol ASC'
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
			'SELECT r.*, (SELECT COUNT(*) FROM sistema_usuarios u WHERE u.id_rol = r.id_rol) AS total_usuarios
			 FROM sistema_roles r WHERE r.id_rol = :id'
		);
		$sth->execute([':id' => $id]);
		$row = $sth->fetch();
		if (!$row) {
			return ['status' => 'error', 'message' => 'Rol no encontrado'];
		}
		return ['status' => 'success', 'rol' => $row];
	}

	public static function store(): array
	{
		if ($error = Acl::guard(self::SECCION)) {
			return $error;
		}

		$datos = self::readInput();
		if (isset($datos['status'])) {
			return $datos;
		}
		if (self::nombreEnUso($datos['nombre'], 0)) {
			return ['status' => 'error', 'message' => 'Ya existe un rol con ese nombre'];
		}

		$con = Conexion::getInstance();
		$sth = $con->prepare(
			'INSERT INTO sistema_roles (nombre_rol, descripcion_rol, estado_rol) VALUES (:nombre, :descripcion, :estado)'
		);
		$sth->execute([':nombre' => $datos['nombre'], ':descripcion' => $datos['descripcion'], ':estado' => $datos['estado']]);

		return ['status' => 'success', 'message' => 'Rol creado', 'id' => (int) $con->lastInsertId()];
	}

	public static function updateRol(): array
	{
		if ($error = Acl::guard(self::SECCION)) {
			return $error;
		}

		$id = (int) ($_POST['id'] ?? 0);
		if ($id <= 0) {
			return ['status' => 'error', 'message' => 'Rol no válido'];
		}

		$datos = self::readInput();
		if (isset($datos['status'])) {
			return $datos;
		}
		if (self::nombreEnUso($datos['nombre'], $id)) {
			return ['status' => 'error', 'message' => 'Ya existe un rol con ese nombre'];
		}

		$con = Conexion::getInstance();
		$sth = $con->prepare(
			'UPDATE sistema_roles SET nombre_rol = :nombre, descripcion_rol = :descripcion, estado_rol = :estado
			 WHERE id_rol = :id'
		);
		$sth->execute([
			':nombre' => $datos['nombre'], ':descripcion' => $datos['descripcion'],
			':estado' => $datos['estado'], ':id' => $id,
		]);

		return ['status' => 'success', 'message' => 'Rol actualizado', 'id' => $id];
	}

	public static function deleteRol(): array
	{
		if ($error = Acl::guard(self::SECCION)) {
			return $error;
		}

		$id = (int) ($_POST['id'] ?? 0);
		if ($id <= 0) {
			return ['status' => 'error', 'message' => 'Rol no válido'];
		}

		$con = Conexion::getInstance();
		// sistema_usuarios.id_rol es ON DELETE RESTRICT.
		$sth = $con->prepare('SELECT COUNT(*) FROM sistema_usuarios WHERE id_rol = :id');
		$sth->execute([':id' => $id]);
		if ((int) $sth->fetchColumn() > 0) {
			return ['status' => 'error', 'message' => 'No se puede eliminar: hay usuarios con este rol asignado'];
		}

		$sth = $con->prepare('DELETE FROM sistema_roles WHERE id_rol = :id');
		$sth->execute([':id' => $id]);

		return ['status' => 'success', 'message' => 'Rol eliminado'];
	}

	private static function readInput(): array
	{
		$nombre = trim((string) ($_POST['nombre'] ?? ''));
		$descripcion = trim((string) ($_POST['descripcion'] ?? ''));
		$estado = ($_POST['estado'] ?? 'activo') === 'inactivo' ? 'inactivo' : 'activo';

		if ($nombre === '') {
			return ['status' => 'error', 'message' => 'El nombre del rol es obligatorio'];
		}

		return ['nombre' => $nombre, 'descripcion' => $descripcion, 'estado' => $estado];
	}

	private static function nombreEnUso(string $nombre, int $id): bool
	{
		$con = Conexion::getInstance();
		$sth = $con->prepare('SELECT COUNT(*) FROM sistema_roles WHERE nombre_rol = :nombre AND id_rol <> :id');
		$sth->execute([':nombre' => $nombre, ':id' => $id]);
		return (int) $sth->fetchColumn() > 0;
	}
}
