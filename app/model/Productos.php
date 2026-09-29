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
		$sth = $con->prepare(
			'SELECT p.*, COALESCE(SUM(v.stock_variante), 0) AS stock_total,
			        COUNT(v.id_variante) AS total_variantes
			 FROM productos p
			 LEFT JOIN productos_variantes v ON v.id_producto = p.id_producto
			 WHERE p.id_producto = :id
			 GROUP BY p.id_producto'
		);
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
		if ($error = self::validarImagenes()) return $error;

		$datos = self::readInput();
		if (isset($datos['status'])) {
			return $datos;
		}
		if (self::urlEnUso($datos['url'], 0)) {
			return ['status' => 'error', 'message' => 'La URL ya está en uso'];
		}

		$inventario = self::readInventarioInicial();
		if (isset($inventario['status'])) {
			return $inventario;
		}

		$con = Conexion::getInstance();
		$con->beginTransaction();
		try {
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
			self::crearVarianteInicial($con, $id, $datos['url'], $inventario);
			$con->commit();
		} catch (\Throwable $e) {
			if ($con->inTransaction()) {
				$con->rollBack();
			}
			error_log('KLOSET crear producto: ' . $e->getMessage());
			return ['status' => 'error', 'message' => 'No se pudo crear el producto'];
		}

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
		if ($error = self::validarImagenes()) return $error;

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
		$ajusteStock = self::readAjusteStockTotal();
		if (isset($ajusteStock['status'])) {
			return $ajusteStock;
		}

		$con = Conexion::getInstance();
		$con->beginTransaction();
		try {
			$existe = $con->prepare('SELECT 1 FROM productos WHERE id_producto = ? FOR UPDATE');
			$existe->execute([$id]);
			if (!$existe->fetchColumn()) {
				$con->rollBack();
				return ['status' => 'error', 'message' => 'Producto no encontrado'];
			}
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
			if ($ajusteStock['aplicar']) {
				self::ajustarStockTotal($con, $id, $ajusteStock['stock']);
			}
			$con->commit();
		} catch (\Throwable $e) {
			if ($con->inTransaction()) {
				$con->rollBack();
			}
			error_log('KLOSET actualizar producto: ' . $e->getMessage());
			return ['status' => 'error', 'message' => 'No se pudo actualizar el producto'];
		}

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
		$con->beginTransaction();
		try {
			$existe = $con->prepare('SELECT 1 FROM productos WHERE id_producto = ? FOR UPDATE');
			$existe->execute([$id]);
			if (!$existe->fetchColumn()) { $con->rollBack(); return ['status' => 'error', 'message' => 'Producto no encontrado']; }
			foreach (['pedidos_items', 'carritos_items'] as $tabla) {
				$usos = $con->prepare("SELECT COUNT(*) FROM $tabla i INNER JOIN productos_variantes v ON v.id_variante = i.id_variante WHERE v.id_producto = ?");
				$usos->execute([$id]);
				if ($usos->fetchColumn()) { $con->rollBack(); return ['status' => 'error', 'message' => 'Este producto tiene pedidos o bolsas asociados. Desactívalo para retirarlo del catálogo.']; }
			}
			$sth = $con->prepare('SELECT url_imagen FROM productos_imagenes WHERE id_producto = ?');
			$sth->execute([$id]);
			$imagenes = $sth->fetchAll();
			$con->prepare('DELETE FROM productos WHERE id_producto = ?')->execute([$id]);
			$con->commit();
		} catch (\Throwable $e) {
			$con->rollBack();
			error_log('KLOSET eliminar producto: ' . $e->getMessage());
			return ['status' => 'error', 'message' => 'No se pudo eliminar el producto. Sus imágenes se conservaron.'];
		}
		foreach ($imagenes as $img) self::borrarArchivo($img['url_imagen']);

		return ['status' => 'success', 'message' => 'Producto eliminado'];
	}

	public static function uploadImagenes(): array
	{
		if ($error = Acl::guard(self::SECCION)) {
			return $error;
		}
		if ($error = self::validarImagenes()) return $error;

		$id = (int) ($_POST['id'] ?? 0);
		if ($id <= 0) {
			return ['status' => 'error', 'message' => 'Producto no válido'];
		}
		$con = Conexion::getInstance();
		$existe = $con->prepare('SELECT 1 FROM productos WHERE id_producto = :id');
		$existe->execute([':id' => $id]);
		if (!$existe->fetchColumn()) {
			return ['status' => 'error', 'message' => 'Producto no encontrado'];
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

	private static function validarImagenes(): ?array
	{
		if (!isset($_FILES['imagenes'])) return null;
		$f = $_FILES['imagenes'];
		if (!is_array($f['name'] ?? null) || count($f['name']) > 20) return ['status'=>'error', 'message'=>'Envía hasta 20 imágenes por operación'];
		foreach ($f['name'] as $i => $nombre) {
			if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
			$info = is_uploaded_file($f['tmp_name'][$i]) ? @getimagesize($f['tmp_name'][$i]) : false;
			if ($f['error'][$i] !== UPLOAD_ERR_OK || $f['size'][$i] > 10 * 1024 * 1024 || !$info
				|| !in_array($info['mime'] ?? '', ['image/jpeg','image/png','image/gif','image/webp'], true)
				|| !in_array(strtolower(pathinfo($nombre, PATHINFO_EXTENSION)), ['jpg','jpeg','png','gif','webp'], true)) return ['status'=>'error', 'message'=>'Usa imágenes JPEG, PNG, GIF o WebP válidas de hasta 10 MB'];
		}
		return null;
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
			$tmp = $_FILES['imagenes']['tmp_name'][$i];
			$size = (int) $_FILES['imagenes']['size'][$i];
			$info = is_uploaded_file($tmp) ? getimagesize($tmp) : false;
			if ($size <= 0 || $size > 10 * 1024 * 1024 || !$info
				|| !in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
				continue;
			}
			$archivo = \uploadImgs(
				$_FILES['imagenes']['name'][$i],
				$tmp,
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
		if (!is_numeric(str_replace(',', '.', (string)($_POST['precio'] ?? '0')))) return ['status'=>'error', 'message'=>'El precio debe ser numérico'];
		$categoria = (int) ($_POST['id_categoria'] ?? 0);
		$estado = ($_POST['estado'] ?? 'activo') === 'inactivo' ? 'inactivo' : 'activo';

		if ($nombre === '') {
			return ['status' => 'error', 'message' => 'El nombre es obligatorio'];
		}
		if (mb_strlen($nombre) > 150 || mb_strlen($url) > 180 || !is_finite($precio) || $precio > 99999999.99) return ['status' => 'error', 'message' => 'Nombre, URL o precio exceden el límite permitido'];
		if ($precio <= 0) {
			return ['status' => 'error', 'message' => 'El precio debe ser mayor a 0'];
		}
		if ($categoria <= 0) {
			return ['status' => 'error', 'message' => 'Selecciona una categoría'];
		}
		$cat = Conexion::getInstance()->prepare('SELECT 1 FROM categorias WHERE id_categoria = ?');
		$cat->execute([$categoria]);
		if (!$cat->fetchColumn()) return ['status' => 'error', 'message' => 'La categoría no existe'];

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

	private static function readInventarioInicial(): array
	{
		$tallas = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
		$cortes = ['Slim', 'Regular', 'Oversize'];
		$talla = (string) ($_POST['talla'] ?? 'M');
		$corte = (string) ($_POST['corte'] ?? 'Regular');
		$stockRaw = $_POST['stock'] ?? null;

		if ($stockRaw === null || $stockRaw === '' || filter_var($stockRaw, FILTER_VALIDATE_INT) === false) {
			return ['status' => 'error', 'message' => 'Ingresa el stock como un número entero'];
		}
		$stock = (int) $stockRaw;
		if ($stock < 0 || $stock > 2147483647) {
			return ['status' => 'error', 'message' => 'El stock no puede ser negativo'];
		}
		if (!in_array($talla, $tallas, true)) {
			return ['status' => 'error', 'message' => 'Talla no válida'];
		}
		if (!in_array($corte, $cortes, true)) {
			return ['status' => 'error', 'message' => 'Corte no válido'];
		}

		return ['talla' => $talla, 'corte' => $corte, 'stock' => $stock];
	}

	private static function readAjusteStockTotal(): array
	{
		if (!array_key_exists('stock_total', $_POST)) {
			return ['aplicar' => false];
		}

		$stockRaw = $_POST['stock_total'];
		if ($stockRaw === '' || filter_var($stockRaw, FILTER_VALIDATE_INT) === false) {
			return ['status' => 'error', 'message' => 'Ingresa el stock total como un número entero'];
		}
		$stock = (int) $stockRaw;
		if ($stock < 0 || $stock > 2147483647) {
			return ['status' => 'error', 'message' => 'El stock total no puede ser negativo'];
		}

		return ['aplicar' => true, 'stock' => $stock];
	}

	/** Distribuye el total solicitado entre las variantes existentes del producto. */
	private static function ajustarStockTotal(PDO $con, int $idProducto, int $stockTotal): void
	{
		$sth = $con->prepare(
			'SELECT id_variante FROM productos_variantes
			 WHERE id_producto = ? ORDER BY id_variante FOR UPDATE'
		);
		$sth->execute([$idProducto]);
		$variantes = $sth->fetchAll();
		$cantidad = count($variantes);
		if ($cantidad === 0) {
			throw new \RuntimeException('El producto no tiene variantes para actualizar el stock');
		}

		$base = intdiv($stockTotal, $cantidad);
		$resto = $stockTotal % $cantidad;
		$actualizar = $con->prepare('UPDATE productos_variantes SET stock_variante = ? WHERE id_variante = ?');
		foreach ($variantes as $indice => $variante) {
			$actualizar->execute([$base + ($indice < $resto ? 1 : 0), $variante['id_variante']]);
		}
	}

	private static function crearVarianteInicial(PDO $con, int $idProducto, string $url, array $inventario): void
	{
		$sku = strtoupper(substr($url, 0, 40) . '-' . $inventario['talla'] . '-' . substr($inventario['corte'], 0, 3));
		$sku = substr(preg_replace('/[^A-Z0-9\-]/', '', $sku) ?: 'SKU', 0, 44);
		$base = $sku;
		$n = 1;
		$ocupado = $con->prepare('SELECT COUNT(*) FROM productos_variantes WHERE sku_variante = ?');
		do {
			$ocupado->execute([$sku]);
			if ((int) $ocupado->fetchColumn() === 0) {
				break;
			}
			$n++;
			$sku = substr($base, 0, 44) . '-' . $n;
		} while ($n < 50);

		$sth = $con->prepare(
			'INSERT INTO productos_variantes (id_producto, talla_variante, corte_variante, sku_variante, stock_variante)
			 VALUES (:producto, :talla, :corte, :sku, :stock)'
		);
		$sth->execute([
			':producto' => $idProducto,
			':talla' => $inventario['talla'],
			':corte' => $inventario['corte'],
			':sku' => $sku,
			':stock' => $inventario['stock'],
		]);
	}

	private static function urlEnUso(string $url, int $id): bool
	{
		$con = Conexion::getInstance();
		$sth = $con->prepare('SELECT COUNT(*) FROM productos WHERE url_producto = :url AND id_producto <> :id');
		$sth->execute([':url' => $url, ':id' => $id]);
		return (int) $sth->fetchColumn() > 0;
	}
}
