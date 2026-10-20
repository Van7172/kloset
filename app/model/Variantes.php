<?php

namespace Develoweb\App\Model;

class Variantes
{
	private const SECCION = 'variantes';
	private const TALLAS = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
	private const CORTES = ['No aplica', 'Slim', 'Regular', 'Oversize'];

	public static function get(): array
	{
		$con = Conexion::getInstance();
		$sth = $con->query(
			"SELECT v.id_variante, v.id_producto, v.id_color, c.nombre_color, v.sku_variante, v.talla_variante, v.corte_variante, v.stock_variante,
			        p.nombre_producto
			 FROM productos_variantes v
			 INNER JOIN productos p ON p.id_producto = v.id_producto
			 INNER JOIN productos_colores c ON c.id_color = v.id_color
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
			'SELECT v.*, p.nombre_producto, c.nombre_color FROM productos_variantes v
			 INNER JOIN productos p ON p.id_producto = v.id_producto
			 INNER JOIN productos_colores c ON c.id_color = v.id_color
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
		if (self::comboEnUso($datos['id_producto'], $datos['id_color'], $datos['talla'], $datos['corte'], 0)) {
			return ['status' => 'error', 'message' => 'La combinación de color, talla y corte ya existe'];
		}

		$con = Conexion::getInstance();
		$sth = $con->prepare(
			'INSERT INTO productos_variantes (id_producto, id_color, talla_variante, corte_variante, sku_variante, stock_variante)
			 VALUES (:producto, :color, :talla, :corte, :sku, :stock)'
		);
		for ($intento = 0; $intento < 5; $intento++) {
			$sku = Productos::codigo($datos['id_producto']) . 'V' . strtoupper(bin2hex(random_bytes(6)));
			try {
				$sth->execute([
					':producto' => $datos['id_producto'],
					':color' => $datos['id_color'],
					':talla' => $datos['talla'],
					':corte' => $datos['corte'],
					':sku' => $sku,
					':stock' => $datos['stock'],
				]);
				return ['status' => 'success', 'message' => 'Variante creada', 'id' => (int) $con->lastInsertId(), 'sku' => $sku];
			} catch (\PDOException $e) {
				if ($e->getCode() !== '23000') throw $e;
				if (self::comboEnUso($datos['id_producto'], $datos['id_color'], $datos['talla'], $datos['corte'], 0)) {
					return ['status' => 'error', 'message' => 'La combinación de color, talla y corte ya existe'];
				}
				if (!self::skuEnUso($sku, 0)) throw $e;
			}
		}

		return ['status' => 'error', 'message' => 'No se pudo asignar un SKU único. Inténtalo nuevamente.'];
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
		if (self::comboEnUso($datos['id_producto'], $datos['id_color'], $datos['talla'], $datos['corte'], $id)) {
			return ['status' => 'error', 'message' => 'La combinación de color, talla y corte ya existe'];
		}

		$con = Conexion::getInstance();
		$existe = $con->prepare('SELECT * FROM productos_variantes WHERE id_variante = ?');
		$existe->execute([$id]);
		$actual = $existe->fetch();
		if (!$actual) return ['status' => 'error', 'message' => 'Variante no encontrada'];
		if ((int)$actual['id_producto'] !== $datos['id_producto'] || (int)$actual['id_color'] !== $datos['id_color'] || $actual['talla_variante'] !== $datos['talla'] || $actual['corte_variante'] !== $datos['corte']) {
			foreach (['pedidos_items', 'carritos_items', 'prendas_guardadas_clientes'] as $tabla) {
				$usos = $con->prepare("SELECT COUNT(*) FROM $tabla WHERE id_variante = ?");
				$usos->execute([$id]);
				if ($usos->fetchColumn()) return ['status' => 'error', 'message' => 'No cambies la identidad de una variante usada por clientes. Crea otra variante.'];
			}
		}
		$sth = $con->prepare(
			'UPDATE productos_variantes SET id_producto = :producto, id_color = :color, talla_variante = :talla,
			 corte_variante = :corte, stock_variante = :stock
			 WHERE id_variante = :id'
		);
		try { $sth->execute([
			':producto' => $datos['id_producto'],
			':color' => $datos['id_color'],
			':talla' => $datos['talla'],
			':corte' => $datos['corte'],
			':stock' => $datos['stock'],
			':id' => $id,
		]); } catch (\PDOException $e) {
			if ($e->getCode() === '23000') return ['status'=>'error','message'=>'La combinación de color, talla y corte ya existe'];
			throw $e;
		}
		if ((int)$actual['stock_variante'] > 0 && $datos['stock'] === 0) {
			$con->prepare("INSERT INTO inventario_eventos (id_variante,tipo_evento,id_usuario_sistema) VALUES (?,'sin_stock',?)")
				->execute([$id,Acl::usuario()->getId()]);
		}

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
		$sth = $con->prepare('SELECT COUNT(*) FROM prendas_guardadas_clientes WHERE id_variante=?');
		$sth->execute([$id]);
		if ((int)$sth->fetchColumn()>0) return ['status'=>'error','message'=>'No se puede eliminar: hay prendas guardadas con esta variante'];

		$sth = $con->prepare('DELETE FROM productos_variantes WHERE id_variante = :id');
		$sth->execute([':id' => $id]);
		if ($sth->rowCount() === 0) return ['status'=>'error', 'message'=>'Variante no encontrada'];

		return ['status' => 'success', 'message' => 'Variante eliminada'];
	}

	private static function readInput(): array
	{
		$idProducto = (int) ($_POST['id_producto'] ?? 0);
		$idColor = (int) ($_POST['id_color'] ?? 0);
		$talla = (string) ($_POST['talla'] ?? '');
		$corte = (string) ($_POST['corte'] ?? '');
		$stock = (int) ($_POST['stock'] ?? 0);
		if (filter_var($_POST['stock'] ?? 0, FILTER_VALIDATE_INT) === false || $stock > 2147483647) return ['status'=>'error', 'message'=>'El stock debe ser un entero entre 0 y 2147483647'];

		if ($idProducto <= 0) {
			return ['status' => 'error', 'message' => 'Selecciona un producto'];
		}
		$existe = Conexion::getInstance()->prepare('SELECT 1 FROM productos WHERE id_producto = ?');
		$existe->execute([$idProducto]);
		if (!$existe->fetchColumn()) return ['status'=>'error', 'message'=>'El producto no existe'];
		$color = Conexion::getInstance()->prepare('SELECT 1 FROM productos_colores WHERE id_color=? AND id_producto=?');
		$color->execute([$idColor,$idProducto]);
		if (!$color->fetchColumn()) return ['status'=>'error','message'=>'Selecciona un color del producto'];
		if (!in_array($talla, self::TALLAS, true)) {
			return ['status' => 'error', 'message' => 'Talla no válida'];
		}
		if (!in_array($corte, self::CORTES, true)) {
			return ['status' => 'error', 'message' => 'Corte no válido'];
		}
		if ($stock < 0) {
			return ['status' => 'error', 'message' => 'El stock no puede ser negativo'];
		}

		return ['id_producto' => $idProducto, 'id_color' => $idColor, 'talla' => $talla, 'corte' => $corte, 'stock' => $stock];
	}

	private static function skuEnUso(string $sku, int $id): bool
	{
		$con = Conexion::getInstance();
		$sth = $con->prepare('SELECT COUNT(*) FROM productos_variantes WHERE sku_variante = :sku AND id_variante <> :id');
		$sth->execute([':sku' => $sku, ':id' => $id]);
		return (int) $sth->fetchColumn() > 0;
	}

	private static function comboEnUso(int $idProducto, int $idColor, string $talla, string $corte, int $id): bool
	{
		$con = Conexion::getInstance();
		$sth = $con->prepare(
			'SELECT COUNT(*) FROM productos_variantes
			 WHERE id_producto = :p AND id_color = :color AND talla_variante = :t AND corte_variante = :c AND id_variante <> :id'
		);
		$sth->execute([':p' => $idProducto, ':color' => $idColor, ':t' => $talla, ':c' => $corte, ':id' => $id]);
		return (int) $sth->fetchColumn() > 0;
	}
}
