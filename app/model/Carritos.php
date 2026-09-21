<?php

namespace Develoweb\App\Model;

use Develoweb\App\Utilities\JwtHelper;
use PDO;

class Carritos
{
	/**
	 * `carritos.id_usuario_sistema` es UNIQUE: cada usuario tiene una única fila de
	 * carrito para siempre (no una por sesión de compra). La vaciamos al pagar en
	 * vez de "cerrarla", así que aquí no filtramos por `estado_carrito`.
	 */
	private static function idCarritoActivo(PDO $con, int $userId): int
	{
		$sth = $con->prepare('SELECT id_carrito FROM carritos WHERE id_usuario_sistema = :id');
		$sth->execute([':id' => $userId]);
		$id = $sth->fetchColumn();
		if ($id) {
			return (int) $id;
		}

		$sth = $con->prepare('INSERT INTO carritos (id_usuario_sistema) VALUES (:id)');
		$sth->execute([':id' => $userId]);
		return (int) $con->lastInsertId();
	}

	private static function itemsDe(PDO $con, int $idCarrito): array
	{
		$sth = $con->prepare(
			"SELECT ci.id_carrito_item, ci.id_variante, ci.cantidad_carrito_item,
			        v.talla_variante, v.corte_variante, v.stock_variante,
			        p.id_producto, p.nombre_producto, p.url_producto, p.precio_producto,
			        (SELECT i.url_imagen FROM productos_imagenes i
			          WHERE i.id_producto = p.id_producto ORDER BY i.orden_imagen, i.id_imagen LIMIT 1) AS url_imagen
			 FROM carritos_items ci
			 INNER JOIN productos_variantes v ON v.id_variante = ci.id_variante
			 INNER JOIN productos p ON p.id_producto = v.id_producto
			 WHERE ci.id_carrito = :id
			 ORDER BY ci.id_carrito_item ASC"
		);
		$sth->execute([':id' => $idCarrito]);
		$items = $sth->fetchAll();
		foreach ($items as &$item) {
			$item['url_imagen'] = $item['url_imagen'] ? IMGS . 'productos/' . $item['url_imagen'] : null;
		}
		unset($item);

		return $items;
	}

	public static function mio(): array
	{
		$userId = JwtHelper::bearerUserId();
		if (!$userId) {
			http_response_code(401);
			return ['status' => 'error', 'message' => 'No autorizado'];
		}

		$con = Conexion::getInstance();
		$idCarrito = self::idCarritoActivo($con, $userId);

		return ['status' => 'success', 'items' => self::itemsDe($con, $idCarrito)];
	}

	public static function agregar(): array
	{
		$userId = JwtHelper::bearerUserId();
		if (!$userId) {
			http_response_code(401);
			return ['status' => 'error', 'message' => 'No autorizado'];
		}

		$idProducto = (int) ($_POST['id_producto'] ?? 0);
		$talla = (string) ($_POST['talla'] ?? '');
		$corte = (string) ($_POST['corte'] ?? '');
		$cantidad = max(1, (int) ($_POST['cantidad'] ?? 1));

		if ($idProducto <= 0 || $talla === '' || $corte === '') {
			return ['status' => 'error', 'message' => 'Producto, talla y corte son obligatorios'];
		}

		$con = Conexion::getInstance();
		$sth = $con->prepare(
			'SELECT id_variante, stock_variante FROM productos_variantes
			 WHERE id_producto = :p AND talla_variante = :t AND corte_variante = :c'
		);
		$sth->execute([':p' => $idProducto, ':t' => $talla, ':c' => $corte]);
		$variante = $sth->fetch();
		if (!$variante) {
			return ['status' => 'error', 'message' => 'Esa combinación de talla y corte no existe'];
		}
		if ((int) $variante['stock_variante'] < $cantidad) {
			return ['status' => 'error', 'message' => 'No hay stock suficiente en esa talla'];
		}

		$idCarrito = self::idCarritoActivo($con, $userId);

		$sth = $con->prepare(
			'SELECT id_carrito_item, cantidad_carrito_item FROM carritos_items
			 WHERE id_carrito = :carrito AND id_variante = :variante'
		);
		$sth->execute([':carrito' => $idCarrito, ':variante' => $variante['id_variante']]);
		$existente = $sth->fetch();

		if ($existente) {
			$nuevaCantidad = min((int) $variante['stock_variante'], (int) $existente['cantidad_carrito_item'] + $cantidad);
			$sth = $con->prepare('UPDATE carritos_items SET cantidad_carrito_item = :c WHERE id_carrito_item = :id');
			$sth->execute([':c' => $nuevaCantidad, ':id' => $existente['id_carrito_item']]);
		} else {
			$sth = $con->prepare(
				'INSERT INTO carritos_items (id_carrito, id_variante, cantidad_carrito_item) VALUES (:carrito, :variante, :cantidad)'
			);
			$sth->execute([':carrito' => $idCarrito, ':variante' => $variante['id_variante'], ':cantidad' => $cantidad]);
		}

		return ['status' => 'success', 'items' => self::itemsDe($con, $idCarrito)];
	}

	public static function quitar(): array
	{
		$userId = JwtHelper::bearerUserId();
		if (!$userId) {
			http_response_code(401);
			return ['status' => 'error', 'message' => 'No autorizado'];
		}

		$idItem = (int) ($_POST['id_carrito_item'] ?? 0);
		if ($idItem <= 0) {
			return ['status' => 'error', 'message' => 'Ítem no válido'];
		}

		$con = Conexion::getInstance();
		$idCarrito = self::idCarritoActivo($con, $userId);

		$sth = $con->prepare('DELETE FROM carritos_items WHERE id_carrito_item = :item AND id_carrito = :carrito');
		$sth->execute([':item' => $idItem, ':carrito' => $idCarrito]);

		return ['status' => 'success', 'items' => self::itemsDe($con, $idCarrito)];
	}
}
