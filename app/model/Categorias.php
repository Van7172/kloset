<?php

namespace Develoweb\App\Model;

use PDO;

class Categorias
{
	private const SECCION = 'categorias';

	public static function get(): array
	{
		$con = Conexion::getInstance();
		$sth = $con->query(
			"SELECT c.id_categoria, c.nombre_categoria, c.url_categoria, c.descripcion_categoria,
			        c.estado_categoria,
			        (SELECT COUNT(*) FROM productos p WHERE p.id_categoria = c.id_categoria) AS total_productos
			 FROM categorias c
			 ORDER BY c.nombre_categoria ASC"
		);
		return $sth->fetchAll();
	}

	public static function getActivas(): array
	{
		$con = Conexion::getInstance();
		$sth = $con->query(
			"SELECT id_categoria, nombre_categoria FROM categorias
			 WHERE estado_categoria = 'activo' ORDER BY nombre_categoria ASC"
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
		$sth = $con->prepare('SELECT * FROM categorias WHERE id_categoria = :id');
		$sth->bindParam(':id', $id, PDO::PARAM_INT);
		$sth->execute();
		$row = $sth->fetch();
		if (!$row) {
			return ['status' => 'error', 'message' => 'Categoría no encontrada'];
		}
		return ['status' => 'success', 'categoria' => $row];
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
		if (self::urlEnUso($datos['url'], 0)) {
			return ['status' => 'error', 'message' => 'La URL ya está en uso'];
		}
		if (self::nombreEnUso($datos['nombre'], 0)) {
			return ['status' => 'error', 'message' => 'El nombre ya está en uso'];
		}

		$con = Conexion::getInstance();
		$sth = $con->prepare(
			'INSERT INTO categorias (nombre_categoria, url_categoria, descripcion_categoria, estado_categoria)
			 VALUES (:nombre, :url, :descripcion, :estado)'
		);
		$sth->execute([
			':nombre' => $datos['nombre'],
			':url' => $datos['url'],
			':descripcion' => $datos['descripcion'],
			':estado' => $datos['estado'],
		]);

		return [
			'status' => 'success',
			'message' => 'Categoría creada',
			'id' => (int) $con->lastInsertId(),
		];
	}

	public static function updateCategoria(): array
	{
		if ($error = Acl::guard(self::SECCION)) {
			return $error;
		}

		$id = (int) ($_POST['id'] ?? 0);
		if ($id <= 0) {
			return ['status' => 'error', 'message' => 'Categoría no válida'];
		}

		$datos = self::readInput();
		if (isset($datos['status'])) {
			return $datos;
		}
		if (self::urlEnUso($datos['url'], $id)) {
			return ['status' => 'error', 'message' => 'La URL ya está en uso'];
		}
		if (self::nombreEnUso($datos['nombre'], $id)) {
			return ['status' => 'error', 'message' => 'El nombre ya está en uso'];
		}

		$con = Conexion::getInstance();
		$sth = $con->prepare(
			'UPDATE categorias SET nombre_categoria = :nombre, url_categoria = :url,
			 descripcion_categoria = :descripcion, estado_categoria = :estado
			 WHERE id_categoria = :id'
		);
		$sth->execute([
			':nombre' => $datos['nombre'],
			':url' => $datos['url'],
			':descripcion' => $datos['descripcion'],
			':estado' => $datos['estado'],
			':id' => $id,
		]);

		return ['status' => 'success', 'message' => 'Categoría actualizada', 'id' => $id];
	}

	public static function deleteCategoria(): array
	{
		if ($error = Acl::guard(self::SECCION)) {
			return $error;
		}

		$id = (int) ($_POST['id'] ?? 0);
		if ($id <= 0) {
			return ['status' => 'error', 'message' => 'Categoría no válida'];
		}

		$con = Conexion::getInstance();
		$sth = $con->prepare('SELECT COUNT(*) FROM productos WHERE id_categoria = :id');
		$sth->execute([':id' => $id]);
		if ((int) $sth->fetchColumn() > 0) {
			return ['status' => 'error', 'message' => 'No se puede eliminar: la categoría tiene productos asociados'];
		}

		$sth = $con->prepare('DELETE FROM categorias WHERE id_categoria = :id');
		$sth->execute([':id' => $id]);

		return ['status' => 'success', 'message' => 'Categoría eliminada'];
	}

	private static function readInput(): array
	{
		$nombre = trim((string) ($_POST['nombre'] ?? ''));
		$url = trim((string) ($_POST['url'] ?? ''));
		$descripcion = trim((string) ($_POST['descripcion'] ?? ''));
		$estado = ($_POST['estado'] ?? 'activo') === 'inactivo' ? 'inactivo' : 'activo';

		if ($nombre === '') {
			return ['status' => 'error', 'message' => 'El nombre es obligatorio'];
		}
		$url = \url_friend($url !== '' ? $url : $nombre);
		if ($url === '') {
			return ['status' => 'error', 'message' => 'La URL no es válida'];
		}

		return [
			'nombre' => $nombre,
			'url' => $url,
			'descripcion' => $descripcion,
			'estado' => $estado,
		];
	}

	private static function urlEnUso(string $url, int $id): bool
	{
		$con = Conexion::getInstance();
		$sth = $con->prepare('SELECT COUNT(*) FROM categorias WHERE url_categoria = :url AND id_categoria <> :id');
		$sth->execute([':url' => $url, ':id' => $id]);
		return (int) $sth->fetchColumn() > 0;
	}

	private static function nombreEnUso(string $nombre, int $id): bool
	{
		$con = Conexion::getInstance();
		$sth = $con->prepare('SELECT COUNT(*) FROM categorias WHERE nombre_categoria = :nombre AND id_categoria <> :id');
		$sth->execute([':nombre' => $nombre, ':id' => $id]);
		return (int) $sth->fetchColumn() > 0;
	}
}
