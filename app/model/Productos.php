<?php

namespace Develoweb\App\Model;

use PDO;

class Productos
{
	private const SECCION = 'productos';
	private const DIR_IMGS = 'imgs/productos/';

	/* ---------------------------------------------------------------- Admin */

	public static function get(): array
	{
		$con = Conexion::getInstance();
		$sth = $con->query(
			"SELECT p.id_producto, p.nombre_producto, p.url_producto, p.precio_producto,
			        p.estado_producto, p.id_categoria, c.nombre_categoria,
			        (SELECT COUNT(*) FROM productos_imagenes i WHERE i.id_producto = p.id_producto) AS total_imagenes,
			        (SELECT COALESCE(SUM(v.stock_variante), 0) FROM productos_variantes v WHERE v.id_producto = p.id_producto) AS stock_total
			 FROM productos p
			 LEFT JOIN categorias c ON c.id_categoria = p.id_categoria
			 ORDER BY p.id_producto DESC"
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
		$sth = $con->prepare('SELECT * FROM productos WHERE id_producto = :id');
		$sth->bindParam(':id', $id, PDO::PARAM_INT);
		$sth->execute();
		$producto = $sth->fetch();
		if (!$producto) {
			return ['status' => 'error', 'message' => 'Producto no encontrado'];
		}

		return [
			'status' => 'success',
			'producto' => $producto,
			'imagenes' => self::imagenesDe($id),
		];
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

		$con = Conexion::getInstance();
		$sth = $con->prepare(
			'INSERT INTO productos (id_categoria, nombre_producto, url_producto, descripcion_producto,
			                        precio_producto, estado_producto)
			 VALUES (:categoria, :nombre, :url, :descripcion, :precio, :estado)'
		);
		$sth->execute([
			':categoria' => $datos['categoria'],
			':nombre' => $datos['nombre'],
			':url' => $datos['url'],
			':descripcion' => $datos['descripcion'],
			':precio' => $datos['precio'],
			':estado' => $datos['estado'],
		]);
		$id = (int) $con->lastInsertId();

		self::guardarImagenes($id);

		return [
			'status' => 'success',
			'message' => 'Producto creado',
			'id' => $id,
			'imagenes' => self::imagenesDe($id),
		];
	}

	public static function updateProducto(): array
	{
		if ($error = Acl::guard(self::SECCION)) {
			return $error;
		}

		$id = (int) ($_POST['id'] ?? 0);
		if ($id <= 0) {
			return ['status' => 'error', 'message' => 'Producto no válido'];
		}

		$datos = self::readInput();
		if (isset($datos['status'])) {
			return $datos;
		}
		if (self::urlEnUso($datos['url'], $id)) {
			return ['status' => 'error', 'message' => 'La URL ya está en uso'];
		}

		$con = Conexion::getInstance();
		$sth = $con->prepare(
			'UPDATE productos SET id_categoria = :categoria, nombre_producto = :nombre,
			 url_producto = :url, descripcion_producto = :descripcion,
			 precio_producto = :precio, estado_producto = :estado
			 WHERE id_producto = :id'
		);
		$sth->execute([
			':categoria' => $datos['categoria'],
			':nombre' => $datos['nombre'],
			':url' => $datos['url'],
			':descripcion' => $datos['descripcion'],
			':precio' => $datos['precio'],
			':estado' => $datos['estado'],
			':id' => $id,
		]);

		self::guardarImagenes($id);

		return [
			'status' => 'success',
			'message' => 'Producto actualizado',
			'id' => $id,
			'imagenes' => self::imagenesDe($id),
		];
	}

	public static function deleteProducto(): array
	{
		if ($error = Acl::guard(self::SECCION)) {
			return $error;
		}

		$id = (int) ($_POST['id'] ?? 0);
		if ($id <= 0) {
			return ['status' => 'error', 'message' => 'Producto no válido'];
		}

		$con = Conexion::getInstance();
		$sth = $con->prepare('SELECT url_imagen FROM productos_imagenes WHERE id_producto = :id');
		$sth->execute([':id' => $id]);
		foreach ($sth->fetchAll() as $img) {
			self::borrarArchivo($img['url_imagen']);
		}

		$sth = $con->prepare('DELETE FROM productos WHERE id_producto = :id');
		$sth->execute([':id' => $id]);

		return ['status' => 'success', 'message' => 'Producto eliminado'];
	}

