<?php

namespace Develoweb\App\Model;

use Throwable;

class Contactos
{
    public static function enviar(): array
    {
        // Campo invisible para descartar envíos automatizados simples.
        if (trim((string) ($_POST['sitio'] ?? '')) !== '') {
            return ['status' => 'success', 'message' => 'Consulta recibida'];
        }

        $tema = trim((string) ($_POST['tema'] ?? 'otro'));
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $correo = trim((string) ($_POST['correo'] ?? ''));
        $telefono = trim((string) ($_POST['telefono'] ?? ''));
        $mensaje = trim((string) ($_POST['mensaje'] ?? ''));

        if (!in_array($tema, ['talla', 'pedido', 'otro'], true)
            || mb_strlen($nombre) < 2 || mb_strlen($nombre) > 120
            || !filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 150
            || mb_strlen($telefono) > 30
            || mb_strlen($mensaje) < 10 || mb_strlen($mensaje) > 3000) {
            http_response_code(422);
            return ['status' => 'error', 'message' => 'Revisa tu nombre, correo y mensaje (mínimo 10 caracteres).'];
        }

        $con = Conexion::getInstance();
        try {
            $con->beginTransaction();
            $insert = $con->prepare(
                'INSERT INTO contactos (tema_contacto, nombre_contacto, correo_contacto, telefono_contacto, mensaje_contacto)
                 VALUES (:tema, :nombre, :correo, :telefono, :mensaje)'
            );
            $insert->execute([
                ':tema' => $tema,
                ':nombre' => $nombre,
                ':correo' => $correo,
                ':telefono' => $telefono !== '' ? $telefono : null,
                ':mensaje' => $mensaje,
            ]);
            $con->commit();
        } catch (Throwable $e) {
            if ($con->inTransaction()) $con->rollBack();
            error_log('Kloset · consulta de contacto: ' . $e->getMessage());
            http_response_code(500);
            return ['status' => 'error', 'message' => 'No pudimos guardar tu consulta. Inténtalo de nuevo.'];
        }

        return ['status' => 'success', 'message' => 'Consulta recibida'];
    }
}
