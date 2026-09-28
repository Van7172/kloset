<?php

namespace Develoweb\App\Model;

use Develoweb\App\Utilities\JwtHelper;

class CuentaApi
{
    private static function id(): ?int
    {
        $id = JwtHelper::bearerUserId();
        if (!$id) http_response_code(401);
        return $id ?: null;
    }

    public static function datos(): array
    {
        if (!$id = self::id()) return ['status' => 'error', 'message' => 'No autorizado'];
        $con = Conexion::getInstance();
        $sth = $con->prepare('SELECT nombre_usuario_sistema AS nombre, correo_usuario_sistema AS correo, telefono_usuario_sistema AS telefono, avisos_pedidos_usuario AS avisos_pedidos, avisos_novedades_usuario AS avisos_novedades, fecha_creacion FROM sistema_usuarios WHERE id_usuario_sistema = ? AND estado_usuario_sistema = \'activo\'');
        $sth->execute([$id]);
        return ['status' => 'success', 'datos' => $sth->fetch() ?: null];
    }

    public static function guardar(): array
    {
        if (!$id = self::id()) return ['status' => 'error', 'message' => 'No autorizado'];
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $telefono = trim((string) ($_POST['telefono'] ?? ''));
        if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 120 || mb_strlen($telefono) > 30) {
            http_response_code(422);
            return ['status' => 'error', 'message' => 'Revisa el nombre y el teléfono.'];
        }
        $con = Conexion::getInstance();
        $con->prepare('UPDATE sistema_usuarios SET nombre_usuario_sistema = ?, telefono_usuario_sistema = ?, avisos_pedidos_usuario = ?, avisos_novedades_usuario = ? WHERE id_usuario_sistema = ?')
            ->execute([$nombre, $telefono ?: null, !empty($_POST['avisos_pedidos']) ? 1 : 0, !empty($_POST['avisos_novedades']) ? 1 : 0, $id]);
        return self::datos();
    }

    public static function cambiarClave(): array
    {
        if (!$id = self::id()) return ['status' => 'error', 'message' => 'No autorizado'];
        $actual = (string) ($_POST['actual'] ?? '');
        $nueva = (string) ($_POST['nueva'] ?? '');
        if (!AuthApi::cumplePoliticaContrasena($nueva)) {
            http_response_code(422);
            return ['status' => 'error', 'message' => AuthApi::mensajePoliticaContrasena()];
        }
        $con = Conexion::getInstance();
        $sth = $con->prepare('SELECT contrasena_usuario_sistema FROM sistema_usuarios WHERE id_usuario_sistema = ?');
        $sth->execute([$id]);
        $hash = $sth->fetchColumn();
        if (!$hash || !password_verify($actual, $hash)) {
            http_response_code(422);
            return ['status' => 'error', 'message' => 'La contraseña actual no coincide.'];
        }
        $con->prepare('UPDATE sistema_usuarios SET contrasena_usuario_sistema = ? WHERE id_usuario_sistema = ?')->execute([password_hash($nueva, PASSWORD_DEFAULT), $id]);
        return ['status' => 'success', 'message' => 'Contraseña actualizada'];
    }

    public static function avisos(): array
    {
        if (!$id = self::id()) return ['status' => 'error', 'message' => 'No autorizado'];
        $con = Conexion::getInstance();
        $sth = $con->prepare('SELECT id_notificacion, id_pedido, mensaje_notificacion, leido_notificacion, fecha_creacion FROM notificaciones WHERE id_usuario_sistema = ? ORDER BY fecha_creacion DESC, id_notificacion DESC LIMIT 10');
        $sth->execute([$id]);
        return ['status' => 'success', 'avisos' => $sth->fetchAll()];
    }
}
