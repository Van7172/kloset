<?php

namespace Develoweb\App\Model;

use Develoweb\App\Utilities\JwtHelper;

class Favoritos
{
    public static function mios(): array
    {
        $userId = JwtHelper::bearerUserId();
        if (!$userId) {
            http_response_code(401);
            return ['status' => 'error', 'message' => 'No autorizado'];
        }
        $con = Conexion::getInstance();
        $sth = $con->prepare(
            'SELECT p.id_producto, p.nombre_producto, p.url_producto, p.descripcion_producto,
                    p.precio_producto, p.estado_producto,
                    c.nombre_categoria,
                    (SELECT i.url_imagen FROM productos_imagenes i WHERE i.id_producto = p.id_producto ORDER BY i.orden_imagen, i.id_imagen LIMIT 1) AS url_imagen,
                    (SELECT COALESCE(SUM(v.stock_variante), 0) FROM productos_variantes v WHERE v.id_producto = p.id_producto) AS stock
             FROM favoritos_clientes f
             INNER JOIN productos p ON p.id_producto = f.id_producto
             LEFT JOIN categorias c ON c.id_categoria = p.id_categoria
             WHERE f.id_usuario_sistema = :usuario
             ORDER BY f.fecha_creacion DESC, p.id_producto DESC'
        );
        $sth->execute([':usuario' => $userId]);
        $productos = $sth->fetchAll();
        foreach ($productos as &$producto) {
            $producto['url_imagen'] = $producto['url_imagen'] ? IMGS . 'productos/' . $producto['url_imagen'] : null;
        }
        unset($producto);
        return ['status' => 'success', 'productos' => $productos];
    }

    public static function alternar(): array
    {
        $userId = JwtHelper::bearerUserId();
        if (!$userId) {
            http_response_code(401);
            return ['status' => 'error', 'message' => 'No autorizado'];
        }
        $productId = (int) ($_POST['id_producto'] ?? 0);
        $con = Conexion::getInstance();
        $sth = $con->prepare('SELECT 1 FROM favoritos_clientes WHERE id_usuario_sistema = ? AND id_producto = ?');
        $sth->execute([$userId, $productId]);
        $activo = (bool) $sth->fetchColumn();
        if ($activo) {
            $con->prepare('DELETE FROM favoritos_clientes WHERE id_usuario_sistema = ? AND id_producto = ?')->execute([$userId, $productId]);
        } else {
            $sth = $con->prepare("SELECT 1 FROM productos WHERE id_producto = ? AND estado_producto = 'activo'");
            $sth->execute([$productId]);
            if (!$sth->fetchColumn()) {
                http_response_code(404);
                return ['status' => 'error', 'message' => 'Producto no disponible'];
            }
            $con->prepare('INSERT INTO favoritos_clientes (id_usuario_sistema, id_producto) VALUES (?, ?)')->execute([$userId, $productId]);
        }
        return ['status' => 'success', 'favorito' => !$activo];
    }
}
