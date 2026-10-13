<?php

namespace Develoweb\App\Model;

class MiCuentaAdmin
{
    public static function datos(): array
    {
        if ($error = Acl::guard('mi-cuenta')) return $error;
        $sth = Conexion::getInstance()->prepare('SELECT nombre_usuario_sistema,correo_usuario_sistema FROM sistema_usuarios WHERE id_usuario_sistema=?');
        $sth->execute([Acl::usuario()->getId()]);
        return $sth->fetch() ?: [];
    }

    public static function guardar(): array
    {
        if ($error = Acl::guard('mi-cuenta')) return $error;
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $correo = mb_strtolower(trim((string) ($_POST['correo'] ?? '')));
        if ($nombre === '' || mb_strlen($nombre)>120 || !filter_var($correo,FILTER_VALIDATE_EMAIL) || mb_strlen($correo)>150) return ['status'=>'error','message'=>'Revisa nombre y correo'];
        try {
            $sth = Conexion::getInstance()->prepare('UPDATE sistema_usuarios SET nombre_usuario_sistema=?,correo_usuario_sistema=? WHERE id_usuario_sistema=?');
            $sth->execute([$nombre,$correo,Acl::usuario()->getId()]);
            return ['status'=>'success','message'=>'Cuenta actualizada. Recarga para ver los cambios.'];
        } catch (\PDOException $e) {
            if ($e->getCode()==='23000') return ['status'=>'error','message'=>'El correo ya pertenece a otra cuenta'];
            throw $e;
        }
    }

    public static function cambiarClave(): array
    {
        if ($error = Acl::guard('mi-cuenta')) return $error;
        $actual = (string) ($_POST['actual'] ?? '');
        $nueva = (string) ($_POST['nueva'] ?? '');
        if (strlen($nueva)<8 || strlen($nueva)>128 || !preg_match('/[A-Z]/',$nueva) || !preg_match('/[a-z]/',$nueva) || !preg_match('/\d/',$nueva) || !preg_match('/[^A-Za-z0-9]/',$nueva)) return ['status'=>'error','message'=>'La nueva clave necesita 8 caracteres, mayúscula, minúscula, número y símbolo'];
        $con = Conexion::getInstance();
        $sth = $con->prepare('SELECT contrasena_usuario_sistema FROM sistema_usuarios WHERE id_usuario_sistema=?');
        $sth->execute([Acl::usuario()->getId()]);
        $hash = (string) $sth->fetchColumn();
        if (!password_verify($actual,$hash)) return ['status'=>'error','message'=>'La contraseña actual no coincide'];
        if (password_verify($nueva,$hash)) return ['status'=>'error','message'=>'La nueva contraseña debe ser diferente'];
        $con->prepare('UPDATE sistema_usuarios SET contrasena_usuario_sistema=? WHERE id_usuario_sistema=?')->execute([password_hash($nueva,PASSWORD_DEFAULT),Acl::usuario()->getId()]);
        return ['status'=>'success','message'=>'Contraseña actualizada'];
    }
}