	public static function uploadImagenes(): array
	{
		if ($error = Acl::guard(self::SECCION)) {
			return $error;
		}

		$id = (int) ($_POST['id'] ?? 0);
		if ($id <= 0) {
			return ['status' => 'error', 'message' => 'Producto no válido'];
		}

		$subidas = self::guardarImagenes($id);
		if (empty($subidas)) {
			return ['status' => 'error', 'message' => 'No se pudo subir ninguna imagen'];
		}

		return [
			'status' => 'success',
			'message' => count($subidas) . ' imagen(es) subida(s)',
			'imagenes' => self::imagenesDe($id),
		];
	}

	public static function deleteImagen(): array
	{
		if ($error = Acl::guard(self::SECCION)) {
			return $error;
		}

		$id_imagen = (int) ($_POST['id_imagen'] ?? 0);
		if ($id_imagen <= 0) {
			return ['status' => 'error', 'message' => 'Imagen no válida'];
		}

		$con = Conexion::getInstance();
		$sth = $con->prepare('SELECT id_producto, url_imagen FROM productos_imagenes WHERE id_imagen = :id');
		$sth->execute([':id' => $id_imagen]);
		$img = $sth->fetch();
		if (!$img) {
			return ['status' => 'error', 'message' => 'Imagen no encontrada'];
		}

		$sth = $con->prepare('DELETE FROM productos_imagenes WHERE id_imagen = :id');
		$sth->execute([':id' => $id_imagen]);
		self::borrarArchivo($img['url_imagen']);

		return [
			'status' => 'success',
			'message' => 'Imagen eliminada',
			'imagenes' => self::imagenesDe((int) $img['id_producto']),
		];
	}

	/* --------------------------------------------------------------- Público */

	public static function listPublic(): array
	{
		$con = Conexion::getInstance();
		$sth = $con->query(
			"SELECT p.id_producto, p.nombre_producto, p.url_producto, p.descripcion_producto,
			        p.precio_producto, c.nombre_categoria, c.url_categoria,
			        (SELECT i.url_imagen FROM productos_imagenes i
			          WHERE i.id_producto = p.id_producto ORDER BY i.orden_imagen, i.id_imagen LIMIT 1) AS url_imagen
			 FROM productos p
			 LEFT JOIN categorias c ON c.id_categoria = p.id_categoria
			 WHERE p.estado_producto = 'activo'
			 ORDER BY p.nombre_producto ASC"
		);

		$productos = $sth->fetchAll();
		foreach ($productos as &$producto) {
			$producto['url_imagen'] = $producto['url_imagen'] ? self::urlPublica($producto['url_imagen']) : null;
		}
		unset($producto);

		return ['status' => 'success', 'productos' => $productos];
	}

	public static function getByUrlPublic(): array
	{
		$url = trim($_GET['url'] ?? '');
		if ($url === '') {
			return ['status' => 'error', 'message' => 'URL requerida'];
		}

		$con = Conexion::getInstance();
		$sth = $con->prepare(
			"SELECT p.*, c.nombre_categoria, c.url_categoria
			 FROM productos p
			 LEFT JOIN categorias c ON c.id_categoria = p.id_categoria
			 WHERE p.url_producto = :url AND p.estado_producto = 'activo'"
		);
		$sth->execute([':url' => $url]);
		$producto = $sth->fetch();
		if (!$producto) {
			http_response_code(404);
			return ['status' => 'error', 'message' => 'Producto no encontrado'];
		}

		$vars = $con->prepare(
			'SELECT id_variante, talla_variante, corte_variante, sku_variante, stock_variante
			 FROM productos_variantes WHERE id_producto = :id'
		);
		$vars->execute([':id' => $producto['id_producto']]);

		return [
			'status' => 'success',
			'producto' => $producto,
			'imagenes' => self::imagenesDe((int) $producto['id_producto']),
			'variantes' => $vars->fetchAll(),
		];
	}

