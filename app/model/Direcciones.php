<?php

namespace Develoweb\App\Model;

use Develoweb\App\Utilities\JwtHelper;
use PDO;
use Throwable;

class Direcciones
{
    private static function usuario(): ?int
    {
        $id = JwtHelper::bearerUserId();
        if (!$id) http_response_code(401);
        return $id ?: null;
    }

    private static function lista(PDO $con, int $userId): array
    {
        $sth = $con->prepare(
            'SELECT id_direccion_envio AS id, nombre_direccion_cliente AS nombre,
                    direccion_envio_cliente AS direccion, ciudad_envio_cliente AS ciudad,
                    referencia_envio_cliente AS referencia, telefono_envio_cliente AS telefono,
                    predeterminada_envio_cliente AS principal
             FROM direcciones_envio_clientes
             WHERE id_usuario_sistema = :usuario AND activa_envio_cliente = 1
             ORDER BY predeterminada_envio_cliente DESC, id_direccion_envio DESC'
        );
        $sth->execute([':usuario' => $userId]);
        return $sth->fetchAll();
    }

    public static function mias(): array
    {
        if (!$userId = self::usuario()) return ['status' => 'error', 'message' => 'No autorizado'];
        return ['status' => 'success', 'direcciones' => self::lista(Conexion::getInstance(), $userId)];
    }

