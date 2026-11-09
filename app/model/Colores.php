<?php

namespace Develoweb\App\Model;

class Colores
{
    public static function todos(): array
    {
        return Conexion::getInstance()->query('SELECT id_color,id_producto,nombre_color,abreviatura_color FROM productos_colores ORDER BY nombre_color')->fetchAll();
    }

    public static function deProducto(int $producto): array
    {
        $sth = Conexion::getInstance()->prepare('SELECT id_color,id_producto,nombre_color,abreviatura_color FROM productos_colores WHERE id_producto=? ORDER BY nombre_color');
        $sth->execute([$producto]);
        return $sth->fetchAll();
    }

    public static function listar(): array
    {
        if ($error = Acl::guard('variantes')) return $error;
        $producto = (int) ($_POST['id_producto'] ?? 0);
        return ['status'=>'success','colores'=>self::deProducto($producto)];
    }

    private static function normalizar(string $nombre): array
    {
        $nombre = preg_replace('/\s+/u', ' ', trim($nombre));
        return [$nombre, mb_strtolower($nombre, 'UTF-8')];
    }

    private static function abreviaturaBase(string $nombre): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombre);
        $ascii = str_replace("'", '', $ascii === false ? $nombre : $ascii);
        $ascii = strtoupper(preg_replace('/[^A-Za-z0-9]+/', ' ', $ascii));
        $palabras = preg_split('/\s+/', trim($ascii), -1, PREG_SPLIT_NO_EMPTY);
        if (!$palabras) return '';
        if (count($palabras) === 1) return substr($palabras[0], 0, 3);
        return substr($palabras[0], 0, 1) . substr($palabras[count($palabras) - 1], 0, 2);
    }

    private static function abreviaturaDisponible(int $producto, string $nombre, int $excluir = 0): string
    {
        $base = self::abreviaturaBase($nombre);
        if ($base === '') return '';
        $sth = Conexion::getInstance()->prepare(
            'SELECT abreviatura_color FROM productos_colores WHERE id_producto=? AND id_color<>?'
        );
        $sth->execute([$producto, $excluir]);
        $usadas = array_fill_keys(array_column($sth->fetchAll(), 'abreviatura_color'), true);
        if (!isset($usadas[$base])) return $base;
        for ($indice = 2; $indice < 10000; $indice++) {
            $alternativa = $base . $indice;
            if (!isset($usadas[$alternativa])) return $alternativa;
        }
        throw new \RuntimeException('No se pudo asignar una abreviatura única al color');
    }

    public static function guardar(): array
    {
        if ($error = Acl::guard('variantes')) return $error;
        $producto = (int) ($_POST['id_producto'] ?? 0);
        [$nombre,$clave] = self::normalizar((string) ($_POST['nombre'] ?? ''));
        if ($producto <= 0 || $nombre === '' || mb_strlen($nombre) > 100) return ['status'=>'error','message'=>'Selecciona un producto y escribe un nombre de color'];
        $con = Conexion::getInstance();
        $transaccionPropia = !$con->inTransaction();
        if ($transaccionPropia) $con->beginTransaction();
        try {
            $sth = $con->prepare('SELECT id_producto FROM productos WHERE id_producto=? FOR UPDATE');
            $sth->execute([$producto]);
            if (!$sth->fetchColumn()) {
                if ($transaccionPropia) $con->rollBack();
                return ['status'=>'error','message'=>'Producto no encontrado'];
            }
            $abreviatura = self::abreviaturaDisponible($producto, $nombre);
            if ($abreviatura === '') {
                if ($transaccionPropia) $con->rollBack();
                return ['status'=>'error','message'=>'El nombre del color debe incluir letras o números'];
            }
            $sth = $con->prepare('INSERT INTO productos_colores (id_producto,nombre_color,abreviatura_color,nombre_clave) VALUES (?,?,?,?)');
            $sth->execute([$producto,$nombre,$abreviatura,$clave]);
            $id = (int) $con->lastInsertId();
            if ($transaccionPropia) $con->commit();
            return ['status'=>'success','message'=>'Color registrado','id_color'=>$id,'abreviatura_color'=>$abreviatura];
        } catch (\PDOException $e) {
            if ($transaccionPropia && $con->inTransaction()) $con->rollBack();
            if ($e->getCode() === '23000') return ['status'=>'error','message'=>'Ese color ya existe para este producto'];
            throw $e;
        } catch (\Throwable $e) {
            if ($transaccionPropia && $con->inTransaction()) $con->rollBack();
            throw $e;
        }
    }

    public static function guardarConImagenes(): array
    {
        if ($error = Acl::guard('variantes')) return $error;
        if ($error = self::validarImagenes($_FILES['imagenes'] ?? null)) return ['status'=>'error','message'=>$error];
        $con = Conexion::getInstance();
        $con->beginTransaction();
        try {
            $color = self::guardar();
            if ($color['status'] !== 'success') {
                $con->rollBack();
                return $color;
            }
            $_POST['id_color'] = $color['id_color'];
            $imagenes = self::subir();
            if ($imagenes['status'] !== 'success') {
                $con->rollBack();
                return $imagenes;
            }
            $con->commit();
            return $color;
        } catch (\Throwable $e) {
            if ($con->inTransaction()) $con->rollBack();
            throw $e;
        }
    }

    public static function renombrar(): array
    {
        if ($error = Acl::guard('variantes')) return $error;
        $id = (int) ($_POST['id_color'] ?? 0);
        [$nombre,$clave] = self::normalizar((string) ($_POST['nombre'] ?? ''));
        if ($id <= 0 || $nombre === '' || mb_strlen($nombre) > 100) return ['status'=>'error','message'=>'Selecciona un color y escribe un nombre válido'];
        $con = Conexion::getInstance();
        $con->beginTransaction();
        try {
            $lock = $con->prepare('SELECT id_producto FROM productos_colores WHERE id_color=? FOR UPDATE');
            $lock->execute([$id]);
            $producto = (int) $lock->fetchColumn();
            if (!$producto) {
                $con->rollBack();
                return ['status'=>'error','message'=>'Color no encontrado'];
            }
            $con->prepare('SELECT id_producto FROM productos WHERE id_producto=? FOR UPDATE')->execute([$producto]);
            $abreviatura = self::abreviaturaDisponible($producto, $nombre, $id);
            if ($abreviatura === '') {
                $con->rollBack();
                return ['status'=>'error','message'=>'El nombre del color debe incluir letras o números'];
            }
            $con->prepare('UPDATE productos_colores SET nombre_color=?,nombre_clave=?,abreviatura_color=? WHERE id_color=?')
                ->execute([$nombre,$clave,$abreviatura,$id]);
            $variantes = $con->prepare('SELECT id_variante,id_producto,talla_variante,corte_variante FROM productos_variantes WHERE id_color=?');
            $variantes->execute([$id]);
            $actualizarSku = $con->prepare('UPDATE productos_variantes SET sku_variante=? WHERE id_variante=?');
            foreach ($variantes->fetchAll() as $variante) {
                $sku = Variantes::skuAutomatico(
                    (int) $variante['id_producto'],
                    $abreviatura,
                    $variante['talla_variante'],
                    $variante['corte_variante']
                );
                $actualizarSku->execute([$sku, $variante['id_variante']]);
            }
            $con->commit();
            return ['status'=>'success','message'=>'Color y códigos de variantes actualizados','abreviatura_color'=>$abreviatura];
        } catch (\PDOException $e) {
            if ($con->inTransaction()) $con->rollBack();
            if ($e->getCode() === '23000') return ['status'=>'error','message'=>'Ese color ya existe para este producto'];
            throw $e;
        } catch (\Throwable $e) {
            if ($con->inTransaction()) $con->rollBack();
            throw $e;
        }
    }

    public static function galeria(): array
    {
        if ($error = Acl::guard('variantes')) return $error;
        $id = (int) ($_POST['id_color'] ?? 0);
        $sth = Conexion::getInstance()->prepare('SELECT id_imagen,url_imagen,orden_imagen,principal_imagen FROM productos_imagenes WHERE id_color=? ORDER BY principal_imagen DESC,orden_imagen,id_imagen');
        $sth->execute([$id]);
        $imagenes = $sth->fetchAll();
        foreach ($imagenes as &$img) $img['url_imagen'] = IMGS.'productos/'.$img['url_imagen'];
        return ['status'=>'success','imagenes'=>$imagenes];
    }

    public static function subir(): array
    {
        if ($error = Acl::guard('variantes')) return $error;
        $id = (int) ($_POST['id_color'] ?? 0);
        $con = Conexion::getInstance();
        $sth = $con->prepare('SELECT id_producto FROM productos_colores WHERE id_color=?');
        $sth->execute([$id]);
        $producto = (int) $sth->fetchColumn();
        if (!$producto) return ['status'=>'error','message'=>'Color no encontrado'];
        $files = $_FILES['imagenes'] ?? null;
        if ($error = self::validarImagenes($files)) return ['status'=>'error','message'=>$error];
        $permitidos = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
        $sth = $con->prepare('SELECT COALESCE(MAX(orden_imagen),0),COUNT(*) FROM productos_imagenes WHERE id_color=?');
        $sth->execute([$id]);
        [$orden,$total] = $sth->fetch(\PDO::FETCH_NUM);
        $dir = PUBLIC_ROOT_HOST.'imgs/productos/';
        $transaccionPropia = !$con->inTransaction();
        $guardados = [];
        if ($transaccionPropia) $con->beginTransaction();
        try {
            foreach ($files['name'] as $i=>$nombre) {
                $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($files['tmp_name'][$i]);
                $archivo = bin2hex(random_bytes(16)).'.'.$permitidos[$mime];
                if (!move_uploaded_file($files['tmp_name'][$i], $dir.$archivo)) throw new \RuntimeException('No se pudo guardar la imagen');
                $guardados[] = $dir.$archivo;
                $orden++;
                $con->prepare('INSERT INTO productos_imagenes (id_producto,id_color,url_imagen,orden_imagen,principal_imagen) VALUES (?,?,?,?,?)')
                    ->execute([$producto,$id,$archivo,$orden,$total++ === 0 ? 1 : 0]);
            }
            if ($transaccionPropia) $con->commit();
        } catch (\Throwable $e) {
            if ($transaccionPropia && $con->inTransaction()) $con->rollBack();
            foreach ($guardados as $ruta) if (is_file($ruta)) unlink($ruta);
            return ['status'=>'error','message'=>'No se pudieron guardar todas las imágenes'];
        }
        return self::galeria();
    }

    private static function validarImagenes(?array $files): ?string
    {
        if (!$files || !is_array($files['name'] ?? null) || count($files['name']) < 1 || count($files['name']) > 20) return 'Selecciona entre 1 y 20 imágenes';
        $permitidos = ['image/jpeg','image/png','image/gif','image/webp'];
        foreach ($files['name'] as $i=>$nombre) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($files['size'][$i] ?? 0) < 1 || $files['size'][$i] > 10*1024*1024 || !is_uploaded_file($files['tmp_name'][$i] ?? '')) return 'Cada imagen debe tener menos de 10 MB';
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($files['tmp_name'][$i]);
            if (!in_array($mime, $permitidos, true) || !@getimagesize($files['tmp_name'][$i])) return 'Usa JPEG, PNG, GIF o WebP';
        }
        return null;
    }

    public static function ordenar(): array
    {
        if ($error = Acl::guard('variantes')) return $error;
        $id = (int) ($_POST['id_imagen'] ?? 0);
        $orden = (int) ($_POST['orden'] ?? 0);
        $principal = (int) ($_POST['principal'] ?? 0) === 1;
        $con = Conexion::getInstance();
        $sth = $con->prepare('SELECT id_color,orden_imagen FROM productos_imagenes WHERE id_imagen=?');
        $sth->execute([$id]);
        $actual = $sth->fetch();
        $color = (int) ($actual['id_color'] ?? 0);
        if (!$color) return ['status'=>'error','message'=>'Imagen no encontrada'];
        $con->beginTransaction();
        try {
            if ($principal) $con->prepare('UPDATE productos_imagenes SET principal_imagen=0 WHERE id_color=?')->execute([$color]);
            if ($orden !== (int)$actual['orden_imagen']) {
                $arriba = $orden < (int)$actual['orden_imagen'];
                $sth = $con->prepare('SELECT id_imagen,orden_imagen FROM productos_imagenes WHERE id_color=? AND id_imagen<>? AND orden_imagen '.($arriba ? '<' : '>').' ? ORDER BY orden_imagen '.($arriba ? 'DESC' : 'ASC').',id_imagen LIMIT 1');
                $sth->execute([$color,$id,(int)$actual['orden_imagen']]);
                $vecina = $sth->fetch();
                if ($vecina) {
                    $con->prepare('UPDATE productos_imagenes SET orden_imagen=? WHERE id_imagen=?')->execute([(int)$vecina['orden_imagen'],$id]);
                    $con->prepare('UPDATE productos_imagenes SET orden_imagen=? WHERE id_imagen=?')->execute([(int)$actual['orden_imagen'],(int)$vecina['id_imagen']]);
                }
            }
            if ($principal) $con->prepare('UPDATE productos_imagenes SET principal_imagen=1 WHERE id_imagen=?')->execute([$id]);
            $con->commit();
        } catch (\Throwable $e) { $con->rollBack(); throw $e; }
        $_POST['id_color'] = $color;
        return self::galeria();
    }

    public static function eliminarImagen(): array
    {
        if ($error = Acl::guard('variantes')) return $error;
        $id = (int) ($_POST['id_imagen'] ?? 0);
        $con = Conexion::getInstance();
        $sth = $con->prepare('SELECT id_color,url_imagen FROM productos_imagenes WHERE id_imagen=?');
        $sth->execute([$id]);
        $img = $sth->fetch();
        if (!$img) return ['status'=>'error','message'=>'Imagen no encontrada'];
        $con->prepare('DELETE FROM productos_imagenes WHERE id_imagen=?')->execute([$id]);
        $ruta = PUBLIC_ROOT_HOST.'imgs/productos/'.basename($img['url_imagen']);
        if (is_file($ruta)) unlink($ruta);
        $con->prepare('UPDATE productos_imagenes SET principal_imagen=1 WHERE id_imagen=(SELECT primera FROM (SELECT MIN(id_imagen) AS primera FROM productos_imagenes WHERE id_color=?) x) AND NOT EXISTS (SELECT 1 FROM (SELECT id_imagen FROM productos_imagenes WHERE id_color=? AND principal_imagen=1) y)')->execute([$img['id_color'],$img['id_color']]);
        $_POST['id_color'] = $img['id_color'];
        return self::galeria();
    }
}