	/* --------------------------------------------------------------- Helpers */

	private static function imagenesDe(int $id_producto): array
	{
		$con = Conexion::getInstance();
		$sth = $con->prepare(
			'SELECT id_imagen, url_imagen, orden_imagen FROM productos_imagenes
			 WHERE id_producto = :id ORDER BY orden_imagen, id_imagen'
		);
		$sth->execute([':id' => $id_producto]);

		$imagenes = $sth->fetchAll();
		foreach ($imagenes as &$imagen) {
			$imagen['archivo_imagen'] = $imagen['url_imagen'];
			$imagen['url_imagen'] = self::urlPublica($imagen['url_imagen']);
		}
		unset($imagen);

		return $imagenes;
	}

	private static function urlPublica(string $archivo): string
	{
		return IMGS . 'productos/' . $archivo;
	}

	private static function guardarImagenes(int $id_producto): array
	{
		if (empty($_FILES['imagenes']['name'][0])) {
			return [];
		}

		$con = Conexion::getInstance();
		$sth = $con->prepare('SELECT COALESCE(MAX(orden_imagen), 0) FROM productos_imagenes WHERE id_producto = :id');
		$sth->execute([':id' => $id_producto]);
		$orden = (int) $sth->fetchColumn();

		$insert = $con->prepare(
			'INSERT INTO productos_imagenes (id_producto, url_imagen, orden_imagen)
			 VALUES (:id, :url, :orden)'
		);

		$subidas = [];
		$total = count($_FILES['imagenes']['name']);
		for ($i = 0; $i < $total; $i++) {
			if ((int) $_FILES['imagenes']['error'][$i] !== UPLOAD_ERR_OK) {
				continue;
			}
			$archivo = \uploadImgs(
				$_FILES['imagenes']['name'][$i],
				$_FILES['imagenes']['tmp_name'][$i],
				PUBLIC_ROOT_HOST . self::DIR_IMGS
			);
			if ($archivo === '') {
				continue;
			}
			$orden++;
			$insert->execute([':id' => $id_producto, ':url' => $archivo, ':orden' => $orden]);
			$subidas[] = $archivo;
		}

		return $subidas;
	}

	private static function borrarArchivo(string $archivo): void
	{
		$ruta = PUBLIC_ROOT_HOST . self::DIR_IMGS . basename($archivo);
		if ($archivo !== '' && is_file($ruta)) {
			unlink($ruta);
		}
	}

	private static function readInput(): array
	{
		$nombre = trim((string) ($_POST['nombre'] ?? ''));
		$url = trim((string) ($_POST['url'] ?? ''));
		$descripcion = trim((string) ($_POST['descripcion'] ?? ''));
		$precio = (float) str_replace(',', '.', (string) ($_POST['precio'] ?? '0'));
		$categoria = (int) ($_POST['id_categoria'] ?? 0);
		$estado = ($_POST['estado'] ?? 'activo') === 'inactivo' ? 'inactivo' : 'activo';

		if ($nombre === '') {
			return ['status' => 'error', 'message' => 'El nombre es obligatorio'];
		}
		if ($precio <= 0) {
			return ['status' => 'error', 'message' => 'El precio debe ser mayor a 0'];
		}
		if ($categoria <= 0) {
			return ['status' => 'error', 'message' => 'Selecciona una categoría'];
		}

		$url = \url_friend($url !== '' ? $url : $nombre);
		if ($url === '') {
			return ['status' => 'error', 'message' => 'La URL no es válida'];
		}

		return [
			'nombre' => $nombre,
			'url' => $url,
			'descripcion' => $descripcion,
			'precio' => number_format($precio, 2, '.', ''),
			'categoria' => $categoria,
			'estado' => $estado,
		];
	}

	private static function urlEnUso(string $url, int $id): bool
	{
		$con = Conexion::getInstance();
		$sth = $con->prepare('SELECT COUNT(*) FROM productos WHERE url_producto = :url AND id_producto <> :id');
		$sth->execute([':url' => $url, ':id' => $id]);
		return (int) $sth->fetchColumn() > 0;
	}
}