    public static function guardar(): array
    {
        if (!$userId = self::usuario()) return ['status' => 'error', 'message' => 'No autorizado'];
        $id = (int) ($_POST['id'] ?? 0);
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $direccion = trim((string) ($_POST['direccion'] ?? ''));
        $ciudad = trim((string) ($_POST['ciudad'] ?? ''));
        $referencia = trim((string) ($_POST['referencia'] ?? ''));
        $telefono = trim((string) ($_POST['telefono'] ?? ''));
        $principal = !empty($_POST['principal']);
        if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 80
            || mb_strlen($direccion) < 5 || mb_strlen($direccion) > 255
            || mb_strlen($ciudad) < 2 || mb_strlen($ciudad) > 100
            || mb_strlen($referencia) > 255 || mb_strlen($telefono) > 30) {
            http_response_code(422);
            return ['status' => 'error', 'message' => 'Revisa el nombre, la dirección y el distrito.'];
        }
        $con = Conexion::getInstance();
        try {
            $con->beginTransaction();
            if ($id > 0) {
                $sth = $con->prepare('SELECT predeterminada_envio_cliente FROM direcciones_envio_clientes WHERE id_direccion_envio = ? AND id_usuario_sistema = ? AND activa_envio_cliente = 1 FOR UPDATE');
                $sth->execute([$id, $userId]);
                $anterior = $sth->fetch();
                if (!$anterior) {
                    $con->rollBack();
                    http_response_code(404);
                    return ['status' => 'error', 'message' => 'Dirección no encontrada'];
                }
                $principal = $principal || (bool) $anterior['predeterminada_envio_cliente'];
                $sth = $con->prepare('SELECT 1 FROM pedidos WHERE id_direccion_envio = ? LIMIT 1');
                $sth->execute([$id]);
                if ($sth->fetchColumn()) {
                    // Preservar la dirección que figura en pedidos anteriores.
                    $con->prepare('UPDATE direcciones_envio_clientes SET activa_envio_cliente = 0, predeterminada_envio_cliente = 0 WHERE id_direccion_envio = ?')->execute([$id]);
                    $id = 0;
                }
            }
            $sth = $con->prepare('SELECT COUNT(*) FROM direcciones_envio_clientes WHERE id_usuario_sistema = ? AND activa_envio_cliente = 1');
            $sth->execute([$userId]);
            if ((int) $sth->fetchColumn() === 0) $principal = true;
            if ($principal) $con->prepare('UPDATE direcciones_envio_clientes SET predeterminada_envio_cliente = 0 WHERE id_usuario_sistema = ?')->execute([$userId]);

            $params = [$nombre, $direccion, $ciudad, $referencia ?: null, $telefono ?: null, (int) $principal];
            if ($id > 0) {
                $con->prepare('UPDATE direcciones_envio_clientes SET nombre_direccion_cliente = ?, direccion_envio_cliente = ?, ciudad_envio_cliente = ?, referencia_envio_cliente = ?, telefono_envio_cliente = ?, predeterminada_envio_cliente = ? WHERE id_direccion_envio = ? AND id_usuario_sistema = ?')
                    ->execute([...$params, $id, $userId]);
            } else {
                $con->prepare('INSERT INTO direcciones_envio_clientes (nombre_direccion_cliente, direccion_envio_cliente, ciudad_envio_cliente, referencia_envio_cliente, telefono_envio_cliente, predeterminada_envio_cliente, id_usuario_sistema) VALUES (?, ?, ?, ?, ?, ?, ?)')
                    ->execute([...$params, $userId]);
                $id = (int) $con->lastInsertId();
            }
            $con->commit();
            return ['status' => 'success', 'id' => $id, 'direcciones' => self::lista($con, $userId)];
        } catch (Throwable $e) {
            if ($con->inTransaction()) $con->rollBack();
            error_log('Kloset · dirección: ' . $e->getMessage());
            http_response_code(500);
            return ['status' => 'error', 'message' => 'No se pudo guardar la dirección'];
        }
    }

    public static function eliminar(): array
    {
        if (!$userId = self::usuario()) return ['status' => 'error', 'message' => 'No autorizado'];
        $id = (int) ($_POST['id'] ?? 0);
        $con = Conexion::getInstance();
        $con->beginTransaction();
        try {
            $sth = $con->prepare('SELECT predeterminada_envio_cliente FROM direcciones_envio_clientes WHERE id_direccion_envio = ? AND id_usuario_sistema = ? AND activa_envio_cliente = 1 FOR UPDATE');
            $sth->execute([$id, $userId]);
            $direccion = $sth->fetch();
            if (!$direccion) {
                $con->rollBack();
                http_response_code(404);
                return ['status' => 'error', 'message' => 'Dirección no encontrada'];
            }
            $con->prepare('UPDATE direcciones_envio_clientes SET activa_envio_cliente = 0, predeterminada_envio_cliente = 0 WHERE id_direccion_envio = ?')->execute([$id]);
            if ($direccion['predeterminada_envio_cliente']) {
                $sth = $con->prepare('SELECT id_direccion_envio FROM direcciones_envio_clientes WHERE id_usuario_sistema = ? AND activa_envio_cliente = 1 ORDER BY id_direccion_envio DESC LIMIT 1');
                $sth->execute([$userId]);
                if ($siguiente = $sth->fetchColumn()) $con->prepare('UPDATE direcciones_envio_clientes SET predeterminada_envio_cliente = 1 WHERE id_direccion_envio = ?')->execute([$siguiente]);
            }
            $con->commit();
            return ['status' => 'success', 'direcciones' => self::lista($con, $userId)];
        } catch (Throwable $e) {
            $con->rollBack();
            error_log('Kloset · eliminar dirección: ' . $e->getMessage());
            http_response_code(500);
            return ['status' => 'error', 'message' => 'No se pudo eliminar la dirección'];
        }
    }

    public static function principal(): array
    {
        if (!$userId = self::usuario()) return ['status' => 'error', 'message' => 'No autorizado'];
        $id = (int) ($_POST['id'] ?? 0);
        $con = Conexion::getInstance();
        $con->beginTransaction();
        try {
            $sth = $con->prepare('SELECT 1 FROM direcciones_envio_clientes WHERE id_direccion_envio = ? AND id_usuario_sistema = ? AND activa_envio_cliente = 1');
            $sth->execute([$id, $userId]);
            if (!$sth->fetchColumn()) {
                $con->rollBack();
                http_response_code(404);
                return ['status' => 'error', 'message' => 'Dirección no encontrada'];
            }
            $con->prepare('UPDATE direcciones_envio_clientes SET predeterminada_envio_cliente = 0 WHERE id_usuario_sistema = ?')->execute([$userId]);
            $con->prepare('UPDATE direcciones_envio_clientes SET predeterminada_envio_cliente = 1 WHERE id_direccion_envio = ?')->execute([$id]);
            $con->commit();
            return ['status' => 'success', 'direcciones' => self::lista($con, $userId)];
        } catch (Throwable $e) {
            $con->rollBack();
            error_log('Kloset · dirección principal: ' . $e->getMessage());
            http_response_code(500);
            return ['status' => 'error', 'message' => 'No se pudo actualizar la dirección'];
        }
    }
}
