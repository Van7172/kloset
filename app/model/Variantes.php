<?php

namespace Develoweb\App\Model;

class Variantes
{
	private const SECCION = 'variantes';
	private const TALLAS = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
	private const CORTES = ['Slim', 'Regular', 'Oversize'];

	public static function get(): array
	{
		$con = Conexion::getInstance();
		$sth = $con->query(
			"SELECT v.id_variante, v.id_producto, v.sku_variante, v.talla_variante, v.corte_variante, v.stock_variante,
			        p.nombre_producto
			 FROM productos_variantes v
			 INNER JOIN productos p ON p.id_producto = v.id_producto
			 ORDER BY p.nombre_producto ASC, v.talla_variante ASC"
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
			'SELECT v.*, p.nombre_producto FROM productos_variantes v
			 INNER JOIN productos p ON p.id_producto = v.id_producto
			 WHERE v.id_variante = :id'
		);
		$sth->execute([':id' => $id]);
		$row = $sth->fetch();
		if (!$row) {
			return ['status' => 'error', 'message' => 'Variante no encontrada'];
		}
		return ['status' => 'success', 'variante' => $row];
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
		if (self::skuEnUso($datos['sku'], 0)) {
			return ['status' => 'error', 'message' => 'El SKU ya está en uso'];
		}
		if (self::comboEnUso($datos['id_producto'], $datos['talla'], $datos['corte'], 0)) {
			return ['status' => 'error', 'message' => 'Ese producto ya tiene una variante con esa talla y corte'];
		}

		$con = Conexion::getInstance();
		$sth = $con->prepare(
			'INSERT INTO productos_variantes (id_producto, talla_variante, corte_variante, sku_variante, stock_variante)
			 VALUES (:producto, :talla, :corte, :sku, :stock)'
		);
		$sth->execute([
			':producto' => $datos['id_producto'],
			':talla' => $datos['talla'],
			':corte' => $datos['corte'],
			':sku' => $datos['sku'],
			':stock' => $datos['stock'],
		]);

		return ['status' => 'success', 'message' => 'Variante creada', 'id' => (int) $con->lastInsertId()];
	}

	public static function updateVariante(): array
	{
		if ($error = Acl::guard(self::SECCION)) {
			return $error;
		}

		$id = (int) ($_POST['id'] ?? 0);
		if ($id <= 0) {
			return ['status' => 'error', 'message' => 'Variante no válida'];
		}

		$datos = self::readInput();
		if (isset($datos['status'])) {
			return $datos;
		}
		if (self::skuEnUso($datos['sku'], $id)) {
			return ['status' => 'error', 'message' => 'El SKU ya está en uso'];
		}
		if (self::comboEnUso($datos['id_producto'], $datos['talla'], $datos['corte'], $id)) {
			return ['status' => 'error', 'message' => 'Ese producto ya tiene una variante con esa talla y corte'];
		}

		$con = Conexion::getInstance();
		$sth = $con->prepare(
			'UPDATE productos_variantes SET id_producto = :producto, talla_variante = :talla,
			 corte_variante = :corte, sku_variante = :sku, stock_variante = :stock
			 WHERE id_variante = :id'
		);
		$sth->execute([
			':producto' => $datos['id_producto'],
			':talla' => $datos['talla'],
			':corte' => $datos['corte'],
			':sku' => $datos['sku'],
			':stock' => $datos['stock'],
			':id' => $id,
		]);

		return ['status' => 'success', 'message' => 'Variante actualizada', 'id' => $id];
	}

	public static function deleteVariante(): array
	{
		if ($error = Acl::guard(self::SECCION)) {
			return $error;
		}

		$id = (int) ($_POST['id'] ?? 0);
		if ($id <= 0) {
			return ['status' => 'error', 'message' => 'Variante no válida'];
		}

		$con = Conexion::getInstance();
		// id_variante es NOT NULL + RESTRICT en carritos_items y pedidos_items:
		// si ya se vendió o está en una bolsa, no puede desaparecer del inventario.
		$sth = $con->prepare('SELECT COUNT(*) FROM pedidos_items WHERE id_variante = :id');
		$sth->execute([':id' => $id]);
		if ((int) $sth->fetchColumn() > 0) {
			return ['status' => 'error', 'message' => 'No se puede eliminar: la variante tiene pedidos_items asociados'];
		}
		$sth = $con->prepare('SELECT COUNT(*) FROM carritos_items WHERE id_variante = :id');
		$sth->execute([':id' => $id]);
		if ((int) $sth->fetchColumn() > 0) {
			return ['status' => 'error', 'message' => 'No se puede eliminar: hay bolsas de clientes con esta variante'];
		}

		$sth = $con->prepare('DELETE FROM productos_variantes WHERE id_variante = :id');
		$sth->execute([':id' => $id]);

		return ['status' => 'success', 'message' => 'Variante eliminada'];
	}

	private static function readInput(): array
	{
		$idProducto = (int) ($_POST['id_producto'] ?? 0);
		$talla = (string) ($_POST['talla'] ?? '');
		$corte = (string) ($_POST['corte'] ?? '');
		$sku = trim((string) ($_POST['sku'] ?? ''));
		$stock = (int) ($_POST['stock'] ?? 0);

		if ($idProducto <= 0) {
			return ['status' => 'error', 'message' => 'Selecciona un producto'];
		}
		if (!in_array($talla, self::TALLAS, true)) {
			return ['status' => 'error', 'message' => 'Talla no válida'];
		}
		if (!in_array($corte, self::CORTES, true)) {
			return ['status' => 'error', 'message' => 'Corte no válido'];
		}
		if ($sku === '') {
			return ['status' => 'error', 'message' => 'El SKU es obligatorio'];
		}
		if ($stock < 0) {
			return ['status' => 'error', 'message' => 'El stock no puede ser negativo'];
		}

		return ['id_producto' => $idProducto, 'talla' => $talla, 'corte' => $corte, 'sku' => $sku, 'stock' => $stock];
	}

	private static function skuEnUso(string $sku, int $id): bool
	{
		$con = Conexion::getInstance();
		$sth = $con->prepare('SELECT COUNT(*) FROM productos_variantes WHERE sku_variante = :sku AND id_variante <> :id');
		$sth->execute([':sku' => $sku, ':id' => $id]);
		return (int) $sth->fetchColumn() > 0;
	}

	private static function comboEnUso(int $idProducto, string $talla, string $corte, int $id): bool
	{
		$con = Conexion::getInstance();
		$sth = $con->prepare(
			'SELECT COUNT(*) FROM productos_variantes
			 WHERE id_producto = :p AND talla_variante = :t AND corte_variante = :c AND id_variante <> :id'
		);
		$sth->execute([':p' => $idProducto, ':t' => $talla, ':c' => $corte, ':id' => $id]);
		return (int) $sth->fetchColumn() > 0;
	}
}
